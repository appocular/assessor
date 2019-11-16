<?php

declare(strict_types=1);

namespace Jobs;

use Appocular\Assessor\Jobs\SubmitDiff;
use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubmitDiffTest extends \TestCase
{
    /**
     * Test that diffs are submitted to Differ.
     */
    public function testSubmittingDiff(): void
    {
        $differ = $this->prophesize(Differ::class);
        $differ->submit('image_url', 'baseline_url')->shouldBeCalled();
        $job = new SubmitDiff('image_url', 'baseline_url');
        $job->handle($differ->reveal());
    }

    /**
     * Test errors are logged.
     */
    public function testErrorLogging(): void
    {
        Log::shouldReceive('info')
            ->once();

        Log::shouldReceive('error')
            ->once()
            ->with('Error submitting diff image image_url, baseline baseline_url: bad stuff')
            ->andReturn();

        $differ = $this->prophesize(Differ::class);
        $differ->submit('image_url', 'baseline_url')->willThrow(new RuntimeException('bad stuff'))->shouldBeCalled();
        $job = new SubmitDiff('image_url', 'baseline_url');
        $job->handle($differ->reveal());
    }
}
