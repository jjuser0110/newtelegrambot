<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Run "php artisan schedule:work" (local) or a cron "* * * * * php artisan schedule:run" (server),
 * plus "php artisan queue:work" so broadcasts are sent.
 */
Schedule::command('broadcasts:dispatch-due')->everyMinute()->withoutOverlapping(5);
