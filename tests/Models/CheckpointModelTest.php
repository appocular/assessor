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
        $existingStatus,
        $existingDiffStatus,
        $existingDiffUrl,
        $difference,
        $expectedStatus,
        $expectedDiffStatus,
        $expectedDiffUrl
    ) {
        $snapshot = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => $name,
            'image_url' => 'image',
            'baseline_url' => 'baseline',
            'diff_url' => $existingDiffUrl,
            'status' => $existingStatus,
            'diff_status' => $existingDiffStatus,
        ]);

        Checkpoint::updateDiffs('image', 'baseline', 'new_diff', $difference);

        $this->seeInDatabase('checkpoints', [
            'name' => $name,
            'status' => $expectedStatus,
            'diff_status' => $expectedDiffStatus,
            'diff_url' => $expectedDiffUrl,
        ]);
    }

    public function statusProvider()
    {
        return [
            // Updating with a difference shouldn't change status.
            [
                'unknown',
                Checkpoint::STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                null,
                true,
                Checkpoint::STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                'new_diff',
            ],
            // Updating with an identical diff should auto-approve.
            [
                'unknown',
                Checkpoint::STATUS_UNKNOWN,
                Checkpoint::DIFF_STATUS_UNKNOWN,
                null,
                false,
                Checkpoint::STATUS_APPROVED,
                Checkpoint::DIFF_STATUS_IDENTICAL,
                'new_diff',
            ],
            // Check that existing diffs doesn't get overwritten.
            [
                'unknown',
                Checkpoint::STATUS_APPROVED,
                Checkpoint::DIFF_STATUS_DIFFERENT,
                'diff',
                true,
                Checkpoint::STATUS_APPROVED,
                Checkpoint::DIFF_STATUS_DIFFERENT,
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
            'status' => Checkpoint::STATUS_UNKNOWN,
        ]);

        $checkpoint->approve();
        $this->seeInDatabase('checkpoints', ['id' => $checkpoint->id, 'status' => Checkpoint::STATUS_APPROVED]);
    }

    public function testRejecting()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'image',
            'status' => Checkpoint::STATUS_UNKNOWN,
        ]);

        $checkpoint->reject();
        $this->seeInDatabase('checkpoints', ['id' => $checkpoint->id, 'status' => Checkpoint::STATUS_REJECTED]);
    }

    public function testIgnoring()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'image',
            'status' => Checkpoint::STATUS_UNKNOWN,
        ]);

        $checkpoint->ignore();
        $this->seeInDatabase('checkpoints', ['id' => $checkpoint->id, 'status' => Checkpoint::STATUS_IGNORED]);
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
            'status' => Checkpoint::STATUS_APPROVED,
            'diff_status' => Checkpoint::DIFF_STATUS_IDENTICAL,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'pending',
            'image_url' => '',
            'status' => Checkpoint::STATUS_PENDING,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'expected',
            'image_url' => '',
            'status' => Checkpoint::STATUS_EXPECTED,
        ]);

        Checkpoint::resetBaselines($snapshot->id);

        $this->seeInDatabase('checkpoints', [
            'name' => 'approved',
            'baseline_url' => null,
            'diff_url' => null,
            'status' => Checkpoint::STATUS_UNKNOWN,
            'diff_status' => Checkpoint::DIFF_STATUS_UNKNOWN,
        ]);
        $this->seeInDatabase('checkpoints', [
            'name' => 'pending',
            'status' => Checkpoint::STATUS_PENDING,
        ]);

        $this->missingFromDatabase('checkpoints', [
            'name' => 'expected',
        ]);
    }
}
