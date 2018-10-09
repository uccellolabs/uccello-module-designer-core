<?php

namespace Uccello\ModuleDesigner\Providers;

use Illuminate\Support\ServiceProvider;
use Uccello\ModuleDesigner\Console\Commands\UccelloModuleCommand;

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
    $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'uccello');

    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

    // Commands
    if ($this->app->runningInConsole()) {
      $this->commands([
        UccelloModuleCommand::class,
      ]);
    }
  }

  public function register()
  {

  }
}