<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Models\Playlist;
use App\Services\Playlist\PlaylistCoverService;
use Illuminate\Http\UploadedFile;

final readonly class UploadPlaylistCover
{
    public function __construct(
        private PlaylistCoverService $coverService,
    ) {}

    /**
     * Upload a custom cover image for a playlist.
     *
     * @return bool True if cover was uploaded successfully
     */
    public function execute(Playlist $playlist, UploadedFile $file): bool
    {
        return $this->coverService->uploadCover($playlist, $file);
    }
}
