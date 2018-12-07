<?php

namespace Uccello\ModuleDesigner\Providers;

use Illuminate\Support\ServiceProvider;
use Uccello\ModuleDesigner\Console\Commands\MakeModuleCommand;

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
    // Views
    $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'module-designer');

    // Translations
    $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'module-designer');

    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    // Publish assets
    $this->publishes([
      __DIR__ . '/../../public' => public_path('vendor/uccello/module-designer'),
    ], 'assets'); // CSS

    // Commands
    if ($this->app->runningInConsole()) {
      $this->commands([
        MakeModuleCommand::class,
      ]);
    }
  }

  public function register()
  {

  }
}