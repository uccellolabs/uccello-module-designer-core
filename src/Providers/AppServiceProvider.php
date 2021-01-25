<?php

namespace Uccello\ModuleDesignerCore\Providers;

use Illuminate\Support\ServiceProvider;
use Uccello\ModuleDesignerCore\Console\Commands\MakeModuleCommand;

/**
 * App Service Provider
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
            ]);
        }
    }

    public function register()
    {
        //
    }
}
