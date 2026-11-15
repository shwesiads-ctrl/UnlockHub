<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — run `php artisan schedule:work` (local) or a cron entry
| `* * * * * php /path/to/artisan schedule:run` (server).
| API orders are sent by the queue worker: `php artisan queue:work`.
*/

Schedule::command('orders:check-api')->everyMinute()->withoutOverlapping(10);
Schedule::command('suppliers:sync')->hourly()->withoutOverlapping();
Schedule::command('tickets:close-resolved')->dailyAt('03:10');
Schedule::command('statements:monthly')->monthlyOn(1, '08:00')->withoutOverlapping();
