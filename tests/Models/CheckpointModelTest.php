<?php

namespace Models;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;

class CheckpointModelTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * @dataProvider statusProvider
     */
    public function testBulkDiffUpdatesSetsStatusesCorrectly(
        $name,
        $existingImageStatus,
        $existingDiffStatus,
        $existingApprovalStatus,
        $existingDiffUrl,
        $difference,
        $expectedImageStatus,
        $expectedDiffStatus,
        $expectedApprovalStatus,
        $expectedDiffUrl
    ) {
        $snapshot = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => $name,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => $existingDiffUrl,
            'image_status' => $existingImageStatus,
            'diff_status' => $existingDiffStatus,
            'approval_status' => $existingApprovalStatus,
        ]);

        Checkpoint::updateDiffs('image', 'baseline', 'new_diff', $difference);

        $this->seeInDatabase('checkpoints', [
            'name' => $name,
            'image_status' => $expectedImageStatus,
            'diff_status' => $expectedDiffStatus,
            'approval_status' => $expectedApprovalStatus,
            'diff_url' => $expectedDiffUrl,
        ]);
    }

    public function statusProvider()
    {
        return [
            // Updating with a difference shouldn't change status.
            [
                'unknown',
                Checkpoint::IMAGE_STATUS_AVAILABLE,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                Checkpoint::APPROVAL_STATUS_UNKNOWN,
                null,
                true,
                Checkpoint::IMAGE_STATUS_AVAILABLE,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                Checkpoint::APPROVAL_STATUS_UNKNOWN,
                'new_diff',
            ],
            // Updating with an identical diff should auto-approve.
            [
                'unknown',
                Checkpoint::IMAGE_STATUS_AVAILABLE,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                Checkpoint::APPROVAL_STATUS_UNKNOWN,
                null,
                false,
                Checkpoint::IMAGE_STATUS_AVAILABLE,
                Checkpoint::DIFF_STATUS_IDENTICAL,
                Checkpoint::APPROVAL_STATUS_APPROVED,
                'new_diff',
            ],
            // Check that existing diffs doesn't get overwritten.
            [
                'unknown',
                Checkpoint::IMAGE_STATUS_AVAILABLE,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                Checkpoint::APPROVAL_STATUS_APPROVED,
                'diff',
                true,
                Checkpoint::IMAGE_STATUS_AVAILABLE,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                Checkpoint::APPROVAL_STATUS_APPROVED,
                'diff',
            ],
        ];
    }

    public function testApproving()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        $checkpoint->approve();
        $this->seeInDatabase('checkpoints', [
            'id' => $checkpoint->id,
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED
        ]);
    }

    public function testRejecting()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        $checkpoint->reject();
        $this->seeInDatabase('checkpoints', [
            'id' => $checkpoint->id,
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED
        ]);
    }

    public function testIgnoring()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        $checkpoint->ignore();
        $this->seeInDatabase('checkpoints', [
            'id' => $checkpoint->id,
            'approval_status' => Checkpoint::APPROVAL_STATUS_IGNORED
        ]);
    }

    public function testResetting()
    {
        $snapshot = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'approved',
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => 'stuff',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
            'diff_status' => Checkpoint::DIFF_STATUS_IDENTICAL,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'pending',
            'image_url' => '',
            'image_status' => Checkpoint::IMAGE_STATUS_PENDING,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'expected',
            'image_url' => '',
            'image_status' => Checkpoint::IMAGE_STATUS_EXPECTED,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        Checkpoint::resetBaselines($snapshot->id);

        $this->seeInDatabase('checkpoints', [
            'name' => 'approved',
            'baseline_url' => null,
            'diff_url' => null,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_UNKNOWN,
        ]);
        $this->seeInDatabase('checkpoints', [
            'name' => 'pending',
            'image_status' => Checkpoint::IMAGE_STATUS_PENDING,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        $this->missingFromDatabase('checkpoints', [
            'name' => 'expected',
        ]);
    }
}
