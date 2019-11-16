<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Models\Checkpoint;
use Appocular\Assessor\Models\Snapshot;
use Appocular\Assessor\SlugGenerator;
use Appocular\Assessor\TestCase;
use Laravel\Lumen\Testing\DatabaseMigrations;

class SnapshotControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();
        // Set up a frontend token.
        \putenv('FRONTEND_TOKEN=FrontendToken');
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
        return ["Authorization" => 'Bearer FrontendToken'];
    }

    /**
     * Test that access control works.
     */
    public function testAccessControl(): void
    {
        $snapshot = \factory(Snapshot::class)->create();

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(401);
    }

    public function testGettingSnapshot(): void
    {
        $baseline = \factory(Snapshot::class)->create();
        $snapshot = \factory(Snapshot::class)->create([
            'baseline' => $baseline->id,
        ]);

        $this->get('snapshot/' . $snapshot->id, $this->headers());
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => \route('snapshot.show', ['id' => $snapshot->id]),
            'id' => $snapshot->id,
            'checkpoints' => [],
            'status' => 'unknown',
            'processing_status' => 'pending',
            'run_status' => 'pending',
            'baseline_url' => \route('snapshot.show', ['id' => $baseline->id]),
        ]);

        $checkpoints = [
            $snapshot->checkpoints()->save(\factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(\factory(Checkpoint::class)->make()),
        ];
        $checkpointsJson = \array_map(static function ($checkpoint): array {
            return [
                'self' => \route('checkpoint.show', ['id' => $checkpoint->id]),
                'name' => $checkpoint->name,
                'image_url' => $checkpoint->image_url,
                'baseline_url' => $checkpoint->baseline_url,
                'diff_url' => $checkpoint->diff_url,
                'image_status' => 'available',
                'approval_status' => 'unknown',
                'diff_status' => 'unknown',
                'actions' => [
                    'approve' => \route('checkpoint.approve', ['id' => $checkpoint->id]),
                    'reject' => \route('checkpoint.reject', ['id' => $checkpoint->id]),
                    'ignore' => \route('checkpoint.ignore', ['id' => $checkpoint->id]),
                ],
                'slug' => SlugGenerator::toSlug($checkpoint->name),
                'meta' => null,
            ];
        }, $checkpoints);

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => \route('snapshot.show', ['id' => $snapshot->id]),
            'id' => $snapshot->id,
            'checkpoints' => $checkpointsJson,
            'status' => 'unknown',
            'processing_status' => 'pending',
            'run_status' => 'pending',
            'baseline_url' => \route('snapshot.show', ['id' => $baseline->id]),
        ]);
    }
}
