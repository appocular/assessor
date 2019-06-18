<?php

namespace Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

class CheckpointTest extends ControllerTestBase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    public function testGettingCheckpoint()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $checkpoints[0]->id,
            'name' => $checkpoints[0]->name,
            'image_url' => $checkpoints[0]->image_url,
            'baseline_url' => $checkpoints[0]->baseline_url,
            'diff_url' => $checkpoints[0]->diff_url,
            'status' => 'unknown',
            'diff_status' => 'unknown',
        ]);

        $this->get('checkpoint/' . $checkpoints[1]->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $checkpoints[1]->id,
            'name' => $checkpoints[1]->name,
            'image_url' => $checkpoints[1]->image_url,
            'baseline_url' => $checkpoints[1]->baseline_url,
            'diff_url' => $checkpoints[1]->diff_url,
            'status' => 'unknown',
            'diff_status' => 'unknown',
        ]);

        $this->get('checkpoint/random');
        $this->assertResponseStatus(404);
    }
}
