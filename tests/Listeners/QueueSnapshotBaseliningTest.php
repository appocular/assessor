<?php

use Appocular\Assessor\Events\SnapshotCreated;
use Appocular\Assessor\Jobs\SnapshotBaselining;
use Appocular\Assessor\Listeners\QueueSnapshotBaselining;
use Laravel\Lumen\Testing\DatabaseMigrations;

class QueueSnapshotBaseliningTest extends TestCase
{
    use DatabaseMigrations;

    public function testTriggerBaselining()
    {
        Queue::fake();
        $baseline = factory(Appocular\Assessor\Snapshot::class)->create();
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $snapshot->history()->create(['history' => "banana\n" . $baseline->id . "\napple\n"]);

        $listener = new QueueSnapshotBaselining();
        $listener->handle(new SnapshotCreated($snapshot));

        Queue::assertPushed(SnapshotBaselining::class);
    }

    public function testDontTriggerBaselining()
    {
        Queue::fake();
        $baseline = factory(Appocular\Assessor\Snapshot::class)->create();
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $listener = new QueueSnapshotBaselining();
        $listener->handle(new SnapshotCreated($snapshot));

        Queue::assertNotPushed(SnapshotBaselining::class);
    }
}
