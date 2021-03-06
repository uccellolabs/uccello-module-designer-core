<?php

namespace Uccello\ModuleDesignerCore\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Tab;
use Uccello\Core\Models\Block;
use Uccello\Core\Models\Field;
use Uccello\Core\Models\Filter;
use Uccello\Core\Models\Relatedlist;
use Uccello\Core\Models\Domain;
use Uccello\ModuleDesignerCore\Models\DesignedModule;

class ModuleImport
{
    /**
     * The structure of the module.
     *
     * @var \StdClass
     */
    protected $structure;

    /**
     * Module directory file path
     *
     * @var string
     */
    protected $filePath;

    /**
     * Command implementation to be able to display message in the console
     *
     * @var \Illuminate\Console\Command|Uccello\ModuleDesignerCore\Console\Commands\MakeModuleCommand
     */
    protected $command;

    /**
     * Fields to delete in database
     *
     * @var array
     */
    protected $fieldsToDelete = [ ];

    /**
     * Constructor
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @return void
     */
    /**
     * Undocumented function
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \Illuminate\Console\Command|Uccello\ModuleDesignerCore\Console\Commands\MakeModuleCommand|null $output
     */
    public function __construct($command = null)
    {
        $this->command = $command;
    }

    /**
     * Generate all files and database structure to install the new module
     *
     * @param \StdClass $module
     * @return void
     */
    public function install(\StdClass $module)
    {
        $this->structure = $module;

        // Initialize module file path
        $this->initFilePath();

        // Create module structure
        $module = $this->createModule();

        // Activate module on all domains
        $this->activateModuleOnDomains($module);

        // Create module table
        $this->createTable($module);

        // Create default filter
        $this->createDefaultFilter($module);

        $this->createFilters($module);

        // Create related lists
        $this->createRelatedLists($module);

        // Create language files
        $this->createLanguageFiles($module);

        // Create model file
        $this->createModelFile($module);

        // Delete designed module
        // $this->deleteDesignedModule();
    }

    protected function initFilePath()
    {
        $this->filePath = base_path();

        if (isset($this->structure->data->package)) {
            if ($this->structure->data->package === 'app') {
                $this->filePath = base_path();
            } else {
                // Extract vendor and package names
                $packageParts = explode('/', $this->structure->data->package);

                if (count($packageParts) === 2) {
                    $this->filePath =  base_path('packages/'.$packageParts[ 0 ].'/'.$packageParts[ 1 ]);
                }
            }
        }
    }

    /**
     * Create module structure in the database
     *
     * @return \Uccello\Core\Models\Module
     */
    protected function createModule()
    {
        // Create new module
        $module = Module::firstOrNew([
            'name' => $this->structure->name,
        ]);

        // Check if the module already exists
        $alreadyExits = !empty($module->id);

        // Delete obsolete structure elements if necessary
        if ($alreadyExits) {
            $this->deleteObsoleteStructureElements($module);
        }

        $module->icon = $this->structure->icon;
        $module->model_class = $this->structure->model;
        $module->data = $this->structure->data ?? null;
        $module->save();

        // Create tabs
        if (isset($this->structure->tabs)) {
            foreach ($this->structure->tabs as $_tab) {
                $tab = Tab::findOrNew($_tab->id);
                $tab->label = $_tab->label;
                $tab->module_id = $module->id;
                $tab->icon = $_tab->icon;
                $tab->data = $_tab->data ?? null;
                $tab->sequence = $_tab->sequence;
                $tab->save();

                // Create blocks
                foreach ($_tab->blocks as $_block) {
                    $block = Block::findOrNew($_block->id);
                    $block->label = $_block->label;
                    $block->module_id = $module->id;
                    $block->icon = $_block->icon;
                    $block->data = $_block->data ?? null;
                    $block->sequence = $_block->sequence;
                    $block->tab_id = $tab->id;
                    $block->save();

                    // Create fields
                    foreach ($_block->fields as $_field) {
                        $field = Field::findOrNew($_field->id);
                        $field->name = $_field->name;
                        $field->module_id = $module->id;
                        $field->block_id = $block->id;
                        $field->data = $_field->data ?? null;
                        $field->uitype_id = uitype($_field->uitype)->id;
                        $field->displaytype_id = displaytype($_field->displaytype)->id;
                        $field->sequence = $_field->sequence;
                        $field->save();
                    }
                }
            }
        }

        if (!is_null($this->command)) {
            if ($alreadyExits) {
                $statusMessage = 'already exists. It was <comment>updated</comment>.';
            } else {
                $statusMessage = 'was created.';
            }

            $this->command->line('The module <info>'.$module->name.'</info> '.$statusMessage);
        }

        return $module;
    }

