<?php

namespace Controllers;

use Appocular\Assessor\ImageStore;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
class ImageTest extends \TestCase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    public function testGettingImage()
    {
        $imageStore = $this->prophesize(ImageStore::class);
        $imageStore->get('existing')->willReturn('<png data>');
        $imageStore->get('not-existing')->willReturn(null);

        $this->app->instance(ImageStore::class, $imageStore->reveal());

        $this->get('image/existing');
        $this->assertResponseStatus(200);
        $this->assertEquals('image/png', $this->response->headers->get('Content-Type'));
        $this->assertEquals('<png data>', $this->response->getContent());

        $this->get('image/not-existing');
        $this->assertResponseStatus(404);
    }
}
