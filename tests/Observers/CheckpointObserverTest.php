<?php

declare(strict_types=1);

namespace Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Assessor\Observers\CheckpointObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class CheckpointObserverTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that diffs gets reset when image or baseline is updated. Should
     * also reset status.
     */
    public function testUpdatingResetsDiffWhenImageOrBaselineChanges(): void
    {
        $observer = new CheckpointObserver();

        $snapshot = \factory(Snapshot::class)->create();

        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'a diff',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
            'diff_status' => Checkpoint::DIFF_STATUS_DIFFERENT,
        ]);
        $checkpoint->syncOriginal();
        $checkpoint->image_url = 'new image';

        $observer->updating($checkpoint);

        $this->assertEquals(null, $checkpoint->diff_url);
        $this->assertEquals(Checkpoint::DIFF_STATUS_UNKNOWN, $checkpoint->diff_status);
        $this->assertEquals(Checkpoint::APPROVAL_STATUS_UNKNOWN, $checkpoint->approval_status);
    }

    /**
     * Test that snapshot status gets updated when checkpoint status changes.
     */
    public function testUpdatedTriggersSnapshotStatusUpdateOnCheckpointStatusChange(): void
    {
        $observer = new CheckpointObserver();

        $snapshot = \factory(Snapshot::class)->create(['status' => Snapshot::STATUS_UNKNOWN]);

        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'a diff',
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
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
        $checkpoint->approval_status = Checkpoint::APPROVAL_STATUS_APPROVED;
        $observer->updated($checkpoint);

        $checkpoint->syncOriginal();
        $checkpoint->approval_status = Checkpoint::APPROVAL_STATUS_REJECTED;

        $observer->updated($checkpoint);
    }

    /**
     * Test that new or deleted checkpoints gets a "different" diff status.
     */
    public function testNewOrDeletedCheckpointsGetsDifferentDiffStatus(): void
    {
        Queue::fake();

        $observer = new CheckpointObserver();

        $snapshot = \factory(Snapshot::class)->create(['status' => Snapshot::STATUS_UNKNOWN]);

        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => null,
            'diff_url' => null,
            'image_status' => Checkpoint::IMAGE_STATUS_AVAILABLE,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_UNKNOWN,
        ]);

        $checkpoint->syncOriginal();
        $this->assertFalse($checkpoint->hasDiff());
        $checkpoint->baseline_url = '';
        $observer->updating($checkpoint);
        $this->assertTrue($checkpoint->hasDiff());
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoint->diff_status);

        $checkpoint->diff_status = Checkpoint::DIFF_STATUS_UNKNOWN;
        $checkpoint->image_url = '';
        $checkpoint->image_status = Checkpoint::IMAGE_STATUS_EXPECTED;
        $checkpoint->syncOriginal();
        $this->assertFalse($checkpoint->hasDiff());
        $checkpoint->baseline_url = 'baseline';
        $observer->updating($checkpoint);
        $this->assertTrue($checkpoint->hasDiff());
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoint->diff_status);
    }

    /**
     * Test that diff requests are submitted when image or baseline changed.
     */
    public function testUpdatedTriggersDiffWhenImageOrBaselineChanges(): void
    {
        Queue::fake();

        $observer = new CheckpointObserver();

        $snapshot = \factory(Snapshot::class)->create(['status' => Snapshot::STATUS_UNKNOWN]);

        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => null,
            'diff_url' => null,
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
        string $existingApprovalStatus,
        string $existingDiffStatus,
        string $change,
        string $expectedApprovalStatus,
        string $expectedDiffStatus
    ): void {
        $observer = new CheckpointObserver();

        $snapshot = \factory(Snapshot::class)->create();

        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'a diff',
            'approval_status' => $existingApprovalStatus,
            'diff_status' => $existingDiffStatus,
        ]);
        $checkpoint->syncOriginal();
        $checkpoint->diff_status = $change;

        $observer->updating($checkpoint);

        $this->assertEquals($expectedApprovalStatus, $checkpoint->approval_status);
        $this->assertEquals($expectedDiffStatus, $checkpoint->diff_status);
    }

    /**
     * @return array<array<string>>
     */
    public function diffStatusChecks(): array
    {
        return[
            // Approve identical diffs.
            [
                Checkpoint::APPROVAL_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_IDENTICAL,
                Checkpoint::APPROVAL_STATUS_APPROVED,
                Checkpoint::DIFF_STATUS_IDENTICAL,
            ],
            // But don't do anything for differences, it's up to the user..
            [
                Checkpoint::APPROVAL_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                Checkpoint::APPROVAL_STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_DIFFERENT,
            ],
        ];
    }

    /**
     * Test that checkpoint goes from pending/expected to available when they get
     * an image.
     */
    public function testStatusChangeOnGettingAImage(): void
    {
        $observer = new CheckpointObserver();

        $snapshot = \factory(Snapshot::class)->create();

        $checkpoint1 = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => '',
            'image_status' => Checkpoint::IMAGE_STATUS_PENDING,
        ]);
        $checkpoint1->syncOriginal();
        $checkpoint1->image_url = 'banana';

        $observer->updating($checkpoint1);

        $this->assertEquals(Checkpoint::IMAGE_STATUS_AVAILABLE, $checkpoint1->image_status);

        $checkpoint2 = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => '',
            'image_status' => Checkpoint::IMAGE_STATUS_EXPECTED,
        ]);
        $checkpoint2->syncOriginal();
        $checkpoint2->image_url = 'banana';

        $observer->updating($checkpoint2);

        $this->assertEquals(Checkpoint::IMAGE_STATUS_AVAILABLE, $checkpoint2->image_status);
    }
}
