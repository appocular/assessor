<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Snapshot;

class QueueCheckpointsBaselining
{
    /**
     * Handle the event.
     *
     * @param  SnapshotUpdated  $event
     * @return void
     */
    public function handle(SnapshotUpdated $event)
    {
        if ($event->snapshot->isDirty('baseline') && $baseline = $event->snapshot->getBaseline()) {
            dispatch(new QueueCheckpointBaselining($event->snapshot));
        }
    }
}
