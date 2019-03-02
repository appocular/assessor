<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Events\SnapshotCreated;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\History;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;

class QueueSnapshotBaselining
{
    /**
     * Handle the event.
     *
     * @param  SnapshotCreated  $event
     * @return void
     */
    public function handle(SnapshotCreated $event)
    {
        $snapshot = $event->snapshot;
        // Ensure that the snapshot is up to date.
        $snapshot->refresh();
        $history = $snapshot->history;
        if (!$history) {
            return;
        }
        dispatch(new SnapshotBaselining($snapshot));
    }
}
