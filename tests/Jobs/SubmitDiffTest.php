<?php

namespace Jobs;

use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubmitDiffTest extends \TestCase
{
    /**
     * Test that diffs are submitted to Differ.
     */
    public function testSubmittingDiff()
    {
        $differ = $this->prophesize(Differ::class);
        $differ->submit('image_kid', 'baseline_kid')->shouldBeCalled();
        $job = new SubmitDiff('image_kid', 'baseline_kid');
        $job->handle($differ->reveal());
    }

    /**
     * Test errors are logged.
     */
    public function testErrorLogging()
    {
        Log::shouldReceive('info')
            ->once();

        Log::shouldReceive('error')
            ->once()
            ->with('Error submitting diff image image_kid, baseline baseline_kid: bad stuff')
            ->andReturn();

        $differ = $this->prophesize(Differ::class);
        $differ->submit('image_kid', 'baseline_kid')->willThrow(new RuntimeException('bad stuff'))->shouldBeCalled();
        $job = new SubmitDiff('image_kid', 'baseline_kid');
        $job->handle($differ->reveal());
    }
}
