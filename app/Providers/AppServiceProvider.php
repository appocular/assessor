<?php

namespace Appocular\Assessor\Providers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Observers\CheckpointObserver;
use Appocular\Assessor\Observers\SnapshotObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Snapshot::observe(SnapshotObserver::class);
        Checkpoint::observe(CheckpointObserver::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
