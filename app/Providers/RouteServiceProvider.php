<?php

namespace Uccello\ModuleDesigner\Providers;

use App\Providers\RouteServiceProvider as DefaultRouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;

/**
 * Route Service Provider
 */
class RouteServiceProvider extends DefaultRouteServiceProvider
{
  /**
   * @inheritDoc
   */
  public function boot()
  {
    parent::boot();
  }

  /**
   * @inheritDoc
   */
  public function map()
  {
    parent::map();

    $this->mapModuleDesignerRoutes();
  }

    /**
     * Define "module_designer" routes for the application.
     *
     * @return void
     */
    protected function mapModuleDesignerRoutes()
    {
        Route::middleware('web', 'auth')
             ->namespace('Uccello\ModuleDesigner\Http\Controllers')
             ->group(__DIR__.'/../Http/routes.php');
    }
}