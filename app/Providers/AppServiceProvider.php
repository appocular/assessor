<?php

namespace Appocular\Assessor\Providers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\History;
use Appocular\Assessor\Http\Resources\CheckpointResource;
use Appocular\Assessor\Http\Resources\SnapshotResource;
use Appocular\Assessor\Observers\CheckpointObserver;
use Appocular\Assessor\Observers\HistoryObserver;
use Appocular\Assessor\Observers\SnapshotObserver;
use Appocular\Assessor\Snapshot;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
        History::observe(HistoryObserver::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Don't use a wrapper on resource responses.
        SnapshotResource::withoutWrapping();
        CheckpointResource::withoutWrapping();

        $this->app->singleton(HttpClientInterface::class, function ($app) {
            return HttpClient::create();
        });
    }
}
