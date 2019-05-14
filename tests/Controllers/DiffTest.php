<?php

namespace Controllers;

use Appocular\Assessor\Jobs\UpdateDiff;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Queue;

class DiffTest extends \TestCase
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
            'image_kid' => 'image_kid',
            'baseline_kid' => 'baseline_kid',
            'diff_kid' => 'first diff',
            'different' => true,
        ];

        $this->json('POST', '/diff', $data);
        $this->assertResponseStatus(200);

        Queue::assertPushed(UpdateDiff::class);
    }
}
