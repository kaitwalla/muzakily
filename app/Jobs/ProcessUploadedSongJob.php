<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AudioFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\Library\MetadataExtractorService;
use App\Services\Library\SmartFolderService;
use App\Services\Storage\R2StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUploadedSongJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
        R2StorageService $r2Storage,
        MetadataExtractorService $metadataExtractor,
        SmartFolderService $smartFolderService,
    ): void {
        // Download file temporarily
        $tempPath = tempnam(sys_get_temp_dir(), 'muzakily_upload_');

        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file for upload processing');
        }

        try {
            $r2Storage->download($this->storagePath, $tempPath);

            // Get file metadata from R2
            $r2Metadata = $r2Storage->getMetadata($this->storagePath);

            // Extract audio metadata
            $metadata = $metadataExtractor->extract($tempPath);

            // Determine audio format
            $extension = pathinfo($this->storagePath, PATHINFO_EXTENSION);
            $format = AudioFormat::fromExtension($extension);

            if (!$format) {
                throw new \RuntimeException("Unsupported audio format: {$extension}");
            }

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
            Song::create([
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
                'file_hash' => $r2Metadata['etag'] ?? null,
                'file_size' => $r2Metadata['size'] ?? 0,
                'mime_type' => $format->mimeType(),
                'audio_format' => $format->value,
                'r2_etag' => $r2Metadata['etag'] ?? null,
                'r2_last_modified' => $r2Metadata['last_modified'] ?? null,
            ]);

            // Update smart folder song count
            $smartFolder?->updateSongCount();

            // Queue metadata enrichment
            EnrichMetadataJob::dispatch([$this->storagePath]);
        } finally {
            @unlink($tempPath);
        }
    }
}
