<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;

class SnapshotBaselining extends Job
{
    /**
     * @var Snapshot
     */
    public $snapshot;

    public function __construct(Snapshot $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $snapshot = $this->snapshot;
        $snapshot->refresh();
        $history = $snapshot->history;
        if (!$history) {
            // Someone beat us to it.
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
