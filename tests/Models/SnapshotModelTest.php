<?php

namespace Models;

use Appocular\Assessor\Batch;
use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class SnapshotModelTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that baseline finding triggers checkpoint baselining for all
     * checkpoints.
     */
    public function testNewBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'new image',
            'baseline_url' => null,
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

    /**
     * Test that baselining finds an existing checkpoint.
     */
    public function testAcceptedBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
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

    /**
     * Test that baselining ignores a checkpoint that is an approved deletion.
     */
    public function testDeletedBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a deleted image',
            'image_url' => '',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
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

    /**
     * Test that baselining ignores rejected images without an approved
     * ancestor (a new image that wasn't approved), but uses the approved
     * ancestor of rejected checkpoints (the change wasn't approved in the
     * previous snapshot).
     */
    public function testRejectedBaselining()
    {
        // If the rejected checkpoint has no ancestor, it should be ignored.
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_url' => 'a rejected image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
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
            'image_url' => 'lala',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_url' => 'lala',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'a rejected image',
            'image_url' => '',
            'approval_status' => Checkpoint::APPROVAL_STATUS_REJECTED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
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

    /**
     * Check that baselining handles ignored checkpoints like rejected
     * checkpoints.
     */
    public function testIgnoredBaselining()
    {
        // If the ignored checkpoint has no ancestor, it should be completely ignored.
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an ignored image',
            'image_url' => '',
            'approval_status' => Checkpoint::APPROVAL_STATUS_IGNORED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
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
            'image_url' => 'lala',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        $baseline = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an existing image',
            'approval_status' => Checkpoint::APPROVAL_STATUS_APPROVED,
        ]);

        factory(Checkpoint::class)->create([
            'snapshot_id' => $baseline->id,
            'name' => 'an ignored image',
            'image_url' => '',
            'approval_status' => Checkpoint::APPROVAL_STATUS_IGNORED,
        ]);

        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'an existing image',
            'baseline_url' => null,
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

    /**
     * Tests that the status reflect the combined statuses of the checkpoints,
     * and that run status is set depending on whether there's any unknown
     * checkpoints left or any active batches.
     */
    public function testStatusUpdate()
    {
        Queue::fake();
        $checkpoints = [];
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints[] = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'one',
            'image_status' => Checkpoint::IMAGE_STATUS_AVAILABLE,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);
        $checkpoints[] = factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'name' => 'one',
            'image_status' => Checkpoint::IMAGE_STATUS_AVAILABLE,
            'approval_status' => Checkpoint::APPROVAL_STATUS_UNKNOWN,
        ]);

        // Processing status is waiting if there's unknown checkpoints.
        $snapshot->updateStatus();
        $this->assertEquals(Snapshot::STATUS_UNKNOWN, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_PENDING, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_DONE, $snapshot->run_status);

        // Should stay at unknown as long as there's unknown checkpoints.
        $checkpoints[0]->approval_status = Checkpoint::APPROVAL_STATUS_APPROVED;
        $checkpoints[0]->save();

        $snapshot->updateStatus();
        $this->assertEquals(Snapshot::STATUS_UNKNOWN, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_PENDING, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_DONE, $snapshot->run_status);

        // Should pass when all checkpoints are either approved or ignored
        $checkpoints[1]->approval_status = Checkpoint::APPROVAL_STATUS_IGNORED;
        $checkpoints[1]->save();

        $snapshot->updateStatus();
        $this->assertEquals(Snapshot::STATUS_PASSED, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_DONE, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_DONE, $snapshot->run_status);

        // Run status should be pending as long as there's active batches.
        $batch = factory(Batch::class)->create(['snapshot_id' => $snapshot->id]);

        $snapshot->updateStatus();
        $this->assertEquals(Snapshot::STATUS_UNKNOWN, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_DONE, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_PENDING, $snapshot->run_status);

        $batch->delete();

        // Or pending Checkpoints.
        $checkpoints[1]->image_status = Checkpoint::IMAGE_STATUS_PENDING;
        $checkpoints[1]->save();

        $this->assertEquals(Snapshot::STATUS_UNKNOWN, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_DONE, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_PENDING, $snapshot->run_status);

        // Should fail if there's rejected checkpoints.
        $checkpoints[1]->image_status = Checkpoint::IMAGE_STATUS_AVAILABLE;
        $checkpoints[1]->approval_status = Checkpoint::APPROVAL_STATUS_REJECTED;
        $checkpoints[1]->save();

        $snapshot->updateStatus();
        $this->assertEquals(Snapshot::STATUS_FAILED, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_DONE, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_DONE, $snapshot->run_status);

        // Rejected trumps unknown.
        $checkpoints[0]->approval_status = Checkpoint::APPROVAL_STATUS_UNKNOWN;
        $checkpoints[0]->save();

        $snapshot->updateStatus();
        $this->assertEquals(Snapshot::STATUS_FAILED, $snapshot->status);
        $this->assertEquals(Snapshot::PROCESSING_STATUS_PENDING, $snapshot->processing_status);
        $this->assertEquals(Snapshot::RUN_STATUS_DONE, $snapshot->run_status);
    }
}
