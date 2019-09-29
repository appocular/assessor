<?php

namespace Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Assessor\Observers\CheckpointObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class CheckpointObserverTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that diffs gets reset when image or baseline is updated. Should
     * also reset status.
     */
    public function testUpdatingResetsDiffWhenImageOrBaselineChanges()
    {
        $observer = new CheckpointObserver();

        $snapshot = factory(Snapshot::class)->create();

        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'a diff',
            'status' => Checkpoint::STATUS_REJECTED,
            'diff_status' => Checkpoint::DIFF_STATUS_DIFFERENT,
        ]);
        $checkpoint->syncOriginal();
        $checkpoint->image_url = 'new image';

        $observer->updating($checkpoint);

        $this->assertEquals(null, $checkpoint->diff_url);
        $this->assertEquals(Checkpoint::DIFF_STATUS_UNKNOWN, $checkpoint->diff_status);
        $this->assertEquals(Checkpoint::STATUS_UNKNOWN, $checkpoint->status);
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
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'a diff',
            'status' => Checkpoint::STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_DIFFERENT,
        ]);

        $this->assertEquals(Snapshot::STATUS_UNKNOWN, $snapshot->refresh()->status);

        // To test that updateStatus() is called on the Snapshot, we'll just
        // mock it.
        $snapshotMock = $this->prophesize(Snapshot::class);
        // We'll test two times, so it should be called two times.
        $snapshotMock->updateStatus()->shouldBeCalledTimes(2);
        $checkpoint->snapshot = $snapshotMock->reveal();

        $checkpoint->syncOriginal();
        $checkpoint->status = Checkpoint::STATUS_APPROVED;
        $observer->updated($checkpoint);

        $checkpoint->syncOriginal();
        $checkpoint->status = Checkpoint::STATUS_REJECTED;

        $observer->updated($checkpoint);
    }

    /**
     * Test that diff requests are submitted when image or baseline changed.
     */
    public function testUpdatedTriggersDiffWhenImageOrBaselineChanges()
    {
        Queue::fake();

        $observer = new CheckpointObserver();

        $snapshot = factory(Snapshot::class)->create(['status' => Snapshot::STATUS_UNKNOWN]);

        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => null,
            'diff_url' => null,
            'status' => Checkpoint::STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_UNKNOWN,
        ]);

        $checkpoint->syncOriginal();
        $checkpoint->baseline_url = 'baseline';
        $this->assertFalse($checkpoint->hasDiff());
        Queue::assertNotPushed(SubmitDiff::class);

        $observer->updated($checkpoint);

        Queue::assertPushed(SubmitDiff::class);
    }

    /**
     * Test that updating diff maybe results in an state change.
     *
     * @dataProvider diffStatusChecks
     */
    public function testNoDiffAutomaticallyAproves(
        $existingStatus,
        $existingDiffStatus,
        $change,
        $expectedStatus,
        $expectedDiffStatus
    ) {
        $observer = new CheckpointObserver();

        $snapshot = factory(Snapshot::class)->create();

        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'a diff',
            'status' => $existingStatus,
            'diff_status' => $existingDiffStatus,
        ]);
        $checkpoint->syncOriginal();
        $checkpoint->diff_status = $change;

        $observer->updating($checkpoint);

        $this->assertEquals($expectedStatus, $checkpoint->status);
        $this->assertEquals($expectedDiffStatus, $checkpoint->diff_status);
    }

    public function diffStatusChecks()
    {
        return[
            // Approve identical diffs.
            [
                Checkpoint::STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_IDENTICAL,
                Checkpoint::STATUS_APPROVED,
                Checkpoint::DIFF_STATUS_IDENTICAL,
            ],
            // But don't do anything for differences, it's up to the user..
            [
                Checkpoint::STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                Checkpoint::STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_DIFFERENT,
            ],
        ];
    }
}
