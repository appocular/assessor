<?php

declare(strict_types=1);

namespace Jobs;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;

class FindCheckpointBaselineTest extends \TestCase
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
     * Test that the job handles deleted checkpoints without throwing up.
     */
    public function testDeletedCheckpointHandling(): void
    {
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => 'test',
            'name' => 'new image',
            'baseline_url' => null,
        ]);
        $this->seeInDatabase('checkpoints', ['name' => 'new image']);

        $job = new FindCheckpointBaseline($checkpoint);

        $checkpoint->delete();

        $job->handle();
        $this->missingFromDatabase('checkpoints', ['name' => 'new image']);
    }

    /**
     * Check that the baseline_url remains empty when no parent exists.
     */
    public function testNewCheckpoint(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_url' => 'not related',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_url);
    }

    /**
     * Test that an approved ancestor gets used as baseline.
     */
    public function testAcceptedBaseline(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_url' => 'not related',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('approved in baseline', $checkpoint->baseline_url);
    }

    /**
     * Test that rejected and ignored ancestors are not used.
     */
    public function testRejectedOrIgnoredBaseline(): void
    {
        // If there's no approved baseline, baseline_url should be ''.
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'rejected in baseline',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_url);

        // If there's an approved ancestor baseline, use its image_url.
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline parent',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $baseline = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'ignored in baseline',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
        ]);

        $baseline = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'rejected in baseline',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('approved in baseline parent', $checkpoint->baseline_url);
    }

    /**
     * Test that if there's no approved ancestor, it's handled as a new
     * checkpoint.
     */
    public function testDeletedBaseline(): void
    {
        // If there's no approved baseline, baseline_url should be ''.
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => '',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_url);

        // If there's an approved ancestor baseline, use its image_url.
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline parent',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $baseline = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => '',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_url);
    }

    /**
     * Test that an existing baseline gets replaced.
     */
    public function testRebaselining(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_url' => 'not related',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => 'old baseline',
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('approved in baseline', $checkpoint->baseline_url);
    }

    /**
     * Test that baselines are handled properly.
     */
    public function testBaseliningWithMeta(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'with meta',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
            'meta' => ['s' => 't'],
        ]);

        \factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'without meta',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = \factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
            'meta' => ['s' => 't'],
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('with meta', $checkpoint->baseline_url);

        $checkpoint2 = \factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint2);
        $job->handle();
        $checkpoint2->refresh();

        $this->assertEquals('without meta', $checkpoint2->baseline_url);
    }
}