    /**
     * Delete obsolete tabs, blocks and fields if necessary
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function deleteObsoleteStructureElements(Module $module)
    {
        // Delete tabs
        $this->deleteObsoleteTabs($module);

        // Delete blocks
        $this->deleteObsoleteBlocks($module);

        // Delete fields
        $this->deleteObsoleteFields($module);

        // Delete related lists
        $this->deleteObsoleteRelatedLists($module);
    }

    /**
     * Delete obsolete tabs if necessary
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function deleteObsoleteTabs(Module $module)
    {
        foreach ($module->tabs as $tab) {
            $found = false;

            // Search tab in the new structure
            foreach ($this->structure->tabs as $_tab) {
                if ($tab->id === $_tab->id) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $tab->delete();
            }
        }
    }

    /**
     * Delete obsolete blocks if necessary
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function deleteObsoleteBlocks(Module $module)
    {
        foreach ($module->blocks as $block) {
            $found = false;

            // Search block in the new structure
            foreach ($this->structure->tabs as $_tab) {
                foreach ($_tab->blocks as $_block) {
                    if ($block->id === $_block->id) {
                        $found = true;
                        break 2;
                    }
                }
            }

            if (!$found) {
                $block->delete();
            }
        }
    }

    /**
     * Delete obsolete fields if necessary
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function deleteObsoleteFields(Module $module)
    {
        foreach ($module->fields as $field) {
            $found = false;

            // Search field in the new structure
            foreach ($this->structure->tabs as $_tab) {
                foreach ($_tab->blocks as $_block) {
                    foreach ($_block->fields as $_field) {
                        if ($field->id === $_field->id) {
                            $found = true;
                            break 3;
                        }
                    }
                }
            }

            if (!$found) {
                $field->delete();
                $this->fieldsToDelete[ ] = $field;
            }
        }
    }

    /**
     * Delete obsolete related lists if necessary
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function deleteObsoleteRelatedLists(Module $module)
    {
        foreach ($module->relatedlists as $relatdlist) {
            $found = false;

            // Search related list in the new structure
            foreach ($this->structure->relatedlists as $_relatdlist) {
                if ($relatdlist->id === $_relatdlist->id) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $relatdlist->delete();
            }
        }
    }

    /**
     * Create module table from fields information
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function createTable(Module $module)
    {
        $tableName = $this->structure->tablePrefix.$this->structure->tableName;

        if (!Schema::hasTable($tableName)) {
            // Create table
            Schema::create($tableName, function (Blueprint $table) use ($module) {
                $table->increments('id');

                // Create each column according to the selected uitype
                if (!empty($this->structure->tabs)) {
                    foreach ($this->structure->tabs as $_tabs) {
                        if (!empty($_tabs->blocks)) {
                            foreach ($_tabs->blocks as $_block) {
                                if (!empty($_block->fields)) {
                                    foreach ($_block->fields as $_field) {
                                        // Do not recreate id
                                        if ($_field->name === 'id') {
                                            continue;
                                        }

                                        if (!empty((array)$_field->data)) {
                                            $data = $_field->data;
                                        } else {
                                            $data = null;
                                        }

                                        $field = new Field([
                                            'name' => $_field->name,
                                            'uitype_id' => uitype($_field->uitype)->id,
                                            'displaytype_id' => displaytype($_field->displaytype)->id,
                                            'sequence' => $_field->sequence,
                                            'data' => $data
                                        ]);

                                        // Create column
                                        $this->createColumn($field, $table);
                                    }
                                }
                            }
                        }
                    }
                }

                $table->unsignedInteger('domain_id');
                $table->timestamps();
                $table->softDeletes();
            });

            if (!is_null($this->command)) {
                $this->command->line('The table <info>'.$tableName.'</info> was created.');
            }
        } else {
            // Update table
            Schema::table($tableName, function (Blueprint $table) use ($module, $tableName) {
                // Create each column according to the selected uitype
                if (!empty($this->structure->tabs)) {
                    foreach ($this->structure->tabs as $_tabs) {
                        if (!empty($_tabs->blocks)) {
                            foreach ($_tabs->blocks as $_block) {
                                if (!empty($_block->fields)) {
                                    foreach ($_block->fields as $_field) {
                                        // Do not recreate id
                                        if ($_field->name === 'id') {
                                            continue;
                                        }

                                        if (!empty((array)$_field->data)) {
                                            $data = $_field->data;
                                        } else {
                                            $data = null;
                                        }

                                        $field = new Field([
                                            'name' => $_field->name,
                                            'uitype_id' => uitype($_field->uitype)->id,
                                            'displaytype_id' => displaytype($_field->displaytype)->id,
                                            'sequence' => $_field->sequence,
                                            'data' => $data
                                        ]);

                                        // Check if the column already exists and if we need to update it
                                        $updateColumn = Schema::hasColumn($tableName, $field->column);

                                        // Create column
                                        $this->createColumn($field, $table, $updateColumn);
                                    }
                                }
                            }
                        }
                    }
                }

                // Drop old columns
                foreach ($this->fieldsToDelete as $field) {
                    // Check if the column exists
                    $columnExists = Schema::hasColumn($tableName, $field->column);

                    if ($columnExists) {
                        $column = uitype($field->uitype->id)->createFieldColumn($field, $table);

                        // Set column nullable (instead to drop it to preserve old data in production)
                        $column->nullable()->change();

                        // Delete column
                        // $table->dropColumn($field->column);
                    }
                }
            });

            if (!is_null($this->command)) {
                $this->command->line('The table <info>'.$tableName.'</info> already exists. It was <comment>updated</comment>.');
            }
        }
    }

    /**
     * Create column in database table
     *
     * @param \Uccello\Core\Models\Field $field
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param boolean $updateColumn
     * @return void
     */
    protected function createColumn(Field $field, Blueprint $table, bool $updateColumn = false)
    {
        $tableName = $this->structure->tablePrefix.$this->structure->tableName;

        // Create column
        $column = uitype($field->uitype->id)->createFieldColumn($field, $table);

        // Get field rules
        $isRequired = false;
        if (isset($field->data->rules)) {
            $rules = explode('|', $field->data->rules);

            // Check if the field is required
            if (in_array('required', $rules)) {
                $isRequired = true;
            }
        }

        // Add nullable() if the field is not required
        if (!$isRequired) {
            $column->nullable();
        }

        if ($updateColumn) {
            $column->change();
        }
    }

