<?php

use App\Jobs\SyncAssetPricesJob;
use App\Jobs\SyncFutureDividendsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncAssetPricesJob)
    ->weekdays()
    ->hourlyAt(15)
    ->between('10:00', '18:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

Schedule::job(new SyncAssetPricesJob)
    ->weekdays()
    ->at('19:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

Schedule::job(new SyncFutureDividendsJob)
    ->dailyAt('07:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();
