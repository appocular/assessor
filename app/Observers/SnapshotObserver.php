<?php

declare(strict_types=1);

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\GitHubStatusUpdate;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Log;

class SnapshotObserver
{
    /**
     * Handle the Snapshot "updated" event.
     */
    public function updated(Snapshot $snapshot): void
    {
        // Reset checkpoint baselines when snapshot baseline changes.
        if ($snapshot->isDirty('baseline')) {
            Log::info(\sprintf('Resetting Checkpoint baselines for snapshot %s', $snapshot->id));
            Checkpoint::resetBaselines($snapshot->id);
        }

        // Queue checkpoint baselining if snapshot baseline was changed and baseline is done.
        if ($snapshot->isDirty('baseline') && $snapshot->getBaseline() && $snapshot->getBaseline()->isDone()) {
            \dispatch(new QueueCheckpointBaselining($snapshot));
        }

        // Queue descendant re-baselining if status changed and the run status
        // is done, or when the run status changes to done.
        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if (
            $snapshot->isDone() &&
            ($snapshot->isDirty('status') ||
             $snapshot->isDirty('run_status'))
        ) {
            foreach ($snapshot->getDescendants() as $descendant) {
                \dispatch(new QueueCheckpointBaselining($descendant));
            }
        }
    }

    /**
     * Handle the Snapshot "saved" event.
     */
    public function saved(Snapshot $snapshot): void
    {
        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if (
            $snapshot->repo &&
            ($snapshot->wasRecentlyCreated ||
             $snapshot->isDirty('status') ||
             $snapshot->isDirty('run_status'))
        ) {
            if (GitHubStatusUpdate::isGitHubUri($snapshot->repo->uri)) {
                \dispatch(new GitHubStatusUpdate($snapshot));
            }
        }
    }
}
