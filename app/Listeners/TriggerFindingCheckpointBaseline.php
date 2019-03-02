<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;
use Illuminate\Support\Facades\Log;

class TriggerFindingCheckpointBaseline implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  SnapshotUpdated  $event
     * @return void
     */
    public function handle(SnapshotUpdated $event)
    {
        Log::debug(print_r($event->snapshot, true));
        $snapshot = $event->snapshot;
        if ($snapshot->wasChanged('baseline') && $baseline = $snapshot->getBaseline()) {
            $snapshot->triggerCheckpointBaselining();
        }
    }
}
