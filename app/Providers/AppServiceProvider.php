<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Playlist;
use App\Models\Song;
use App\Policies\PlaylistPolicy;
use App\Policies\SongPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Song::class, SongPolicy::class);
        Gate::policy(Playlist::class, PlaylistPolicy::class);
    }
}
