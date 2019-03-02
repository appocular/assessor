<?php

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\CheckpointUpdated;
use Appocular\Assessor\Listeners\UpdateSnapshotStatus;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class UpdateSnapshotStatusTest extends TestCase
{
    public function testSnapshotUpdate()
    {
        $checkpoint = $this->prophesize(Checkpoint::class);
        $snapshot = $this->prophesize(Snapshot::class);
        $snapshot->updateStatus()->shouldNotBeCalled();
        $checkpoint->wasChanged('status')->willReturn(false);
        $checkpoint->getAttribute('snapshot')->shouldNotBeCalled();

        (new UpdateSnapshotStatus())->handle(new CheckpointUpdated($checkpoint->reveal()));

        $checkpoint = $this->prophesize(Checkpoint::class);
        $checkpoint->wasChanged('status')->willReturn(true);
        $snapshot = $this->prophesize(Snapshot::class);
        $snapshot->updateStatus()->shouldBeCalled();
        $checkpoint->getAttribute('snapshot')->willReturn($snapshot->reveal());

        (new UpdateSnapshotStatus())->handle(new CheckpointUpdated($checkpoint->reveal()));
    }
}
