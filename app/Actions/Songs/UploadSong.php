<?php

declare(strict_types=1);

namespace App\Actions\Songs;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Exceptions\UnsupportedAudioFormatException;
use App\Jobs\ProcessUploadedSongJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class UploadSong
{
    public function __construct(
        private MusicStorageInterface $storage,
    ) {}

    /**
     * Upload a song file to storage and dispatch processing job.
     *
     * @return array{upload_id: string, status: string, filename: string}
     * @throws UnsupportedAudioFormatException
     */
    public function execute(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $format = AudioFormat::fromExtension($extension);

        if ($format === null) {
            throw new UnsupportedAudioFormatException(
                'The uploaded file format is not supported.'
            );
        }

        // Generate storage path using canonical extension from enum
        $uuid = (string) Str::uuid();
        $storagePath = sprintf('uploads/%s/%s.%s', date('Y/m'), $uuid, $format->extension());

        // Store temporarily to local disk then upload to R2
        $tempPath = $file->store('temp', 'local');
        if ($tempPath === false) {
            throw new \RuntimeException('Failed to store temporary file');
        }
        $fullTempPath = Storage::disk('local')->path($tempPath);

        $dispatched = false;
        try {
            $this->storage->upload($storagePath, $fullTempPath);
            ProcessUploadedSongJob::dispatch($storagePath, $file->getClientOriginalName());
            $dispatched = true;
        } finally {
            @unlink($fullTempPath);

            // Clean up remote file if dispatch failed
            if (!$dispatched) {
                try {
                    $this->storage->delete($storagePath);
                } catch (\Throwable $e) {
                    Log::error('Failed to clean up orphaned upload', [
                        'path' => $storagePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'upload_id' => $uuid,
            'status' => 'processing',
            'filename' => $file->getClientOriginalName(),
        ];
    }
}
