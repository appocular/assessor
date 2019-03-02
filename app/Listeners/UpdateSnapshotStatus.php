<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdated;

class UpdateSnapshotStatus
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
