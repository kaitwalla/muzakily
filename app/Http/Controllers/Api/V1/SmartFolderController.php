<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SmartFolderResource;
use App\Http\Resources\Api\V1\SongResource;
use App\Models\SmartFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmartFolderController extends Controller
{
    /**
     * Display a listing of smart folders.
     */
    public function index(): AnonymousResourceCollection
    {
        $folders = SmartFolder::whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        return SmartFolderResource::collection($folders);
    }

    /**
     * Get songs for the specified smart folder.
     */
    public function songs(Request $request, SmartFolder $smartFolder): AnonymousResourceCollection
    {
        $query = $smartFolder->songs()
            ->with(['artist', 'album', 'genres']);

        // Sorting
        $sortField = $request->input('sort', 'title');
        $sortOrder = $request->input('order', 'asc');
        $allowedSorts = ['title', 'artist_name', 'album_name', 'year'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return SongResource::collection($query->paginate($perPage));
    }
}
