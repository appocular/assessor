<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class TriggerFindingCheckpointBaseline implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  SnapshotUpdated  $event
     * @return void
     */
    public function handle(SnapshotUpdated $event)
    {
        $snapshot = $event->snapshot;
        if ($snapshot->wasChanged('baseline') && $baseline = $snapshot->getBaseline()) {
            Log::info(sprintf('Collectiong Checkpoints for baselines finding for snapshot %s', $snapshot->id));
            $baselineCheckpoints = [];
            foreach ($baseline->checkpoints()->get() as $checkpoint) {
                if ($checkpoint->shouldPropagate()) {
                    $baselineCheckpoints[$checkpoint->name] = $checkpoint;
                }
            }

            foreach ($snapshot->checkpoints()->get() as $checkpoint) {
                unset($baselineCheckpoints[$checkpoint->name]);
                dispatch(new FindCheckpointBaseline($checkpoint));
            }

            foreach ($baselineCheckpoints as $baseCheckpoint) {
                try {
                    $checkpoint = new Checkpoint([
                        'id' => hash('sha1', $snapshot->id . $baseCheckpoint->name),
                        'snapshot_id' => $snapshot->id,
                        'name' => $baseCheckpoint->name,
                        'image_sha' => '',
                    ]);
                    $checkpoint->save();
                    dispatch(new FindCheckpointBaseline($checkpoint));
                } catch (Throwable $e) {
                    // We'll assume that any errors is because someone beat us
                    // in creating the checkpoint, and quietly chug along.
                }
            }
        }
    }
}
