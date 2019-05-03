<?php

namespace Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\ImageStore;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Prophecy\Argument;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
class CheckpointTest extends \TestCase
{
    use DatabaseMigrations;

    public function testGettingCheckpoint()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $this->get('checkpoint/' . $checkpoints[0]->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals(['id' => $checkpoints[0]->id, 'name' => $checkpoints[0]->name, 'image_sha' => $checkpoints[0]->image_sha]);

        $this->get('checkpoint/' . $checkpoints[1]->id);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals(['id' => $checkpoints[1]->id, 'name' => $checkpoints[1]->name, 'image_sha' => $checkpoints[1]->image_sha]);

        $this->get('checkpoint/random');
        $this->assertResponseStatus(404);
    }

    public function testGettingImage()
    {
        $snapshot = factory(Snapshot::class)->create();
        $checkpoints = [
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
            $snapshot->checkpoints()->save(factory(Checkpoint::class)->make()),
        ];

        $imageStore = $this->prophesize(ImageStore::class);
        $imageStore->get($checkpoints[0]->image_sha)->willReturn('<png data>');
        $imageStore->get($checkpoints[1]->image_sha)->willReturn(null);

        $this->app->instance(ImageStore::class, $imageStore->reveal());

        $this->get('checkpoint/' . $checkpoints[0]->id . '/image');
        $this->assertResponseStatus(200);
        $this->assertEquals('image/png', $this->response->headers->get('Content-Type'));
        $this->assertEquals('<png data>', $this->response->getContent());

        $this->get('checkpoint/' . $checkpoints[1]->id . '/image');
        $this->assertResponseStatus(404);
    }
}
