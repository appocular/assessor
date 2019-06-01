<?php

namespace Jobs;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\UpdateDiff;
use Appocular\Assessor\Snapshot;
use Event;
use Laravel\Lumen\Testing\DatabaseMigrations;

class UpdateDiffTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Suppress model events so we can test in isolation.
     */
    public function setUp()
    {
        parent::setUp();
        Event::fake();
    }

    /**
     * Test that job updates checkpoints.
     */
    public function testUpdatingDiff()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $job = new UpdateDiff($checkpoints[0]->image_sha, $checkpoints[0]->baseline_sha, 'diff', 1);
        $job->handle();

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

        $job = new UpdateDiff($checkpoints[0]->image_sha, $checkpoints[0]->baseline_sha, 'diff', 1);
        $job->handle();

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
