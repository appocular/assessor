<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\TestCase;

abstract class ControllerTestBase extends TestCase
{

    /**
     * Return authorization headers for request.
     *
     * Note that the Illuminate\Auth\TokenGuard is only constructed on the
     * first request in a test, and the Authorization headert thus "sticks
     * around" for the subsequent requests, rendering passing the header to
     * them pointless.
     *
     * @return array<string, string>
     */
    public function authHeader(string $token): array
    {
        return ["Authorization" => 'Bearer ' . $token];
    }

    /**
     * Return front-end authorization headers for request.
     *
     * @return array<string, string>
     */
    public function frontendAuthHeaders(): array
    {
        \putenv('FRONTEND_TOKEN=FrontendToken');

        return $this->authHeader('FrontendToken');
    }

    /**
     * Return shared authorization headers for request.
     *
     * @return array<string, string>
     */
    public function sharedAuthHeaders(): array
    {
        \putenv('SHARED_TOKEN=SharedToken');

        return $this->authHeader('SharedToken');
    }
}
