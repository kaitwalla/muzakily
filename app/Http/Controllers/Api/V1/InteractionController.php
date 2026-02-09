<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    /**
     * Record a song play.
     */
    public function play(Request $request): JsonResponse
    {
        $request->validate([
            'song_id' => ['required', 'uuid', 'exists:songs,id'],
        ]);

        $song = Song::findOrFail($request->input('song_id'));
        $interaction = Interaction::recordPlay($request->user(), $song);

        return response()->json([
            'data' => [
                'song_id' => $song->id,
                'play_count' => $interaction->play_count,
                'last_played_at' => $interaction->last_played_at->toIso8601String(),
            ],
        ]);
    }
}
