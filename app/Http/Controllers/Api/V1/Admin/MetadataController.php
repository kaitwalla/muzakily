<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnrichMetadataJob;
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

        $songIds = $request->input('song_ids');

        // Dispatch enrichment job
        EnrichMetadataJob::dispatch($songIds);

        return response()->json([
            'data' => [
                'status' => 'started',
                'message' => $songIds
                    ? 'Enriching metadata for ' . count($songIds) . ' songs'
                    : 'Enriching metadata for all songs',
            ],
        ], 202);
    }
}
