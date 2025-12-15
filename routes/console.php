<?php

use App\Console\Commands\AuctionResult;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AuctionResult::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->sendOutputTo(storage_path('logs/auction-result.log'));
