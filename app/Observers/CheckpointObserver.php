<?php

declare(strict_types=1);

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Assessor\Models\Checkpoint;

class CheckpointObserver
{
    /**
     * Handle the Checkpoint "updating" event.
     */
    public function updating(Checkpoint $checkpoint): void
    {
        // Set image status to available for pendin/expected images when they get an image.
        if ($checkpoint->isPending() && $checkpoint->isDirty('image_url') && $checkpoint->image_url) {
            $checkpoint->image_status = Checkpoint::IMAGE_STATUS_AVAILABLE;
        }

        // Reset diff if image or baseline changes.
        if ($checkpoint->isDirty('image_url') || $checkpoint->isDirty('baseline_url')) {
            $checkpoint->resetDiff();
        }

        // Set diff status for new and deleted images immediately.
        if (!$checkpoint->hasDiff()) {
            if ($checkpoint->baseline_url === '' || $checkpoint->image_url === '') {
                $checkpoint->diff_status = Checkpoint::DIFF_STATUS_DIFFERENT;
            }
        }

        // Update status when diff_status is set.
        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if ($checkpoint->isDirty('diff_status')) {
            $checkpoint->updateStatusFromDiff();
        }
    }

    /**
     * Handle the Checkpoint "updated" event.
     */
    public function updated(Checkpoint $checkpoint): void
    {
        // Submit diff when there isn't one and image or baseline changes.
        if (
            !$checkpoint->hasDiff() &&
            $checkpoint->image_url &&
            $checkpoint->baseline_url &&
            $checkpoint->isDirty('image_url', 'baseline_url')
        ) {
            \dispatch(new SubmitDiff($checkpoint->image_url, $checkpoint->baseline_url));
        }

        // Update snapshot status when checkpoint approval status or image status changes.
        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if ($checkpoint->isDirty('approval_status') || $checkpoint->isDirty('image_status')) {
            $checkpoint->snapshot->updateStatus();
        }
    }
}
