<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'songs' => Song::count(),
                'albums' => Album::count(),
                'artists' => Artist::count(),
                'playlists' => Playlist::count(),
                'total_duration' => (int) Song::sum('length'),
                'total_size' => (int) Song::sum('file_size'),
            ],
        ]);
    }
}
