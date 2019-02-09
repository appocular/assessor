<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Events\NewBatch;
use Appocular\Assessor\History;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
        $history = $snapshot->history;
        $foundBaseline = null;
        foreach (explode("\n", $history->history) as $id) {
            if ($baseline = Snapshot::find($id)) {
                $foundBaseline = $baseline;
                break;
            }
        }

        if ($foundBaseline) {
            $snapshot->setBaseline($baseline);
        } else {
            $snapshot->setNoBaseline();
        }

        $snapshot->save();
        $history->delete();
    }
}
