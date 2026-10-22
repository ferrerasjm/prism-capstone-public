<?php

use Illuminate\Support\Facades\Schedule;

$schedulerLog = storage_path('logs/scheduler.log');

// The intervention reminder sweep runs every five minutes (288x/day). It gets its own
// log so its heartbeat lines cannot bury the daily jobs' output in scheduler.log.
$reminderLog = storage_path('logs/scheduler-reminders.log');

Schedule::command('cases:finalize-expired-appeals')
    ->dailyAt('00:05')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('academic-periods:notify-started')
    ->dailyAt('00:10')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(15)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('risk:compute')
    ->dailyAt('05:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('us:flag-overdue')
    ->dailyAt('06:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('cases:process-nte-deadlines')
    ->dailyAt('06:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('sanctions:send-intervention-reminders')
    ->everyFiveMinutes()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo($reminderLog);

Schedule::command('sanctions:close-monitoring-periods')
    ->dailyAt('01:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('sanctions:close-probation-periods')
    ->dailyAt('01:20')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('prism:automation-check')
    ->dailyAt('06:45')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(15)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

Schedule::command('prism:privacy-check')
    ->monthlyOn(1, '06:50')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(15)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);

// Runs daily rather than only on the 1st: the command is idempotent (it no-ops when the
// previous month is already archived), so a day the server is down no longer loses the
// month permanently -- it is picked up on the next day the scheduler runs.
Schedule::command('reports:archive-monthly')
    ->dailyAt('00:20')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo($schedulerLog);
