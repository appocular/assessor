<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Jobs\UpdateDiff;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Testing\DatabaseMigrations;

class DiffControllerTest extends ControllerTestBase
{
    use DatabaseMigrations;

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

        $this->json('POST', '/diff', $data, $this->sharedAuthHeaders());
        $this->assertResponseStatus(200);

        Queue::assertPushed(UpdateDiff::class);
    }
}
