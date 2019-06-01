<?php

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Log;

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
        // Submit diff when there isn't one and image or baseline changes.
        if (!$checkpoint->hasDiff() &&
            !empty($checkpoint->image_sha) &&
            !empty($checkpoint->baseline_sha) &&
            $checkpoint->isDirty('image_sha', 'baseline_sha')) {
            dispatch(new SubmitDiff($checkpoint->image_sha, $checkpoint->baseline_sha));
        }

        // Update snapshot status when checkpoint status changes.
        if ($checkpoint->isDirty('status')) {
            $checkpoint->snapshot->updateStatus();
        }
    }
}
