<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Appocular\Assessor\ImageStore;
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
        $jsonImages = array_map(function ($checkpoint) {
            return ['name' => $checkpoint->name, 'image_sha' => $checkpoint->image_sha];
        }, $checkpoints);

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $snapshot->id,
            'checkpoints' => $jsonImages,
        ]);
    }

    public function testCaseInsensitivity()
    {
        $snapshot = factory(Appocular\Assessor\Snapshot::class)->create();

        $this->get('snapshot/' . $snapshot->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'id' => $snapshot->id,
            'checkpoints' => [],
        ]);

        $this->get('snapshot/' . strtoupper($snapshot->id));
        $this->assertResponseStatus(200);

        $this->get('snapshot/' . strtolower($snapshot->id));
        $this->assertResponseStatus(200);
    }

}
