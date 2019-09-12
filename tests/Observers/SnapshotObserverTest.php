<?php

namespace Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Observers\SnapshotObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class SnapshotObserverTest extends \TestCase
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
     * Test that creating snapshot with history triggers baseline finding job.
     */
    public function testCreateTriggerBaselining()
    {
        $observer = new SnapshotObserver();

        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        $snapshot = factory(Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\n" . $baseline->id . "\napple\n"]);

        $observer->created($snapshot);

        Queue::assertPushed(SnapshotBaselining::class);
    }

    /**
     * Test that creating snapshots without history doesn't trigger baseline
     * finding.
     */
    public function testCreateWithoutHistoryDontTriggerBaselining()
    {
        $observer = new SnapshotObserver();

        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        $snapshot = factory(Snapshot::class)->create();

        $observer->created($snapshot);

        Queue::assertNotPushed(SnapshotBaselining::class);
    }

    /**
     * Test that checkpoint baselines get reset when snapshots baseline
     * changes.
     */
    public function testUpdateResetsCheckpointBaselinesWhenSnapshotBaselineChanges()
    {
        $observer = new SnapshotObserver();

        $baseline = factory(Snapshot::class)->create();
        $snapshot = factory(Snapshot::class)->create(['baseline' => $baseline->id]);
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'baseline_url' => 'deadbeef']);
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'baseline_url' => 'deadbeef']);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_url' => null,
            'baseline_url' => 'deadbeef',
        ]);

        $observer->updated($snapshot);

        // Shouldn't change when snapshot baseline didn't change.
        $this->assertEquals('deadbeef', $snapshot->checkpoints()->first()->baseline_url);
        // And imageless checkpoints should be left alone.
        $this->assertCount(3, $snapshot->checkpoints()->get());

        // Set new baseline.
        $baseline = factory(Snapshot::class)->create();
        $snapshot->baseline = $baseline->id;

        $observer->updated($snapshot);

        $snapshot->refresh();
        $this->assertEquals(null, $snapshot->checkpoints()->first()->baseline_url);
        // There should only be two checkpoints, as the new image (the one
        // with null URL) should have been deleted.
        $this->assertCount(2, $snapshot->checkpoints()->get());
    }

    /**
     * Test that checkpoint baselining job is queued when snapshot baseline
     * changes and the new baseline is done.
     */
    public function testUpdateTriggersCheckpointBaseliningWhenSnopshotBaselineChanges()
    {
        Queue::fake();
        $snapshot = factory(Snapshot::class)->create();

        $observer = new SnapshotObserver();
        $observer->updated($snapshot);

        // Shouldn't fire QueueCheckpointBaselining if there's no baseline.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $snapshot->baseline = '';
        $snapshot->syncChanges();

        $observer->updated($snapshot);
        // Shouldn't fire QueueCheckpointBaselining when baseline is empty.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $baseline = factory(Snapshot::class)->create();
        $baseline->run_status = Snapshot::RUN_STATUS_PENDING;
        $baseline->save();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should not fire when baseline isn't done.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $baseline = factory(Snapshot::class)->create();
        $baseline->run_status = Snapshot::RUN_STATUS_DONE;
        $baseline->save();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should fire when baseline has been changed to a valid done baseline.
        Queue::assertPushedTimes(QueueCheckpointBaselining::class, 1);

        $baseline = factory(Snapshot::class)->create();
        $baseline->run_status = Snapshot::RUN_STATUS_DONE;
        $baseline->save();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should fire again when baseline has been changed.
        Queue::assertPushedTimes(QueueCheckpointBaselining::class, 2);
    }

    /**
     * Test that descendant snapshots gets re-baselined when the snapshot
     * status changes to done.
     */
    public function testStatusChangeTriggersDescendantBaselining()
    {
        Queue::fake();
        $snapshot = factory(Snapshot::class)->create([
            'status' => Snapshot::STATUS_UNKNOWN,
            'run_status' => Snapshot::RUN_STATUS_PENDING,
        ]);
        $descendant = factory(Snapshot::class)->create(['baseline' => $snapshot->id]);

        $observer = new SnapshotObserver();
        $snapshot->status = Snapshot::STATUS_PASSED;
        $observer->updated($snapshot);

        // Shouldn't fire any baselining job while not done.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $snapshot->run_status = Snapshot::RUN_STATUS_DONE;
        $observer->updated($snapshot);

        // Should trigger descendant re-baselining when done.
        Queue::assertPushed(QueueCheckpointBaselining::class);
    }
}
