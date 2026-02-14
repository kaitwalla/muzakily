<?php

declare(strict_types=1);

namespace App\Actions\Albums;

use App\Models\Album;
use App\Services\Library\CoverArtService;
use Illuminate\Http\UploadedFile;

final readonly class UploadAlbumCover
{
    public function __construct(
        private CoverArtService $coverService,
    ) {}

    /**
     * Upload a custom cover image for an album.
     *
     * @return bool True if cover was uploaded successfully
     */
    public function execute(Album $album, UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType() ?? 'image/jpeg';
        $content = $file->getContent();

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            return false;
        }

        $coverUrl = $this->coverService->storeForAlbum($album, [
            'data' => $content,
            'mime_type' => $mimeType,
        ]);

        if ($coverUrl === null) {
            return false;
        }

        return $album->update(['cover' => $coverUrl]);
    }
}
