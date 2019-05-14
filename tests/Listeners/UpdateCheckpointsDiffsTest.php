<?php

namespace Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdated;
use Appocular\Assessor\Events\DiffSubmitted;
use Appocular\Assessor\Listeners\UpdateCheckpointsDiffs;
use Appocular\Assessor\Snapshot;
use Event;
use Laravel\Lumen\Testing\DatabaseMigrations;

class UpdateCheckpointsDiffsTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that DiffSubmitted events updates checkpoints.
     */
    public function testUpdating()
    {
        Event::fake([
            CheckpointUpdated::class,
        ]);

        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $event = new DiffSubmitted($checkpoints[0]->image_sha, $checkpoints[0]->baseline_sha, 'diff', 1);
        (new UpdateCheckpointsDiffs())->handle($event);
        $checkpoints[0]->refresh();

        $this->assertEquals('diff', $checkpoints[0]->diff_sha);
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoints[0]->diff_status);

        // Check that the other checkpoint wasn't changed.
        $this->assertEquals($checkpoints[1]->getAttributes(), $checkpoints[1]->fresh()->getAttributes());
    }

    /**
     * Test that approved/rejected checkpoints doesn't get updated.
     */
    public function testNotUpdatingProcessed()
    {
        Event::fake([
            CheckpointUpdated::class,
        ]);

        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        foreach ([Checkpoint::STATUS_APPROVED, Checkpoint::STATUS_REJECTED, Checkpoint::STATUS_IGNORED] as $status) {
            $checkpoints[] = $snapshot->checkpoints()->save(factory(Checkpoint::class)->make([
                'image_sha' => $checkpoints[0]->image_sha,
                'baseline_sha' => $checkpoints[0]->baseline_sha,
                'diff_sha' => 'original diff',
                'status' => $status,
            ]));
        }

        $event = new DiffSubmitted($checkpoints[0]->image_sha, $checkpoints[0]->baseline_sha, 'diff', 1);
        (new UpdateCheckpointsDiffs())->handle($event);
        $checkpoints[0]->refresh();

        $this->assertEquals('diff', $checkpoints[0]->diff_sha);
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoints[0]->diff_status);

        // Check that the approved/rejected/ignored checkpoints wasn't changed.
        $this->assertEquals(
            $checkpoints[1]->getAttributes(),
            $checkpoints[1]->fresh()->getAttributes(),
            'Approved checkpoint was updated.'
        );
        $this->assertEquals(
            $checkpoints[2]->getAttributes(),
            $checkpoints[2]->fresh()->getAttributes(),
            'Rejected checkpoint was updated.'
        );
        $this->assertEquals(
            $checkpoints[3]->getAttributes(),
            $checkpoints[3]->fresh()->getAttributes(),
            'Ignored checkpoint was updated.'
        );
    }
}