<?php

namespace Jobs;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class FindCheckpointBaselineTest extends \TestCase
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
     * Test that the job handles deleted checkpoints without throwing up.
     */
    public function testDeletedCheckpointHandling()
    {
        $checkpoint = factory(Checkpoint::class)->create([
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
    public function testNewCheckpoint()
    {
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_url' => 'not related',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
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
    public function testAcceptedBaseline()
    {
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_url' => 'not related',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
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
    public function testRejectedOrIgnoredBaseline()
    {
        // If there's no approved baseline, baseline_url should be ''.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'rejected in baseline',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_url);

        // If there's an approved ancestor baseline, use its image_url.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline parent',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'ignored in baseline',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'rejected in baseline',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
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
    public function testDeletedBaseline()
    {
        // If there's no approved baseline, baseline_url should be ''.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => '',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_url);

        // If there's an approved ancestor baseline, use its image_url.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline parent',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => '',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
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
    public function testRebaselining()
    {
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_url' => 'approved in baseline',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_url' => 'not related',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => 'old baseline',
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('approved in baseline', $checkpoint->baseline_url);
    }
}
