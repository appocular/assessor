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
            'id' => $snapshot->id,
            'checkpoints' => [],
        ]);

        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];
        $checkpointsJson = array_map(function ($checkpoint) {
            return [
                'id' => $checkpoint->id,
                'name' => $checkpoint->name,
                'image_sha' => $checkpoint->image_sha,
                'baseline_sha' => $checkpoint->baseline_sha,
                'diff_sha' => $checkpoint->diff_sha,
            ];
        }, $checkpoints);

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $snapshot->id,
            'checkpoints' => $checkpointsJson,
        ]);
    }
}
