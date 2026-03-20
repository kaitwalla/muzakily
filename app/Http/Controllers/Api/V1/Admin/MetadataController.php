<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnrichMetadataJob;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    /**
     * Trigger metadata enrichment.
     */
    public function enrich(Request $request): JsonResponse
    {
        $request->validate([
            'song_ids' => ['nullable', 'array'],
            'song_ids.*' => ['uuid'],
        ]);

        $songIds = $request->input('song_ids')
            ?? Song::query()->select('id')->pluck('id')->all();

        foreach ($songIds as $songId) {
            EnrichMetadataJob::dispatch($songId);
        }

        $count = count($songIds);

        return response()->json([
            'data' => [
                'status' => 'started',
                'message' => "Enriching metadata for {$count} songs",
            ],
        ], 202);
    }
}
