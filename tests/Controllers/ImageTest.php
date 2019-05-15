<?php

namespace Controllers;

use Appocular\Clients\Contracts\Keeper;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\WithoutMiddleware;
use Prophecy\Argument;

class ImageTest extends \TestCase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    public function testGettingImage()
    {
        $keeper = $this->prophesize(Keeper::class);
        $keeper->get('existing')->willReturn('<png data>');
        $keeper->get('not-existing')->willReturn(null);

        $this->app->instance(Keeper::class, $keeper->reveal());

        $this->get('image/existing');
        $this->assertResponseStatus(200);
        $this->assertEquals('image/png', $this->response->headers->get('Content-Type'));
        $this->assertEquals('<png data>', $this->response->getContent());

        $this->get('image/not-existing');
        $this->assertResponseStatus(404);
    }
}
