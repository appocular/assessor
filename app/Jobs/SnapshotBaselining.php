<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;

class SnapshotBaselining extends Job
{
    /**
     * Snapshot to baseline.
     *
     * @var \Appocular\Assessor\Snapshot
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
        $snapshot = $this->snapshot;
        $snapshot->refresh();
        $history = $snapshot->history;

        if (!$history) {
            // Someone beat us to it.
            return;
        }

        $history->delete();
        Log::info(\sprintf('Finding baseline for snapshot %s', $snapshot->id));
        $foundBaseline = null;

        foreach (\explode("\n", $history->history) as $id) {
            $baseline = Snapshot::find($id);

            if ($id !== $snapshot->id && $baseline) {
                $foundBaseline = $baseline;

                break;
            }
        }

        if ($foundBaseline) {
            $snapshot->setBaseline($foundBaseline);
        } else {
            $snapshot->setNoBaseline();
        }

        Log::info(\sprintf(
            'Setting baseline for snapshot %s to %s',
            $snapshot->id,
            $foundBaseline ? $foundBaseline->id : '"none"',
        ));

        $snapshot->save();
    }
}
