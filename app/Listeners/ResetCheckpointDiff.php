<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdated;

class ResetCheckpointDiff
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
        if ($checkpoint->isDirty('image_sha') || $checkpoint->isDirty('baseline_sha')) {
            $checkpoint->resetDiff();
        }
    }
}
