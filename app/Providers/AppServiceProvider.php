<?php

declare(strict_types=1);

namespace Appocular\Assessor\Providers;

use Appocular\Assessor\Http\Resources\CheckpointResource;
use Appocular\Assessor\Http\Resources\SnapshotResource;
use Appocular\Assessor\Models\Checkpoint;
use Appocular\Assessor\Models\History;
use Appocular\Assessor\Models\Snapshot;
use Appocular\Assessor\Observers\CheckpointObserver;
use Appocular\Assessor\Observers\HistoryObserver;
use Appocular\Assessor\Observers\SnapshotObserver;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Snapshot::observe(SnapshotObserver::class);
        Checkpoint::observe(CheckpointObserver::class);
        History::observe(HistoryObserver::class);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Don't use a wrapper on resource responses.
        SnapshotResource::withoutWrapping();
        CheckpointResource::withoutWrapping();

        $this->app->configure('assessor');

        $this->app->singleton(HttpClientInterface::class, static function (): HttpClientInterface {
            return HttpClient::create();
        });

        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if (\env('APP_LOG_QUERIES', false)) {
            // Ensure that the dispatcher has been created.
            $this->app['events'];
            $log = $this->app['log'];
            $this->app['db']->listen(static function ($sql) use ($log): void {
                $log->debug("*** SQL ***");
                $log->debug($sql->sql);
                $log->debug($sql->bindings);
                $log->debug($sql->time);
                $log->debug("**********");
            });
        }
    }
}
