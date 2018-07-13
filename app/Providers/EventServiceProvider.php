<?php

namespace Oogle\Assessor\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Oogle\Assessor\Events\SomeEvent' => [
            'Oogle\Assessor\Listeners\EventListener',
        ],
    ];
}
