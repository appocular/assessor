<?php

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;

class SnapshotObserver
{
    /**
     * Handle to the Snapshot "created" event.
     */
    public function created(Snapshot $snapshot)
    {
        // Start a baselining job if the snapshot has history.
        $history = $snapshot->history;
        if (!$history) {
            return;
        }
        dispatch(new SnapshotBaselining($snapshot));
    }

    /**
     * Handle to the Snapshot "updated" event.
     */
    public function updated(Snapshot $snapshot)
    {
        // Reset checkpoint baselines when snapshot baseline changes.
        if ($snapshot->isDirty('baseline')) {
            Log::info(sprintf('Resetting Checkpoint baselines for snapshot %s', $snapshot->id));
            Checkpoint::resetBaselines($snapshot->id);
        }

        // Queue checkpoint baselining if snapshot baseline was changed.
        if ($snapshot->isDirty('baseline') && $snapshot->getBaseline()) {
            dispatch(new QueueCheckpointBaselining($snapshot));
        }
    }
}