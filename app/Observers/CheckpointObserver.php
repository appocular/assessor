<?php

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Checkpoint;
//use Appocular\Assessor\Snapshot;
//use Illuminate\Support\Facades\Log;

class CheckpointObserver
{
    /**
     * Handle to the Snapshot "updating" event.
     */
    public function updating(Checkpoint $checkpoint)
    {
        // Reset diff if image or baseline changes.
        if ($checkpoint->isDirty('image_sha') || $checkpoint->isDirty('baseline_sha')) {
            $checkpoint->resetDiff();
        }
    }

    /**
     * Handle to the Checkpoint "updated" event.
     */
    public function updated(Checkpoint $checkpoint)
    {
        // Update snapshot status when checkpoint status changes.
        if ($checkpoint->isDirty('status')) {
            $checkpoint->snapshot->updateStatus();
        }
    }
}
