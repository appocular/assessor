<?php

namespace Controllers;

use Appocular\Assessor\Jobs\UpdateDiff;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;

class DiffTest extends ControllerTestBase
{
    use DatabaseMigrations;

    public function setUp() : void
    {
        parent::setUp();
        // Set up shared token.
        \putenv('SHARED_TOKEN=SharedToken');
    }

    /**
     * Return authorization headers for request.
     *
     * Note that the Illuminate\Auth\TokenGuard is only constructed on the
     * first request in a test, and the Authorization headert thus "sticks
     * around" for the subsequent requests, rendering passing the header to
     * them pointless.
     */
    public function headers()
    {
        return ["Authorization" => 'Bearer SharedToken'];
    }


    /**
     * Test that access control works.
     */
    public function testAccessControl()
    {
        Queue::fake();

        $data = [
            'image_url' => 'image_url',
            'baseline_url' => 'baseline_url',
            'diff_url' => 'first diff',
            'different' => true,
        ];

        $this->json('POST', '/diff', $data);
        $this->assertResponseStatus(401);

        Queue::assertNotPushed(UpdateDiff::class);
    }

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

        $this->json('POST', '/diff', $data, $this->headers());
        $this->assertResponseStatus(200);

        Queue::assertPushed(UpdateDiff::class);
    }
}
