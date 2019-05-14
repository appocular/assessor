<?php

namespace Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdating;
use Appocular\Assessor\Listeners\ResetCheckpointDiff;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;

class ResetCheckpointDiffTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that diffs gets reset when image or baseline is updated.
     */
    public function testResetting()
    {
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
        // Sync changes so change is marked dirty.
        $checkpoint->syncChanges();

        $listener = new ResetCheckpointDiff();
        $listener->handle(new CheckpointUpdating($checkpoint));
        $this->assertEquals(null, $checkpoint->diff_sha);
        $this->assertEquals(Checkpoint::DIFF_STATUS_UNKNOWN, $checkpoint->diff_status);
    }

    /**
     * Test that checkpoints processed by the user isn't reset.
     *
     * @dataProvider processedStatuses
     */
    public function testNotResetting($status)
    {
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
        // Sync changes so change is marked dirty.
        $checkpoint->syncChanges();

        $listener = new ResetCheckpointDiff();
        $listener->handle(new CheckpointUpdating($checkpoint));
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
}
