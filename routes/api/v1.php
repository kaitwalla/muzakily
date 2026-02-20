<?php

use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\ArtistController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\InteractionController;
use App\Http\Controllers\Api\V1\LocalStreamController;
use App\Http\Controllers\Api\V1\PlayerDeviceController;
use App\Http\Controllers\Api\V1\PlaylistController;
use App\Http\Controllers\Api\V1\RemoteControlController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SongController;
use App\Http\Controllers\Api\V1\SongTagController;
use App\Http\Controllers\Api\V1\StreamController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\Admin\LibraryController;
use App\Http\Controllers\Api\V1\Admin\MetadataController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1
|
*/

// Authentication (public)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
});

// Public config
Route::get('config', ConfigController::class)->name('config');

// Local streaming (uses signed URL for auth)
Route::get('stream/local', [LocalStreamController::class, 'stream'])->name('stream.local');

// Download (uses signed URL for auth)
Route::get('songs/{song}/download/signed', [StreamController::class, 'downloadSigned'])->name('songs.download.signed');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::delete('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::patch('me', [AuthController::class, 'updateProfile'])->name('auth.update-profile');
    });

    // Songs
    Route::get('songs/recently-played', [SongController::class, 'recentlyPlayed'])->name('songs.recently-played');
    Route::put('songs/bulk', [SongController::class, 'bulkUpdate'])->name('songs.bulk-update');
    Route::apiResource('songs', SongController::class)->only(['index', 'show', 'update']);
    Route::get('songs/{song}/stream', [StreamController::class, 'stream'])->name('songs.stream');
    Route::get('songs/{song}/download', [StreamController::class, 'download'])->name('songs.download');
    Route::post('songs/{song}/tags', [SongTagController::class, 'store'])->name('songs.tags.store');
    Route::delete('songs/{song}/tags', [SongTagController::class, 'destroy'])->name('songs.tags.destroy');

    // Albums
    Route::apiResource('albums', AlbumController::class)->only(['index', 'show']);
    Route::get('albums/{album}/songs', [AlbumController::class, 'songs'])->name('albums.songs');
    Route::post('albums/{album}/cover', [AlbumController::class, 'uploadCover'])->name('albums.upload-cover');
    Route::post('albums/{album}/refresh-cover', [AlbumController::class, 'refreshCover'])->name('albums.refresh-cover');

    // Artists
    Route::apiResource('artists', ArtistController::class)->only(['index', 'show']);
    Route::get('artists/{artist}/albums', [ArtistController::class, 'albums'])->name('artists.albums');
    Route::get('artists/{artist}/songs', [ArtistController::class, 'songs'])->name('artists.songs');

    // Playlists
    Route::apiResource('playlists', PlaylistController::class);
    Route::get('playlists/{playlist}/songs', [PlaylistController::class, 'songs'])->name('playlists.songs');
    Route::post('playlists/{playlist}/songs', [PlaylistController::class, 'addSongs'])->name('playlists.songs.add');
    Route::delete('playlists/{playlist}/songs', [PlaylistController::class, 'removeSongs'])->name('playlists.songs.remove');
    Route::put('playlists/{playlist}/songs/reorder', [PlaylistController::class, 'reorderSongs'])->name('playlists.songs.reorder');
    Route::post('playlists/{playlist}/refresh', [PlaylistController::class, 'refresh'])->name('playlists.refresh');
    Route::post('playlists/{playlist}/refresh-cover', [PlaylistController::class, 'refreshCover'])->name('playlists.refresh-cover');
    Route::post('playlists/{playlist}/cover', [PlaylistController::class, 'uploadCover'])->name('playlists.upload-cover');

    // Tags
    Route::apiResource('tags', TagController::class);
    Route::get('tags/{tag}/songs', [TagController::class, 'songs'])->name('tags.songs');

    // Favorites
    Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('favorites', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('favorites', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

    // Interactions
    Route::post('interactions/play', [InteractionController::class, 'play'])->name('interactions.play');

    // Search
    Route::get('search', [SearchController::class, 'search'])->name('search');

    // Sync
    Route::get('deleted', [SyncController::class, 'deleted'])->name('sync.deleted');
    Route::get('sync/status', [SyncController::class, 'status'])->name('sync.status');

    // Upload
    Route::post('upload', [UploadController::class, 'upload'])->name('upload');

    // Player Control
    Route::prefix('player')->group(function () {
        Route::get('devices', [PlayerDeviceController::class, 'index'])->name('player.devices.index');
        Route::post('devices', [PlayerDeviceController::class, 'store'])->name('player.devices.store');
        Route::delete('devices/{device}', [PlayerDeviceController::class, 'destroy'])->name('player.devices.destroy');
        Route::post('control', [RemoteControlController::class, 'control'])->name('player.control');
        Route::get('state', [RemoteControlController::class, 'state'])->name('player.state');
        Route::post('sync', [RemoteControlController::class, 'sync'])->name('player.sync');
    });

    // Admin routes
    Route::prefix('admin')->middleware('can:admin')->group(function () {
        // User management
        Route::apiResource('users', AdminUserController::class);

        // Library scanning
        Route::post('library/scan', [LibraryController::class, 'scan'])->name('admin.library.scan');
        Route::get('library/scan/status', [LibraryController::class, 'scanStatus'])->name('admin.library.scan.status');

        // Metadata enrichment
        Route::post('metadata/enrich', [MetadataController::class, 'enrich'])->name('admin.metadata.enrich');
    });
});
