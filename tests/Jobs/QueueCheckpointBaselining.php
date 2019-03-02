<?php

use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;

class QueueCheckpointBaseliningTest extends TestCase
{
    use DatabaseMigrations;

    public function testTriggering()
    {
        $snapshot = $this->prophesize(Snapshot::class);
        $snapshot->wasChanged('baseline')->willReturn(false);
        $snapshot->triggerCheckpointBaselining()->shouldNotBeCalled();

        (new QueueCheckpointBaselining($snapshot))->handle();

        $snapshot->wasChanged('baseline')->willReturn(true);
        $snapshot->getBaseline()->willReturn(null);
        $snapshot->triggerCheckpointBaselining()->shouldNotBeCalled();

        (new QueueCheckpointBaselining($snapshot))->handle();

        $snapshot->wasChanged('baseline')->willReturn(true);
        $snapshot->getBaseline()->willReturn(new Snapshot());
        $snapshot->triggerCheckpointBaselining()->shouldBeCalled();

        (new QueueCheckpointBaselining($snapshot))->handle();
    }
}
