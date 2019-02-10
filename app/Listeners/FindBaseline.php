<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Events\NewBatch;
use Appocular\Assessor\History;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class FindBaseline implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  NewBatch  $event
     * @return void
     */
    public function handle(NewBatch $event)
    {
        $snapshot = Snapshot::findOrFail($event->snapshotId);
        Log::info(sprintf('Finding baseline for snapshot %s', $snapshot->id));
        $history = $snapshot->history;
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
            'Setting baseline for snapshot %s to $s',
            $snapshot->id,
            $foundBaseline ? $foundBaseline->id : '"none"'
        ));

        $snapshot->save();
        $history->delete();
    }
}
