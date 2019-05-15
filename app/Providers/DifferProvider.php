<?php

namespace Appocular\Assessor\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Appocular\Assessor\Differ;
use RuntimeException;

class DifferProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Differ::class, function ($app) {
            $uri = $app['config']->get('app.differ_base_uri');
            if (empty($uri)) {
                throw new RuntimeException('No base uri for Differ.');
            }
            $client = new Client(['base_uri' => $uri]);

            return new Differ($client);
        });
    }
}
