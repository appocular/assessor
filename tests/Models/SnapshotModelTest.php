<?php

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class SnapshotModelTest extends TestCase
{
    use DatabaseMigrations;

    public function testNewBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'new image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image', 'new image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);
    }

    public function testAcceptedBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);
    }

    public function testDeletedBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a deleted image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);
    }

    public function testRejectedBaselining()
    {
        // If the rejected checkpoint has no ancestor, it should be ignored.
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);

        // If the rejected checkpoint has an approved ancestor, it should be added.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_sha' => 'lala',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_sha' => 'lala',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image', 'a rejected image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);
    }

    public function testIgnoredBaselining()
    {
        // If the ignored checkpoint has no ancestor, it should be completely ignored.
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an ignored image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_IGNORED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);

        // If the ignored checkpoint has an approved ancestor, it should be added.
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an ignored image',
            'image_sha' => 'lala',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'status' => Checkpoint::STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an ignored image',
            'image_sha' => '',
            'status' => Checkpoint::STATUS_IGNORED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_sha' => null,
        ]);

        $snapshot->triggerCheckpointBaselining();

        $expectedCheckpoints = ['an existing image', 'an ignored image'];
        $expectedCheckpoints = array_flip($expectedCheckpoints);
        Queue::assertPushed(FindCheckpointBaseline::class, function ($job) use (&$expectedCheckpoints) {
            if (isset($expectedCheckpoints[$job->checkpoint->name])) {
                unset($expectedCheckpoints[$job->checkpoint->name]);
                return true;
            }
        });

        $this->assertCount(0, $expectedCheckpoints);
    }
}
