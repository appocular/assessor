<?php

namespace Observers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\GitHubStatusUpdate;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Observers\SnapshotObserver;
use Appocular\Assessor\Repo;
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
    public function setUp(): void
    {
        parent::setUp();
        Event::fake();
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

        // As we're testing the observer outside the normal event cycle, we
        // have to manually do some things that's normally done by Eloquent.
        // We use syncOriginal() to tell Eloquent what the original loaded
        // state was, so that SnapshotObserver can see what changed with
        // isDirty.
        $snapshot->syncOriginal();

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

        $snapshot->syncOriginal();
        $snapshot->baseline = '';

        $observer->updated($snapshot);
        // Shouldn't fire QueueCheckpointBaselining when baseline is empty.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $snapshot->syncOriginal();
        $baseline = factory(Snapshot::class)->create();
        $baseline->run_status = Snapshot::RUN_STATUS_PENDING;
        $baseline->save();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should not fire when baseline isn't done.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $snapshot->syncOriginal();
        $baseline = factory(Snapshot::class)->create();
        $baseline->run_status = Snapshot::RUN_STATUS_DONE;
        $baseline->save();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should fire when baseline has been changed to a valid done baseline.
        Queue::assertPushed(QueueCheckpointBaselining::class, 1);

        $snapshot->syncOriginal();
        $baseline = factory(Snapshot::class)->create();
        $baseline->run_status = Snapshot::RUN_STATUS_DONE;
        $baseline->save();
        $snapshot->setBaseline($baseline);

        $observer->updated($snapshot);
        // Should fire again when baseline has been changed.
        Queue::assertPushed(QueueCheckpointBaselining::class, 2);
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

        $snapshot->syncOriginal();
        $snapshot->run_status = Snapshot::RUN_STATUS_DONE;
        $observer->updated($snapshot);

        // Should trigger descendant re-baselining when done.
        Queue::assertPushed(QueueCheckpointBaselining::class);
    }

    /**
     * Test that a GitHub status change job i started if the repo is from
     * GitHub and status/run_status changes.
     */
    public function testStatusChangeTriggersGitHubUpdate()
    {
        Queue::fake();
        $observer = new SnapshotObserver();
        $snapshot = factory(Snapshot::class)->create([
            'status' => Snapshot::STATUS_UNKNOWN,
            'run_status' => Snapshot::RUN_STATUS_PENDING,
        ]);

        $snapshot->wasRecentlyCreated = false;
        // Make it dirty.
        $snapshot->status = Snapshot::STATUS_PASSED;
        $snapshot->run_status = Snapshot::RUN_STATUS_DONE;

        // Shouldn't trigger on non-github repos.
        $repo = factory(Repo::class)->create();
        $snapshot->repo()->associate($repo);
        $observer->saved($snapshot);

        Queue::assertNotPushed(GitHubStatusUpdate::class);

        // Should trigger on ssh URIs.
        $repo = factory(Repo::class)->create(['uri' => 'git@github.com:appocular/assessor']);
        $snapshot->repo()->associate($repo);

        $observer->saved($snapshot);
        Queue::assertPushed(GitHubStatusUpdate::class);

        // Should trigger on https URIs.
        $repo = factory(Repo::class)->create(['uri' => 'https://github.com/appocular/assessor']);
        $snapshot->repo()->associate($repo);

        $observer->saved($snapshot);
        Queue::assertPushed(GitHubStatusUpdate::class, 2);

        // Shouldn't trigger on non-state changes.
        $snapshot->syncOriginal();
        $snapshot->baseline = 'test';

        $observer->saved($snapshot);
        Queue::assertPushed(GitHubStatusUpdate::class, 2);

        // Should trigger when the snapshot is created.
        $snapshot->syncOriginal();
        $snapshot->wasRecentlyCreated = true;

        $observer->saved($snapshot);
        Queue::assertPushed(GitHubStatusUpdate::class, 3);

        // Should trigger on status change.
        $snapshot->wasRecentlyCreated = false;
        $snapshot->syncOriginal();
        $snapshot->status = Snapshot::STATUS_FAILED;

        $observer->saved($snapshot);
        Queue::assertPushed(GitHubStatusUpdate::class, 4);

        // Should trigger on run status change.
        $snapshot->syncOriginal();
        $snapshot->run_status = Snapshot::RUN_STATUS_PENDING;

        $observer->saved($snapshot);
        Queue::assertPushed(GitHubStatusUpdate::class, 5);

    }
}
