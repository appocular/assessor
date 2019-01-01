<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Prophecy\Argument;

class SnapshotTest extends TestCase
{
    use DatabaseMigrations;

    public function testGettingSnapshot()
    {
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $snapshot->id,
            'checkpoints' => [],
        ]);

        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Appocular\Assessor\Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Appocular\Assessor\Checkpoint::class)->make()),
        ];
        $checkpointsJson = array_map(function ($checkpoint) {
            return ['id' => $checkpoint->id, 'name' => $checkpoint->name, 'image_sha' => $checkpoint->image_sha];
        }, $checkpoints);

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $snapshot->id,
            'checkpoints' => $checkpointsJson,
        ]);
    }
}
