<?php

// phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__),
))->bootstrap();

if (env('REPORT_COVERAGE', false)) {
    $coverage = new SebastianBergmann\CodeCoverage\CodeCoverage();

    $coverage->filter()->addDirectoryToWhitelist(__DIR__ . '/../app');

    $coverage->start('api-test');

    // Save code coverage when the request ends.
    $handler = static function () use ($coverage): void {
        $coverage->stop();

        $writer = new SebastianBergmann\CodeCoverage\Report\PHP();
        $writer->process($coverage, __DIR__ . '/../coverage/api.' . uniqid() . '.cov');
    };

    register_shutdown_function($handler);
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    realpath(__DIR__ . '/../'),
);

$app->withFacades();

$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    Appocular\Assessor\Exceptions\Handler::class,
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Appocular\Assessor\Console\Kernel::class,
);

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

$app->middleware([
    Appocular\Assessor\Http\Middleware\CorsMiddleware::class,
    Fideloper\Proxy\TrustProxies::class,
]);

$app->routeMiddleware([
    'auth' => Appocular\Assessor\Http\Middleware\Authenticate::class,
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/

$app->register(Appocular\Assessor\Providers\AuthServiceProvider::class);
$app->register(Appocular\Assessor\Providers\AppServiceProvider::class);
$app->register(Appocular\Clients\KeeperServiceProvider::class);
$app->register(Appocular\Clients\DifferServiceProvider::class);
$app->register(Webpatser\Uuid\UuidServiceProvider::class);
$app->register(Fideloper\Proxy\TrustedProxyServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->router->group([
    'namespace' => 'Appocular\Assessor\Http\Controllers',
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
], static function ($router): void {
    require __DIR__ . '/../routes/web.php';
});

return $app;
