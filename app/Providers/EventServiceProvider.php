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
            'Appocular\Assessor\Listeners\QueueSnapshotBaselining',
        ],
        'Appocular\Assessor\Events\SnapshotUpdated' => [
            'Appocular\Assessor\Listeners\ResetCheckpointBaselines',
            'Appocular\Assessor\Listeners\QueueCheckpointsBaselining',
        ],
        'Appocular\Assessor\Events\CheckpointUpdated' => [
            'Appocular\Assessor\Listeners\UpdateSnapshotStatus',
        ],
        'Appocular\Assessor\Events\DiffSubmitted' => [
            'Appocular\Assessor\Listeners\UpdateCheckpointsDiffs',
        ],
    ];
}
