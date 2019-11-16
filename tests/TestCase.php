<?php

declare(strict_types=1);

namespace Appocular\Assessor;

use Laravel\Lumen\Application;
use Laravel\Lumen\Testing\TestCase as LumenTestCase;

abstract class TestCase extends LumenTestCase
{
    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication(): Application
    {
        // Seems there is a memory leak somewhere when testing, so bump limit.
        \ini_set('memory_limit', '256M');

        return require __DIR__ . '/../bootstrap/app.php';
    }
}
