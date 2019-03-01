<?php

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\SnapshotUpdated;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Appocular\Assessor\Listeners\TriggerFindingCheckpointBaseline;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class TriggerFindingCheckpointBaselineTest extends TestCase
{
    use DatabaseMigrations;

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
