<?php

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckpointObserver
{
    /**
     * Create new observer.
     */
    public function __construct(Differ $differ)
    {
        $this->differ = $differ;
    }

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
            Log::info(sprintf(
                'Submitting diff for image %s, baseline %s',
                $checkpoint->image_sha,
                $checkpoint->baseline_sha
            ));
            try {
                $this->differ->submit($checkpoint->image_sha, $checkpoint->baseline_sha);
            } catch (Throwable $e) {
                Log::error(sprintf(
                    'Error submitting diff image %s, baseline %s: %s',
                    $checkpoint->image_sha,
                    $checkpoint->baseline_sha,
                    $e->getMessage()
                ));
            }
            Log::debug('Submitted');
        }

        // Update snapshot status when checkpoint status changes.
        if ($checkpoint->isDirty('status')) {
            $checkpoint->snapshot->updateStatus();
        }
    }
}
