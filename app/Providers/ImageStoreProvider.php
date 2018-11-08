<?php

namespace Appocular\Assessor\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Appocular\Assessor\ImageStore;
use RuntimeException;

class ImageStoreProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ImageStore::class, function ($app) {
            $uri = $app['config']->get('app.image_store_base_uri');
            if (empty($uri)) {
                throw new RuntimeException('No base uri for Keeper.');
            }
            $client = new Client(['base_uri' => $uri]);

            return new ImageStore($client);
        });
    }
}
