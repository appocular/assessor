<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Events\SnapshotCreated;
use Appocular\Assessor\History;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class FindSnapshotBaseline implements ShouldQueue
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
        $history = $snapshot->history;
        if (!$history) {
            return;
        }
        Log::info(sprintf('Finding baseline for snapshot %s', $snapshot->id));
        $foundBaseline = null;

        foreach (explode("\n", $history->history) as $id) {
            if ($baseline = Snapshot::find($id)) {
                $foundBaseline = $baseline;
                break;
            }
        }

        if ($foundBaseline) {
            $snapshot->setBaseline($foundBaseline);
        } else {
            $snapshot->setNoBaseline();
        }
        Log::info(sprintf(
            'Setting baseline for snapshot %s to %s',
            $snapshot->id,
            $foundBaseline ? $foundBaseline->id : '"none"'
        ));

        $snapshot->save();
        $history->delete();
    }
}