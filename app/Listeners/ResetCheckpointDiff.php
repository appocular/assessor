<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdating;

class ResetCheckpointDiff
{
    /**
     * Handle the event.
     *
     * @param  CheckpointUpdating  $event
     * @return void
     */
    public function handle(CheckpointUpdating $event)
    {
        $checkpoint = $event->checkpoint;
        if ($checkpoint->isDirty('image_sha') || $checkpoint->isDirty('baseline_sha')) {
            $checkpoint->resetDiff();
        }
    }
}
