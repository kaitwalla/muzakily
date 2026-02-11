<?php

declare(strict_types=1);

namespace App\Services\Streaming;

use App\Contracts\MusicStorageInterface;
use App\Jobs\TranscodeSongJob;
use App\Models\Song;
use App\Models\Transcode;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;

class TranscodingService
{
    public function __construct(
        private MusicStorageInterface $storage,
    ) {}

    /**
     * Get the stream URL for a song in the requested format.
     */
    public function getStreamUrl(Song $song, string $format, int $bitrate = 256): string
    {
        // Original format - no transcoding needed
        if ($format === 'original' || $song->audio_format->value === $format) {
            return $this->storage->getStreamUrl(
                $song->storage_path,
                config('muzakily.r2.presigned_expiry', 3600)
            );
        }

        // Check for cached transcode
        $transcode = Transcode::findForSong($song, $format, $bitrate);
        if ($transcode) {
            return $this->storage->getStreamUrl(
                $transcode->storage_key,
                config('muzakily.r2.presigned_expiry', 3600)
            );
        }

        // Queue transcoding job, return original for now
        TranscodeSongJob::dispatch($song, $format, $bitrate);

        return $this->storage->getStreamUrl(
            $song->storage_path,
            config('muzakily.r2.presigned_expiry', 3600)
        );
    }

    /**
     * Transcode a song and store the result.
     */
    public function transcodeAndStore(Song $song, string $format, int $bitrate): Transcode
    {
        // Check if already exists
        $existing = Transcode::findForSong($song, $format, $bitrate);
        if ($existing) {
            return $existing;
        }

        // Download original to temp
        $tempInput = tempnam(sys_get_temp_dir(), 'muzakily_input_');
        $tempOutputBase = tempnam(sys_get_temp_dir(), 'muzakily_output_');
        $tempOutput = $tempOutputBase . '.' . $format;

        if ($tempInput === false || $tempOutputBase === false) {
            throw new \RuntimeException('Failed to create temporary files for transcoding');
        }

        // Clean up the base temp file as we'll use the one with extension
        @unlink($tempOutputBase);

        try {
            $this->storage->download($song->storage_path, $tempInput);

            // Transcode with FFmpeg
            $this->ffmpeg($tempInput, $tempOutput, $format, $bitrate);

            // Upload to R2
            $storageKey = Transcode::generateStorageKey($song, $format, $bitrate);
            $this->storage->upload($storageKey, $tempOutput);

            // Use firstOrCreate to handle race conditions
            return Transcode::firstOrCreate(
                [
                    'song_id' => $song->id,
                    'format' => $format,
                    'bitrate' => $bitrate,
                ],
                [
                    'storage_key' => $storageKey,
                    'file_size' => filesize($tempOutput) ?: 0,
                ]
            );
        } finally {
            // Cleanup temp files
            @unlink($tempInput);
            @unlink($tempOutput);
        }
    }

    /**
     * Run FFmpeg to transcode audio.
     */
    private function ffmpeg(string $input, string $output, string $format, int $bitrate): void
    {
        $codec = match ($format) {
            'mp3' => 'libmp3lame',
            'aac' => 'aac',
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };

        $result = Process::timeout(300)->run([
            'ffmpeg',
            '-i', $input,
            '-codec:a', $codec,
            '-b:a', "{$bitrate}k",
            '-y',
            $output,
        ]);

        if (!$result->successful()) {
            throw new ProcessFailedException($result);
        }
    }

    /**
     * Check if FFmpeg is available.
     */
    public function isFFmpegAvailable(): bool
    {
        $result = Process::run(['ffmpeg', '-version']);

        return $result->successful();
    }
}
