<?php

declare(strict_types=1);

namespace Jobs;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\SubmitImage;
use Appocular\Clients\Contracts\Keeper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use RuntimeException;

class SubmitImageTest extends \TestCase
{
    use DatabaseMigrations;

    /**
     * Test that images are submitted to Keeper.
     */
    public function testSubmittingImage(): void
    {
        // Disable jobs triggered by observers.
        Queue::fake();

        $keeper = $this->prophesize(Keeper::class);
        $keeper->store('image data')->willReturn('image url')->shouldBeCalled();
        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => 1,
            'image_url' => null,]);

        $job = new SubmitImage($checkpoint, \base64_encode('image data'));
        $job->handle($keeper->reveal());

        $checkpoint->refresh();
        $this->assertEquals('image url', $checkpoint->image_url);
    }

    /**
     * Test errors are logged.
     */
    public function testErrorLogging(): void
    {
        Queue::fake();

        Log::shouldReceive('info')
            ->once();

        $checkpoint = \factory(Checkpoint::class)->create([
            'snapshot_id' => 2,
            'image_url' => null,
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with(\sprintf('Error submitting image for checkpoint %s: bad stuff', $checkpoint->id))
            ->andReturn();

        $keeper = $this->prophesize(Keeper::class);
        $keeper->store('image data')->willThrow(new RuntimeException('bad stuff'))->shouldBeCalled();

        $job = new SubmitImage($checkpoint, \base64_encode('image data'));
        $this->expectException(RuntimeException::class);
        $job->handle($keeper->reveal());
    }
}
