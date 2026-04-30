<?php

namespace Delwarhossaindev\ActivityLog;

use Delwarhossaindev\ActivityLog\Http\Middleware\LogApiActivity;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/activitylog.php' => config_path('activitylog.php'),
            ], 'activitylog-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'activitylog-migrations');
        }
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('log.activity', LogApiActivity::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/activitylog.php',
            'activitylog'
        );

        $this->app->singleton(ActivityLogger::class, function () {
            return new ActivityLogger();
        });
    }
}
