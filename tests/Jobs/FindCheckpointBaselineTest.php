<?php

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class FindCheckpointBaselineTest extends TestCase
{
    use DatabaseMigrations;

    public function testDeletedCheckpointHandling()
    {
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => 'test',
            'name' => 'new image',
            'baseline_sha' => null,
        ]);
        $this->seeInDatabase('checkpoints', ['name' => 'new image']);

        $job = new FindCheckpointBaseline($checkpoint);

        $checkpoint->delete();

        $job->handle();
        $this->missingFromDatabase('checkpoints', ['name' => 'new image']);
    }

    public function testNewCheckpoint()
    {
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_sha' => 'not related',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_sha);
    }

    public function testAcceptedBaseline()
    {
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => 'approved in baseline',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an unrelated image',
            'image_sha' => 'not related',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('approved in baseline', $checkpoint->baseline_sha);
    }

    public function testRejectedOrIgnoredBaseline()
    {
        // If there's no approved baseline, baseline_sha should be ''.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => 'rejected in baseline',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_sha);

        // If there's an approved ancestor baseline, use its image_sha.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => 'approved in baseline parent',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => 'ignored in baseline',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => 'rejected in baseline',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('approved in baseline parent', $checkpoint->baseline_sha);
    }

    public function testDeletedBaseline()
    {
        // If there's no approved baseline, baseline_sha should be ''.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_sha);

        // If there's an approved ancestor baseline, use its image_sha.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => 'approved in baseline parent',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        $checkpoint = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $job = new FindCheckpointBaseline($checkpoint);
        $job->handle();
        $checkpoint->refresh();

        $this->assertEquals('', $checkpoint->baseline_sha);
    }

}
