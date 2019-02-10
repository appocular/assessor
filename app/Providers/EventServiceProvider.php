<?php

namespace Appocular\Assessor\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Appocular\Assessor\Events\SnapshotCreated' => [
            'Appocular\Assessor\Listeners\SnapshotCreatedListener',
        ],
    ];
}
