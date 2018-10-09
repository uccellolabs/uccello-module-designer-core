<?php

use Illuminate\Database\Migrations\Migration;
use Uccello\Core\Database\Migrations\Traits\TablePrefixTrait;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Domain;

class CreateModuleDesignerStructure extends Migration
{
    use TablePrefixTrait;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = $this->createModule();
        $this->activateModuleOnDomain($module);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Module::where('name', 'module_designer')->forceDelete();
    }

    protected function createModule()
    {
        $module = new  Module();
        $module->name = 'module_designer';
        $module->icon = 'brush';
        $module->model_class = null;
        $module->data = ["package" => "module_designer", "admin" => true, "link" => "index"];
        $module->save();

        return $module;
    }

    protected function activateModuleOnDomain($module)
    {
        $domain = Domain::first();
        $domain->modules()->attach($module);
    }
}
