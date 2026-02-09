<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Policies\PlaylistPolicy;
use App\Policies\SongPolicy;
use App\Policies\TagPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            /** @var string $host */
            $host = config('scout.meilisearch.host', 'http://localhost:7700');
            /** @var string|null $key */
            $key = config('scout.meilisearch.key');

            return new Client($host, $key);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define admin gate for admin middleware
        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });

        Gate::policy(Song::class, SongPolicy::class);
        Gate::policy(Playlist::class, PlaylistPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
    }
}
