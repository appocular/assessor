<?php

namespace Ogle\Assessor\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Ogle\Assessor\Events\SomeEvent' => [
            'Ogle\Assessor\Listeners\EventListener',
        ],
    ];
}
