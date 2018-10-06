<?php

namespace Ogle\Assessor\Providers;

use Ogle\Assessor\ImageStore;
use Illuminate\Support\ServiceProvider;

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
            return new ImageStore();
        });
    }
}
