<?php

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Log;

class CheckpointObserver
{
    /**
     * Handle the Snapshot "updating" event.
     */
    public function updating(Checkpoint $checkpoint)
    {
        // Reset diff if image or baseline changes.
        if ($checkpoint->isDirty('image_url') || $checkpoint->isDirty('baseline_url')) {
            $checkpoint->resetDiff();
        }

        // Set diff status for new and deleted images immediately.
        if (!$checkpoint->hasDiff()) {
            if ($checkpoint->baseline_url == '' || $checkpoint->image_url == '') {
                $checkpoint->diff_status = Checkpoint::DIFF_STATUS_DIFFERENT;
            }
        }

        // Update status when diff_status is set.
        if ($checkpoint->isDirty('diff_status')) {
            $checkpoint->updateStatusFromDiff();
        }
    }

    /**
     * Handle the Checkpoint "updated" event.
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
