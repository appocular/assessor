<?php

declare(strict_types=1);

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class AppSmokeTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample(): void
    {
        $this->get('/');

        $this->assertEquals(
            $this->app->version(),
            $this->response->getContent(),
        );
    }
}
