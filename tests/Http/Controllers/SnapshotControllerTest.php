<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Models\Checkpoint;
use Appocular\Assessor\Models\Snapshot;
use Appocular\Assessor\SlugGenerator;
use Laravel\Lumen\Testing\DatabaseMigrations;

class SnapshotControllerTest extends ControllerTestBase
{
    use DatabaseMigrations;

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

        $this->get('snapshot/' . $snapshot->id, $this->frontendAuthHeaders());
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
