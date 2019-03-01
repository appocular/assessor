<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ResetCheckpointBaselines implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  SnapshotUpdated  $event
     * @return void
     */
    public function handle(SnapshotUpdated $event)
    {
        $snapshot = $event->snapshot;
        if ($snapshot->wasChanged('baseline')) {
            Log::info(sprintf('Resetting Checkpoint baselines for snapshot %s', $snapshot->id));
            Checkpoint::resetBaseline($snapshot->id);
        }
    }
}
