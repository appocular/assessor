<?php

namespace Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Observers\SnapshotObserver;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Queue;

class SnapshotObserverTest extends \TestCase
{
    use DatabaseMigrations;

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

        \Queue::assertPushed(SnapshotBaselining::class);
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
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'baseline_sha' => 'deadbeef']);
        factory(Checkpoint::class)->create(['snapshot_id' => $snapshot->id, 'baseline_sha' => 'deadbeef']);
        factory(Checkpoint::class)->create([
            'snapshot_id' => $snapshot->id,
            'image_sha' => null,
            'baseline_sha' => 'deadbeef',
        ]);

        $observer->updated($snapshot);

        // Shouldn't change when snapshot baseline didn't change.
        $this->assertEquals('deadbeef', $snapshot->checkpoints()->first()->baseline_sha);
        // And imageless checkpoints should be left alone.
        $this->assertCount(3, $snapshot->checkpoints()->get());

        // Set new baseline.
        $baseline = factory(Snapshot::class)->create();
        $snapshot->baseline = $baseline->id;

        $observer->updated($snapshot);

        $snapshot->refresh();
        $this->assertEquals(null, $snapshot->checkpoints()->first()->baseline_sha);
        $this->assertCount(2, $snapshot->checkpoints()->get());
    }

    /**
     * Test that checkpoint baselining job is queued when snapshot baseline changes.
     */
    public function testUpdateTriggersCheckpointBaseliningWhenSnopshotBaselineChanges()
    {
        $observer = new SnapshotObserver();

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
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should fire when baseline has been changed to a valid baseline.
        Queue::assertPushed(QueueCheckpointBaselining::class);

        $baseline = factory(Snapshot::class)->create();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should fire again when baseline has been changed.
        Queue::assertPushed(QueueCheckpointBaselining::class);
    }

}
