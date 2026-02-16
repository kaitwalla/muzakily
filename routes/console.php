<?php

use App\Jobs\RefreshStaleSmartPlaylistsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh smart playlists that haven't been updated in 24 hours
Schedule::job(new RefreshStaleSmartPlaylistsJob(staleAfterHours: 24))
    ->hourly()
    ->withoutOverlapping();
