<?php

namespace Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Listeners\QueueCheckpointsBaselining;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Queue;

class QueueCheckpointsBaseliningTest extends \TestCase
{
    use DatabaseMigrations;

    public function testTriggering()
    {
        Queue::fake();
        $snapshot = factory(Snapshot::class)->create();

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));
        // Shouldn't fire QueueCheckpointBaselining if there's no baseline.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $snapshot->baseline = '';
        $snapshot->syncChanges();

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));
        // Shouldn't fire QueueCheckpointBaselining when baseline is empty.
        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $baseline = factory(Snapshot::class)->create();
        $snapshot->setBaseline($baseline);

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));
        // Should fire when baseline has been changed to a valid baseline.
        Queue::assertPushed(QueueCheckpointBaselining::class);

        $baseline = factory(Snapshot::class)->create();
        $snapshot->setBaseline($baseline);

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));
        // Should fire again when baseline has been changed.
        Queue::assertPushed(QueueCheckpointBaselining::class);
    }
}
