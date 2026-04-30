<?php

namespace Delwarhossaindev\ActivityLog;

use Delwarhossaindev\ActivityLog\Http\Middleware\LogApiActivity;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * ActivityLogServiceProvider — The entry point Laravel uses to load this package.
 *
 * Laravel automatically discovers and registers this provider via the
 * "extra.laravel.providers" key in composer.json — no manual registration needed.
 *
 * This provider does two things:
 *
 *   register() — Bind classes into Laravel's service container so they can be
 *                resolved via app() or dependency injection anywhere in the app.
 *
 *   boot()     — Run setup tasks that depend on other services already being
 *                loaded (e.g. registering middleware, publishing files).
 */
class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * Boot — runs after all providers are registered.
     *
     * Here we:
     *   1. Register the 'log.activity' route middleware alias.
     *   2. Publish the config file and migration to the host application
     *      (only when running in the console, e.g. `php artisan vendor:publish`).
     */
    public function boot(): void
    {
        $this->registerMiddleware();

        // Publishing only makes sense from the CLI, not during web requests
        if ($this->app->runningInConsole()) {

            // php artisan vendor:publish --tag=activitylog-config
            $this->publishes([
                __DIR__ . '/../config/activitylog.php' => config_path('activitylog.php'),
            ], 'activitylog-config');

            // php artisan vendor:publish --tag=activitylog-migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'activitylog-migrations');
        }
    }

    /**
     * Register the 'log.activity' middleware alias with Laravel's router.
     *
     * This lets developers use the short alias in route files instead of
     * the full class name:
     *   Route::middleware('log.activity')->group(...);
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('log.activity', LogApiActivity::class);
    }

    /**
     * Register — runs first, before boot().
     *
     * Here we:
     *   1. Merge the package's default config with any published config in the app.
     *   2. Bind ActivityLogger as a singleton so the same instance is reused within
     *      a single request (avoids creating a new object on every activity() call).
     */
    public function register(): void
    {
        // Merge defaults: if the app hasn't published the config yet, the
        // package's own config/activitylog.php values are used automatically.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/activitylog.php',
            'activitylog'
        );

        // Singleton: one ActivityLogger instance per request lifecycle.
        // This is safe because reset() is called after every log() call,
        // so there is no leftover state between separate log entries.
        $this->app->singleton(ActivityLogger::class, function () {
            return new ActivityLogger();
        });
    }
}
