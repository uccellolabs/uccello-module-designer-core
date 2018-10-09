<?php

namespace Uccello\ModuleDesigner\Http\Controllers;

use Illuminate\Http\Request;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;
use Schema;

class DesignerController extends Controller
{
    public function install(Domain $domain, Module $module, Request $request)
    {
        // Check user permissions
        $this->middleware('uccello.permissions:admin');

        return 'install';
    }

    public function create(Domain $domain, Module $module, Request $request)
    {
        // Check user permissions
        $this->middleware('uccello.permissions:admin');

        return 'create';
    }

    public function update(Domain $domain, Module $module, Request $request)
    {
        // Check user permissions
        $this->middleware('uccello.permissions:admin');

        return 'update';
    }

    public function settings(Domain $domain, Module $module, Request $request)
    {
        // Check user permissions
        $this->middleware('uccello.permissions:admin');

        return 'settings';
    }
}