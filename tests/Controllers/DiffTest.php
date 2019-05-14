<?php

namespace Controllers;

use Appocular\Assessor\Events\DiffSubmitted;
use Appocular\Assessor\Snapshot;
use Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;

class DiffTest extends \TestCase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    /**
     * Test that posting diff fires DiffSubmitted event.
     */
    public function testPostingDiff()
    {
        Event::fake([
            DiffSubmitted::class,
        ]);

        $data = [
            'image_kid' => 'image_kid',
            'baseline_kid' => 'baseline_kid',
            'diff_kid' => 'first diff',
            'different' => true,
        ];

        $this->json('POST', '/diff', $data);
        print_r($this->response->getContent());
        $this->assertResponseStatus(200);

        Event::assertDispatched(DiffSubmitted::class, function ($e) {
            return true;
        });
    }
}
