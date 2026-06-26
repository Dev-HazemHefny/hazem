<?php

use App\Jobs\MarkPastDueOrchestratorJob;
use App\Jobs\RunBillingOrchestratorJob;
use App\Jobs\RunRecognitionOrchestratorJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RunBillingOrchestratorJob)->daily();
Schedule::job(new RunRecognitionOrchestratorJob)->monthlyOn(1, '02:00');
Schedule::job(new MarkPastDueOrchestratorJob)->daily();
