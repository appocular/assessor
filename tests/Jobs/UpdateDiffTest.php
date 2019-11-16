<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Appocular\Assessor\TestCase;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;

class UpdateDiffTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Suppress model events so we can test in isolation.
     */
    public function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    /**
     * Test that job updates checkpoints.
     */
    public function testUpdatingDiff(): void
    {
        $snapshot = \factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(\factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(\factory(Checkpoint::class)->make()),
        ];

        $job = new UpdateDiff($checkpoints[0]->image_url, $checkpoints[0]->baseline_url, 'diff', true);
        $job->handle();

        $checkpoints[0]->refresh();

        $this->assertEquals('diff', $checkpoints[0]->diff_url);
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoints[0]->diff_status);

        // Check that the other checkpoint wasn't changed.
        $this->assertEquals($checkpoints[1]->getAttributes(), $checkpoints[1]->fresh()->getAttributes());
    }

    /**
     * Test that approved/rejected checkpoints doesn't get updated.
     */
    public function testNotUpdatingProcessed(): void
    {
        $snapshot = \factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(\factory(Checkpoint::class)->make()),
        ];

        $statuses = [
            Checkpoint::APPROVAL_STATUS_APPROVED,
            Checkpoint::APPROVAL_STATUS_REJECTED,
            Checkpoint::APPROVAL_STATUS_IGNORED,
        ];

        foreach ($statuses as $approval_status) {
            $checkpoints[] = $snapshot->checkpoints()->save(\factory(Checkpoint::class)->make([
                'image_url' => $checkpoints[0]->image_url,
                'baseline_url' => $checkpoints[0]->baseline_url,
                'diff_url' => 'original diff',
                'approval_status' => $approval_status,
                'meta' => null,
            ]));
        }

        $job = new UpdateDiff($checkpoints[0]->image_url, $checkpoints[0]->baseline_url, 'diff', true);
        $job->handle();

        $checkpoints[0]->refresh();

        $this->assertEquals('diff', $checkpoints[0]->diff_url);
        $this->assertEquals(Checkpoint::DIFF_STATUS_DIFFERENT, $checkpoints[0]->diff_status);

        // Check that the approved/rejected/ignored checkpoints wasn't changed.
        $this->assertEquals(
            $checkpoints[1]->getAttributes(),
            $checkpoints[1]->fresh()->getAttributes(),
            'Approved checkpoint was updated.',
        );
        $this->assertEquals(
            $checkpoints[2]->getAttributes(),
            $checkpoints[2]->fresh()->getAttributes(),
            'Rejected checkpoint was updated.',
        );
        $this->assertEquals(
            $checkpoints[3]->getAttributes(),
            $checkpoints[3]->fresh()->getAttributes(),
            'Ignored checkpoint was updated.',
        );
    }
}
