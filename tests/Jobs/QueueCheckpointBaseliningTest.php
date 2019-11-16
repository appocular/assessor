<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Models\Snapshot;
use Appocular\Assessor\TestCase;
use Illuminate\Contracts\Queue\Job;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Prophecy\Argument;

class QueueCheckpointBaseliningTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Test that checkpoint baselining is triggered when the baseline is
     * changed.
     */
    public function testTriggering(): void
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
}
