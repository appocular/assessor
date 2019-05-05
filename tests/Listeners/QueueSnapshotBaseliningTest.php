<?php

namespace Listeners;

use Appocular\Assessor\Events\SnapshotCreated;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Listeners\QueueSnapshotBaselining;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Queue;

class QueueSnapshotBaseliningTest extends \TestCase
{
    use DatabaseMigrations;

    public function testTriggerBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        $snapshot = factory(Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\n" . $baseline->id . "\napple\n"]);

        $listener = new QueueSnapshotBaselining();
        $listener->handle(new SnapshotCreated($snapshot));

        \Queue::assertPushed(SnapshotBaselining::class);
    }

    public function testDontTriggerBaselining()
    {
        Queue::fake();
        $baseline = factory(Snapshot::class)->create();
        $snapshot = factory(Snapshot::class)->create();

        $listener = new QueueSnapshotBaselining();
        $listener->handle(new SnapshotCreated($snapshot));

        Queue::assertNotPushed(SnapshotBaselining::class);
    }
}
