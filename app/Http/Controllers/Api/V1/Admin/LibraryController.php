<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScanR2BucketJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LibraryController extends Controller
{
    /**
     * Trigger a library scan.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'force' => ['nullable', 'boolean'],
        ]);

        $force = $request->boolean('force');
        $jobId = uniqid('scan_', true);

        // Store initial scan status
        Cache::put("scan_status:{$jobId}", [
            'status' => 'started',
            'progress' => [
                'total_files' => 0,
                'scanned_files' => 0,
                'new_songs' => 0,
                'updated_songs' => 0,
                'errors' => 0,
            ],
            'started_at' => now()->toIso8601String(),
        ], 3600);

        // Store current job ID
        Cache::put('scan_current_job', $jobId, 3600);

        // Dispatch the scan job
        ScanR2BucketJob::dispatch($jobId, $force);

        return response()->json([
            'data' => [
                'job_id' => $jobId,
                'status' => 'started',
            ],
        ], 202);
    }

    /**
     * Get the current scan status.
     */
    public function scanStatus(): JsonResponse
    {
        $jobId = Cache::get('scan_current_job');

        if (!$jobId) {
            return response()->json([
                'data' => [
                    'status' => 'idle',
                ],
            ]);
        }

        $status = Cache::get("scan_status:{$jobId}");

        if (!$status) {
            return response()->json([
                'data' => [
                    'status' => 'idle',
                ],
            ]);
        }

        return response()->json([
            'data' => $status,
        ]);
    }
}
