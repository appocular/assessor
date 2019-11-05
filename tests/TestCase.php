<?php

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
abstract class TestCase extends Laravel\Lumen\Testing\TestCase
{
    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        // Seems there is a memory leak somewhere when testing, so bump limit.
        ini_set('memory_limit', '256M');
        return require __DIR__.'/../bootstrap/app.php';
    }
}
