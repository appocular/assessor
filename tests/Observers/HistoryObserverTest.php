<?php

namespace Observers;

use Appocular\Assessor\History;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Observers\HistoryObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class HistoryObserverTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that a SnapshotBaselining job is queued when history is saved.
     */
    public function testHistorySaveTriggersSnapshotBaselining()
    {
        Queue::fake();

        $snapshot = factory(Snapshot::class)->create();
        $history = factory(History::class)->create([
            'snapshot_id' => $snapshot->id,
            'history' => "banana\npear\napple\n",
        ]);

        $observer = new HistoryObserver();

        $observer->saved($history);

        Queue::assertPushed(SnapshotBaselining::class);
    }
}