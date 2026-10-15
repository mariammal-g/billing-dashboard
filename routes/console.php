<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\GenerateCycleInvoicesJob;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Run shortly after the billing period closes. In production, make the exact
// timezone explicit in app config and coordinate this with the merchant timezone.
Schedule::job(new GenerateCycleInvoicesJob)->dailyAt('00:15');

