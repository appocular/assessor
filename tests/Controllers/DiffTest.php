<?php

namespace Controllers;

use Appocular\Assessor\Jobs\UpdateDiff;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;

class DiffTest extends ControllerTestBase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    /**
     * Test that posting diff fires DiffSubmitted event.
     */
    public function testPostingDiff()
    {
        Queue::fake();

        $data = [
            'image_url' => 'image_url',
            'baseline_url' => 'baseline_url',
            'diff_url' => 'first diff',
            'different' => true,
        ];

        $this->json('POST', '/diff', $data);
        $this->assertResponseStatus(200);

        Queue::assertPushed(UpdateDiff::class);
    }
}
