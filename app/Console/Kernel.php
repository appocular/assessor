<?php

declare(strict_types=1);

namespace Appocular\Assessor\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<string>
     */
    protected $commands = [
        Commands\ApproveSnapshot::class,
        Commands\AddRepo::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    protected function schedule(Schedule $schedule): void
    {
    }
}
