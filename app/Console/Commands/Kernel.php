<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Define your scheduled commands here
        $schedule->command('audit:user-stats')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    }
}
