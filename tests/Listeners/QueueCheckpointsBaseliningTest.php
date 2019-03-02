<?php

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Listeners\QueueCheckpointsBaselining;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;

class QueueCheckpointsBaseliningTest extends TestCase
{
    use DatabaseMigrations;

    public function testTriggering()
    {
        Queue::fake();
        $snapshot = factory(Snapshot::class)->create();

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));

        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $snapshot->baseline = '';
        $snapshot->syncChanges();

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));

        Queue::assertNotPushed(QueueCheckpointBaselining::class);

        $baseline = factory(Snapshot::class)->create();
        $snapshot->setBaseline($baseline);

        (new QueueCheckpointsBaselining())->handle(new SnapshotUpdated($snapshot));

        Queue::assertPushed(QueueCheckpointBaselining::class);
    }
}
