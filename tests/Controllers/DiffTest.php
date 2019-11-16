<?php

declare(strict_types=1);

namespace Controllers;

use Appocular\Assessor\Jobs\UpdateDiff;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class DiffTest extends \TestCase
{
    use DatabaseMigrations;

    public function setUp(): void
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
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ["Authorization" => 'Bearer SharedToken'];
    }


    /**
     * Test that access control works.
     */
    public function testAccessControl(): void
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
    public function testPostingDiff(): void
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
