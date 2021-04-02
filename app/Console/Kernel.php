<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Jobs\Test::class,
        Jobs\GetBangumi::class,
        Jobs\GetCharacter::class,
        Jobs\SetSearch::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('Test')->everyMinute();
        $schedule->command('GetCharacter')->everyMinute();
        $schedule->command('SetSearch')->everyMinute();
    }
}