    /**
     * Create default filter
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function createDefaultFilter(Module $module)
    {
        // Add all field in the filter
        $columns = [ ];
        foreach ($this->getAllFields() as $field) {
            if (isset($field->displayInFilter) && $field->displayInFilter === true) {
                $columns[ ] = $field->name;
            }
        }

        if ($columns) {
            $filter = Filter::firstOrNew([
                "module_id" => $module->id,
                "domain_id" => null,
                "user_id" => null,
                "name" => 'filter.all',
                "type" => 'list',
            ]);
            $filter->columns = $columns;
            $filter->conditions = null;
            $filter->order = null;
            $filter->is_default = true;
            $filter->is_public = false;
            $filter->data = [ 'readonly' => true ];
            $filter->save();
        }
    }

    /**
     * Create all filters
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function createFilters(Module $module)
    {
        if (!isset($this->structure->filters)) {
            return;
        }

        // Add all field in the filter
        foreach ($this->structure->filters as $_filter) {
            $columns = [ ];
            foreach ($_filter->columns as $column) {
                $columns[ ] = $column;
            }
            $filter = Filter::firstOrNew([
                "module_id" => $module->id,
                "domain_id" => null,
                "user_id" => null,
                "name" => 'filter.'.$_filter->name,
                "type" => 'list',
            ]);
            $filter->columns = $columns;
            $filter->conditions = null;
            $filter->order = null;
            $filter->is_default = true;
            $filter->is_public = false;
            $filter->data = [ 'readonly' => true ];
            $filter->save();
        }
    }

    /**
     * Create all related lists
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function createRelatedLists(Module $module)
    {
        if (isset($this->structure->relatedLists)) {
            foreach ($this->structure->relatedLists as $_relatedList) {
                $relatedModule = Module::where('name', $_relatedList->related_module)->first();

                // Get tab where we want to connect the related list if defined
                if (isset($_relatedList->tab)) {
                    $tab = Tab::where('module_id', $module->id)
                        ->where('label', $_relatedList->tab)
                        ->first();
                } else {
                    $tab = null;
                }

                // Get related field if defined
                if (isset($_relatedList->related_field)) {
                    $relatedField = Field::where('module_id', $relatedModule->id)
                        ->where('name', $_relatedList->related_field)
                        ->first();
                } else {
                    $relatedField = null;
                }

                $relatedList = Relatedlist::firstOrNew([
                    "module_id" => $module->id,
                    "related_module_id" => $relatedModule->id,
                    "label" => $_relatedList->label,
                ]);
                $relatedList->tab_id = isset($tab) ? $tab->id : null;
                $relatedList->related_field_id = isset($relatedField) ? $relatedField->id : null;
                $relatedList->icon = $_relatedList->icon;
                $relatedList->type = $_relatedList->type;
                $relatedList->method = $_relatedList->method;
                $relatedList->data = $_relatedList->data;
                $relatedList->sequence = $_relatedList->sequence;
                $relatedList->save();
            }
        }
    }

    /**
     * Activate module on all domains
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function activateModuleOnDomains(Module $module)
    {
        $domains = Domain::all();

        foreach ($domains as $domain) {
            $domain->modules()->detach($module); // Useful if it exists yet
            $domain->modules()->attach($module);
        }
    }

    /**
     * Create or update language files
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function createLanguageFiles(Module $module)
    {
        foreach ($this->structure->lang as $locale => $translations) {
            $languageFile = $this->filePath.'/resources/lang/'.$locale.'/'.$this->structure->name.'.php';

            // If file exists then update translations
            if (File::exists($languageFile)) {
                // Get old translations ($languageFile returns an array)
                $fileTranslations = File::getRequire($languageFile);

                // Add or update translations ($translations have priority)
                $translations = array_merge((array)$fileTranslations, (array)$translations);

                $message = 'The file <info>'.$languageFile.'</info> already exists. It was <comment>updated</comment>.';
            } else {
                $message = 'The file <info>'.$languageFile.'</info> was created.';
            }

            // Write language file
            $this->writeLanguageFile($languageFile, $translations);

            if (!is_null($this->command)) {
                $this->command->line($message);
            }
        }
    }

    /**
     * Write language file
     *
     * @param string $filepath
     * @param Object|array $translations
     * @return void
     */
    protected function writeLanguageFile(string $filepath, $translations)
    {
        $content = "<?php\n\n".
                    "return ";

        $content .= $this->arrayReadableEncode($translations);

        $content .= ';';

        // Create language directory if necessary
        $directory = dirname($filepath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true); // Recursive
        }

