<?php

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Listeners\TriggerFindingCheckpointBaseline;
use Appocular\Assessor\Snapshot;

class TriggerFindingCheckpointBaselineTest extends TestCase
{
    public function testTriggering()
    {
        $snapshot = $this->prophesize(Snapshot::class);
        $snapshot->wasChanged('baseline')->willReturn(false);
        $snapshot->triggerCheckpointBaselining()->shouldNotBeCalled();

        (new TriggerFindingCheckpointBaseline())->handle(new SnapshotUpdated($snapshot->reveal()));

        $snapshot->wasChanged('baseline')->willReturn(true);
        $snapshot->getBaseline()->willReturn(null);
        $snapshot->triggerCheckpointBaselining()->shouldNotBeCalled();

        (new TriggerFindingCheckpointBaseline())->handle(new SnapshotUpdated($snapshot->reveal()));

        $snapshot->wasChanged('baseline')->willReturn(true);
        $snapshot->getBaseline()->willReturn(new Snapshot());
        $snapshot->triggerCheckpointBaselining()->shouldBeCalled();

        (new TriggerFindingCheckpointBaseline())->handle(new SnapshotUpdated($snapshot->reveal()));
    }
}
