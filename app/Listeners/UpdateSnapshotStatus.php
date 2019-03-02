<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdated;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class UpdateSnapshotStatus implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  CheckpointUpdated  $event
     * @return void
     */
    public function handle(CheckpointUpdated $event)
    {
        $checkpoint = $event->checkpoint;
        if ($checkpoint->wasChanged('status')) {
            $checkpoint->snapshot->updateStatus();
        }
    }
}
