<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Appocular\Assessor\ImageStore;
use Prophecy\Argument;

class CommitTest extends TestCase
{
    use DatabaseMigrations;

    public function testIndex()
    {
        $commit = factory(Appocular\Assessor\Commit::class)->create();
        // $commit->images()->save(factory(Appocular\Assessor\Image::class)->make());
        // $commit->images()->save(factory(Appocular\Assessor\Image::class)->make());

        $this->get('commit/' . $commit->sha);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'sha' => $commit->sha,
            'images' => [],
        ]);

        $images = [
            $commit->images()->save(factory(Appocular\Assessor\Image::class)->make()),
            $commit->images()->save(factory(Appocular\Assessor\Image::class)->make()),
        ];
        $jsonImages = array_map(function ($image) {
            return ['name' => $image->name, 'image_sha' => $image->image_sha];
        }, $images);

        $this->get('commit/' . $commit->sha);
        $this->assertResponseStatus(200);
        $this->seeJsonEquals([
            'sha' => $commit->sha,
            'images' => $jsonImages,
        ]);

    }
}
