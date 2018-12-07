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
        Module::where('name', 'module-designer')->forceDelete();
    }

    protected function createModule()
    {
        $module = new  Module();
        $module->name = 'module-designer';
        $module->icon = 'brush';
        $module->model_class = null;
        $module->data = ["package" => "module-designer", "admin" => true, "route" => "uccello.index"];
        $module->save();

        return $module;
    }

    protected function activateModuleOnDomain($module)
    {
        $domain = Domain::first();
        $domain->modules()->attach($module);
    }
}
