<?php

use App\Console\Commands\CheckSystemUpdatesCommand;
use App\Console\Commands\SendProjectTaskDueSoonRemindersCommand;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceSignal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendProjectTaskDueSoonRemindersCommand::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(CheckSystemUpdatesCommand::class)
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('model:prune', ['--model' => [ConferenceSignal::class]])
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function (): void {
    ConferenceParticipant::query()
        ->whereNull('left_at')
        ->where(
            'last_seen_at',
            '<',
            now()->subSeconds((int) config('conference.presence_timeout_seconds', 120)),
        )
        ->update(['left_at' => now()]);
})
    ->name('conference:expire-stale-participants')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
