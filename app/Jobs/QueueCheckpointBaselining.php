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
        if (!$baseline || $baseline->run_status != Snapshot::RUN_STATUS_DONE) {
            // Cop out if things change too quickly.
            return;
        }

        // Trigger checkpoint baselining.
        $this->snapshot->triggerCheckpointBaselining();
    }
}