        File::put($filepath, $content);
    }

    /**
     * Create model file
     *
     * @param \Uccello\Core\Models\Module $module
     * @return void
     */
    protected function createModelFile(Module $module)
    {
        // Check model stub file existence (from module-designer-core package)
        $stubsDirectory = base_path('vendor/uccello/module-designer-core/src/Console/Commands/stubs');

        if (!File::exists($stubsDirectory.'/model.stub')) {
            if (!is_null($this->command)) {
                $this->command->line('<error>You have to install module-designer-core to generate the model file</error> : <comment>composer require uccello/module-designer-core</comment>');
            }
            return;
        }

        if (File::isDirectory($this->filePath.'/src')) {
            $basePath = $this->filePath.'/src/';
        } elseif (File::isDirectory($this->filePath.'/app')) {
            $basePath = $this->filePath.'/app/';
        }

        $modelClassData = explode('\\', $this->structure->model);

        // Extract class name
        $className = array_pop($modelClassData); // Remove last element: Model

        // Extract namespace
        $namespace = implode('\\', $modelClassData);

        // Extract subdirectories
        $subDirectories = '';
        if (count($modelClassData) > 2) {
            Arr::pull($modelClassData, 0); // Remove first element: Vendor
            Arr::pull($modelClassData, 1); // Remove second element: Package

            // Now it remains only the subdirectories
            $subDirectories = implode('/', $modelClassData);

            // Create sub directories if not exist
            if (!File::isDirectory($basePath.$subDirectories)) {
                File::makeDirectory($basePath.$subDirectories, 0755, true); // Recursive
            }

            $subDirectories .= '/';
        }

        // Table name
        $tableName = $this->structure->tableName;

        // Table prefix
        $tablePrefix = $this->structure->tablePrefix;

        // File path
        $modelFile = $basePath.$subDirectories.$className.'.php';

        // Check if file already exists
        if (File::exists($modelFile)) {
            if (!is_null($this->command)) {
                $modelFileCopy = str_replace('.php', '.prev.php', $modelFile);
                File::move($modelFile, $modelFileCopy);
                $this->command->line('<error>WARNING:</error> The file <info>'.$modelFile.'</info> already exists. '.
                    'It was <comment>renamed</comment> into <info>'.File::basename($modelFileCopy).'</info>.'
                );
            }
        }

        // Generate table prefix
        if (!empty($tablePrefix)) {
            $initTablePrefix = "\n    protected function initTablePrefix()\n".
                            "    {\n".
                            "        \$this->tablePrefix = '$tablePrefix';\n".
                            "    }\n";
        } else {
            $initTablePrefix = '';
        }

        // Generate relations
        $relations = "";
        foreach ($this->getAllFields() as $field) {
            if ($field->uitype === 'entity') {
                $relatedModule = Module::where('name', $field->data->module)->first();

                if ($relatedModule) {
                    $relations .= "\n    public function ".$field->name."()\n".
                                "    {\n".
                                "        return \$this->belongsTo(\\".$relatedModule->model_class."::class);\n".
                                "    }\n";
                }
            }
        }

        if (isset($this->structure->relatedLists)) {
            foreach ($this->structure->relatedlists as $relatedList) {
                if ($relatedList->type !== 'n-n') {
                    continue;
                }

                $relatedModule = Module::where('name', $relatedList->related_module)->first();

                $relations .= "\n    public function ".$relatedList->relation->relationName."()\n".
                                "    {\n".
                                "        return \$this->belongsToMany(\\".$relatedModule->model_class."::class, '".$relatedList->relation->tableName."')->withTimestamps();\n".
                                "    }\n";
            }
        }

        // Generate content
        $fileContent = File::get($stubsDirectory.'/model.stub');

        $content = str_replace(
            [
                '// %namespace%',
                'ClassName',
                '%table_name%',
                '// %init_table_prefix%',
                '// %relations%'
            ],
            [
                "namespace $namespace;",
                $className,
                $tableName,
                $initTablePrefix,
                $relations
            ],
            $fileContent
        );

        File::put($modelFile, $content);

        if (!is_null($this->command)) {
            $this->command->line('The file <info>'.$modelFile.'</info> was created.');
        }
    }

    /**
     * Delete module from designed_modules table
     *
     * @return void
     */
    protected function deleteDesignedModule()
    {
        // Search designed module by name
        $designedModule = DesignedModule::where('name', $this->structure->name);

        // Delete if exists
        if (!is_null($designedModule)) {
            $designedModule->delete();
        }
    }

    /**
     * Get all module fields
     *
     * @return array
     */
    protected function getAllFields()
    {
        $fields = [ ];

        if (isset($this->structure->tabs)) {
            foreach ($this->structure->tabs as $tab) {
                foreach ($tab->blocks as $block) {
                    if (isset($block->fields)) {
                        foreach ($block->fields as $field) {
                            $fields[ ] = $field;
                        }
                    }
                }
            }
        }

        return $fields;
    }

    protected function arrayReadableEncode($in, $indent = 0, $from_array = false)
    {
        $_escape = function ($str) {
            return preg_replace("!([\b\t\n\r\f\"\\'])!", "\\\\\\1", $str);
        };

        $out = '';

        foreach ($in as $key => $value) {
            $out .= str_repeat("    ", $indent + 1);
            $out .= "'".$_escape((string)$key)."' => ";

            if (is_object($value) || is_array($value)) {
                $out .= $this->arrayReadableEncode($value, $indent + 1);
            } elseif (is_bool($value)) {
                $out .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $out .= 'null';
            } elseif (is_string($value)) {
                $out .= "'" . $_escape($value) ."'";
            } else {
                $out .= $value;
            }

            $out .= ",\n";
        }

        if (!empty($out)) {
            $out = substr($out, 0, -2);
        }

        $out = "[\n" . $out;
        $out .= "\n" . str_repeat("    ", $indent) . "]";

        return $out;
    }
}
