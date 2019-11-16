<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Models\Snapshot;

class QueueCheckpointBaselining extends Job
{
    /**
     * Snapshot to queue checkpoint baselining for.
     *
     * @var \Appocular\Assessor\Models\Snapshot
     */
    public $snapshot;

    public function __construct(Snapshot $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->snapshot->refresh();
        $baseline = $this->snapshot->getBaseline();

        if (!$baseline || $baseline->run_status !== Snapshot::RUN_STATUS_DONE) {
            // Cop out if things change too quickly.
            return;
        }

        // Trigger checkpoint baselining.
        $this->snapshot->triggerCheckpointBaselining();
    }
}
