<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Schedule::command('cost-per-plate:generate')
    ->monthlyOn(1, '2:00')        // cada día 1 a las 02:00
    ->timezone('America/Lima')    // tu zona horaria
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('debt-days:generate')
    ->timezone('America/Lima')
    ->lastDayOfMonth('23:00')
    ->onOneServer();
