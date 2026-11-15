<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — run `php artisan schedule:work` (local) or a cron entry
| `* * * * * php /path/to/artisan schedule:run` (server).
| API orders are sent by the queue worker: `php artisan queue:work`.
*/

Schedule::command('orders:check-api')->everyMinute()->withoutOverlapping(10);
Schedule::command('suppliers:sync')->hourly()->withoutOverlapping();
Schedule::command('suppliers:sync-costs')->hourlyAt(17)->withoutOverlapping();
Schedule::command('services:delivery-stats')->hourlyAt(40)->withoutOverlapping();
Schedule::command('services:delivery-stats --overdue')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('wallet:reconcile')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('tickets:close-resolved')->dailyAt('03:10');
Schedule::command('statements:monthly')->monthlyOn(1, '08:00')->withoutOverlapping();

// Shared hosting (cPanel / XAMPP) has no queue worker: process queued jobs from the
// one-per-minute cron. Turn off with QUEUE_IN_SCHEDULER=false when you run `queue:work` yourself.
if (config('unlockhub.queue_in_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
}
