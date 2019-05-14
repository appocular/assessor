<?php

namespace Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Observers\CheckpointObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseMigrations;

class CheckpointObserverTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that diffs gets reset when image or baseline is updated.
     */
    public function testUpdatingResetsDiffWhenImageOrBaselineChanges()
    {
        $observer = new CheckpointObserver();

        $snapshot = factory(Snapshot::class)->create();

        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_sha' => 'image',
            'baseline_sha' => 'baseline',
            'diff_sha' => 'a diff',
            'status' => Checkpoint::STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_DIFFERENT,
        ]);
        $checkpoint->save();
        $checkpoint->image_sha = 'new image';

        $observer->updating($checkpoint);

        $this->assertEquals(null, $checkpoint->diff_sha);
        $this->assertEquals(Checkpoint::DIFF_STATUS_UNKNOWN, $checkpoint->diff_status);
    }

    /**
     * Test that checkpoints processed by the user isn't reset.
     *
     * @dataProvider processedStatuses
     */
    public function testUpdatingDoesNotResetDiffForUserProcessedCheckpoints($status)
    {
        $observer = new CheckpointObserver();

        $snapshot = factory(Snapshot::class)->create();

        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_sha' => 'image',
            'baseline_sha' => 'baseline',
            'diff_sha' => 'a diff',
            'status' => $status,
            'diff_status' => Checkpoint::DIFF_STATUS_DIFFERENT,
        ]);
        $checkpoint->save();
        $checkpoint->image_sha = 'new image';

        $observer->updating($checkpoint);

        $this->assertEquals('a diff', $checkpoint->diff_sha);
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoint->diff_status);
    }

    public function processedStatuses()
    {
        return [
            [Checkpoint::STATUS_APPROVED],
            [Checkpoint::STATUS_REJECTED],
            [Checkpoint::STATUS_IGNORED],
        ];
    }

    /**
     * Test that snapshot status gets updated when checkpoint status changes.
     */
    public function testUpdatedTriggersSnapshotStatusUpdateOnCheckpointStatusChange()
    {
        $observer = new CheckpointObserver();

        $snapshot = factory(Snapshot::class)->create(['status' => Snapshot::STATUS_UNKNOWN]);

        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_sha' => 'image',
            'baseline_sha' => 'baseline',
            'diff_sha' => 'a diff',
            'status' => Checkpoint::STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_DIFFERENT,
        ]);

        $observer->updated($checkpoint);

        $this->assertEquals(Snapshot::STATUS_UNKNOWN, $snapshot->refresh()->status);

        $checkpoint->status = Checkpoint::STATUS_APPROVED;
        // Normally, the updated method is called after saving the model to
        // the dotabase, but before changes are synced, so the changes are
        // still 'dirty'. If we save the checkpoint first, then the observer
        // wont do anything as it's not dirty after saving. But
        // Snapshot::updateStatus() requires the checkpoint to have hit the
        // database in order to pick up the new status. So we work around this
        // problem by updating the model, not saving it but updating the
        // database row manually. Then the observer will see the change *and*
        // the snapshot will see the new status.
        DB::table('checkpoints')->where('id', $checkpoint->id)->update(['status' => Checkpoint::STATUS_APPROVED]);

        $observer->updated($checkpoint);

        $this->assertEquals(Snapshot::STATUS_PASSED, $snapshot->refresh()->status);

        $checkpoint->status = Checkpoint::STATUS_REJECTED;
        DB::table('checkpoints')->where('id', $checkpoint->id)->update(['status' => Checkpoint::STATUS_REJECTED]);

        $observer->updated($checkpoint);

        $this->assertEquals(Snapshot::STATUS_FAILED, $snapshot->refresh()->status);
    }
}
