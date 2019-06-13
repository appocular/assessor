<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;

class QueueCheckpointBaselining extends Job
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
        $this->snapshot->refresh();
        $baseline = $this->snapshot->getBaseline();
        if ($baseline) {
            if ($baseline->run_status == Snapshot::RUN_STATUS_DONE) {
                // If baseline is done, trigger checkpoint baselining.
                $this->snapshot->triggerCheckpointBaselining();
            } else {
                // Else try again in 5 seconds.
                $this->release(5);
            }
        }
    }
}
