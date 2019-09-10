<?php

namespace Jobs;

use Appocular\Assessor\Jobs\QueueCheckpointBaselining;
use Appocular\Assessor\Snapshot;
use Illuminate\Contracts\Queue\Job;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Prophecy\Argument;

class QueueCheckpointBaseliningTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that checkpoint baselining is triggered when the baseline is
     * changed.
     */
    public function testTriggering()
    {
        $jobContract = $this->prophesize(Job::class);
        $jobContract->release(Argument::any())->shouldNotBeCalled();

        $snapshot = $this->prophesize(Snapshot::class);
        $snapshot->refresh()->shouldBeCalled();
        $snapshot->getBaseline()->willReturn(null);
        $snapshot->triggerCheckpointBaselining()->shouldNotBeCalled();

        $job = new QueueCheckpointBaselining($snapshot->reveal());
        $job->setJob($jobContract->reveal());
        $job->handle();

        $baseline = new Snapshot();
        $baseline->run_status = Snapshot::RUN_STATUS_DONE;

        $snapshot->getBaseline()->willReturn($baseline);
        $snapshot->triggerCheckpointBaselining()->shouldBeCalled();

        $job = new QueueCheckpointBaselining($snapshot->reveal());
        $job->setJob($jobContract->reveal());
        $job->handle();
    }

    /**
     * Test that the job re-queues itself if the baseline is still pending.
     */
    public function testDelayingWhenBaselineStillPending()
    {
        $jobContract = $this->prophesize(Job::class);
        $jobContract->release(5)->willReturn($jobContract)->shouldBeCalled();

        $baseline = new Snapshot();
        $baseline->run_status = Snapshot::RUN_STATUS_PENDING;

        $snapshot = $this->prophesize(Snapshot::class);
        $snapshot->refresh()->willReturn($snapshot);
        $snapshot->getBaseline()->willReturn($baseline);
        $snapshot->triggerCheckpointBaselining()->shouldNotBeCalled();

        $job = new QueueCheckpointBaselining($snapshot->reveal());
        $job->setJob($jobContract->reveal());
        $job->handle();
    }
}
