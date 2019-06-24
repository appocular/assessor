<?php

namespace Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

class SnapshotTest extends ControllerTestBase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    public function testGettingSnapshot()
    {
        $snapshot = factory(Snapshot::class)->create();

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => route('snapshot.show', ['id' => $snapshot->id]),
            'checkpoints' => [],
            'status' => 'unknown',
            'run_status' => 'running',
        ]);

        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];
        $checkpointsJson = array_map(function ($checkpoint) {
            return [
                'self' => route('checkpoint.show', ['id' => $checkpoint->id]),
                'name' => $checkpoint->name,
                'image_url' => $checkpoint->image_url,
                'baseline_url' => $checkpoint->baseline_url,
                'diff_url' => $checkpoint->diff_url,
                'status' => 'unknown',
                'diff_status' => 'unknown',
            ];
        }, $checkpoints);

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'self' => route('snapshot.show', ['id' => $snapshot->id]),
            'checkpoints' => $checkpointsJson,
            'status' => 'unknown',
            'run_status' => 'running',
        ]);
    }
}
