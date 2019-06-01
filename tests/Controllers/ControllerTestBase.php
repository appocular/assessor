<?php

namespace Controllers;

use Appocular\Clients\Contracts\Differ;
use Appocular\Clients\Contracts\Keeper;

/**
 * Controller test base. Ensures that the external services are prophesized.
 */
class ControllerTestBase extends \TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->keeperProphecy = $this->prophesize(Keeper::class);
        $this->app->instance(Keeper::class, $this->keeperProphecy->reveal());
        $this->differProphecy = $this->prophesize(Differ::class);
        $this->app->instance(Differ::class, $this->differProphecy->reveal());
    }
}
