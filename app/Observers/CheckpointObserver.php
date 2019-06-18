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
        if ($checkpoint->isDirty('image_url') || $checkpoint->isDirty('baseline_url')) {
            $checkpoint->resetDiff();
        }

        // Update status when diff_status is set.
        if ($checkpoint->isDirty('diff_status')) {
            $checkpoint->updateStatus();
        }
    }

    /**
     * Handle to the Checkpoint "updated" event.
     */
    public function updated(Checkpoint $checkpoint)
    {
        // Submit diff when there isn't one and image or baseline changes.
        if (!$checkpoint->hasDiff() &&
            !empty($checkpoint->image_url) &&
            !empty($checkpoint->baseline_url) &&
            $checkpoint->isDirty('image_url', 'baseline_url')) {
            dispatch(new SubmitDiff($checkpoint->image_url, $checkpoint->baseline_url));
        }

        // Update snapshot status when checkpoint status changes.
        if ($checkpoint->isDirty('status')) {
            $checkpoint->snapshot->updateStatus();
        }
    }
}
