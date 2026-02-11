<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MusicStorageInterface;
use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\Library\MetadataExtractorService;
use App\Services\Library\SmartFolderService;
use App\Services\Library\TagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUploadedSongJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $storagePath,
        public string $originalFilename,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        MusicStorageInterface $storage,
        MetadataExtractorService $metadataExtractor,
        SmartFolderService $smartFolderService,
        TagService $tagService,
    ): void {
        // Download file temporarily
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_upload_');

        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file for upload processing');
        }

        try {
            $storage->download($this->storagePath, $tempPath);

            // Get file metadata from storage
            $storageMetadata = $storage->getMetadata($this->storagePath);

            // Extract audio metadata
            $metadata = $metadataExtractor->extract($tempPath);

            // Determine audio format
            $extension = pathinfo($this->storagePath, PATHINFO_EXTENSION);
            $format = AudioFormat::fromExtension($extension);

            if (!$format) {
                throw new \RuntimeException("Unsupported audio format: {$extension}");
            }

            // Wrap all database operations in a transaction to prevent partial state
            $song = DB::transaction(function () use ($metadata, $format, $storageMetadata, $smartFolderService, $tagService) {
                // Find or create artist
                $artist = null;
                if ($metadata['artist'] ?? null) {
                    $artist = Artist::findOrCreateByName($metadata['artist']);
                }

                // Find or create album
                $album = null;
                if ($metadata['album'] ?? null) {
                    $album = Album::findOrCreateByNameAndArtist($metadata['album'], $artist);
                    if ($metadata['year'] ?? null) {
                        $album->update(['year' => $metadata['year']]);
                    }
                }

                // Assign smart folder
                $smartFolder = $smartFolderService->assignFromPath($this->storagePath);

                // Create song
                $song = Song::create([
                    'album_id' => $album?->id,
                    'artist_id' => $artist?->id,
                    'smart_folder_id' => $smartFolder?->id,
                    'title' => $metadata['title'] ?? pathinfo($this->originalFilename, PATHINFO_FILENAME),
                    'album_name' => $metadata['album'] ?? null,
                    'artist_name' => $metadata['artist'] ?? null,
                    'length' => $metadata['duration'],
                    'track' => $metadata['track'] ?? null,
                    'disc' => $metadata['disc'] ?? 1,
                    'year' => $metadata['year'] ?? null,
                    'lyrics' => $metadata['lyrics'] ?? null,
                    'storage_path' => $this->storagePath,
                    'file_hash' => $storageMetadata['etag'] ?? null,
                    'file_size' => $storageMetadata['size'] ?? 0,
                    'mime_type' => $format->mimeType(),
                    'audio_format' => $format->value,
                    'r2_etag' => $storageMetadata['etag'] ?? null,
                    'r2_last_modified' => $storageMetadata['last_modified'] ?? null,
                ]);

                // Update smart folder song count
                $smartFolder?->updateSongCount();

                // Assign tags based on folder path
                if (config('muzakily.tags.auto_create_from_folders', true)) {
                    $tags = $tagService->assignFromPath($song);
                    foreach ($tags as $tag) {
                        $tag->updateSongCount();
                    }
                }

                return $song;
            });

            // Queue metadata enrichment (outside transaction as it dispatches a job)
            EnrichMetadataJob::dispatch([$song->id]);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Determine if the job should retry based on the exception.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessUploadedSongJob failed permanently', [
            'storage_path' => $this->storagePath,
            'original_filename' => $this->originalFilename,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
