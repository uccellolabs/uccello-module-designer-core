<?php

namespace Uccello\ModuleDesigner\Console\Commands;

use Illuminate\Console\Command;
use Uccello\ModuleDesigner\Models\DesignedModule;
use Uccello\Core\Models\Uitype;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Tab;
use Uccello\Core\Models\Block;
use Uccello\Core\Models\Field;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Uccello\Core\Models\Displaytype;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Filter;

class MakeModuleCommand extends Command
{
    /**
     * The structure of the module.
     *
     * @var string
     */
    protected $module;

    /**
     * The default locale.
     *
     * @var string
     */
    protected $locale;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or edit a module compatible with Uccello';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->locale = \Lang::getLocale();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->checkForDesignedModules();
    }

    /**
     * Check if modules are being designed and
     * ask user to choose a module to continue
     * or select another action to perform.
     *
     * @return void
     */
    protected function checkForDesignedModules()
    {
        // Get all designed modules
        $designedModules = DesignedModule::all();

        // If designed modules are found display several options
        if (count($designedModules) > 0) {
            $choices = [];
            $modules = [];

            $createEditModuleChoice = 'Create or edit another module';
            $removeDesignedModuleChoice = 'Remove a designed module from the list';

            foreach ($designedModules as $module) {
                // Get module name
                $name = $module->name;

                // Store module data
                $modules[$name] = $module->data;

                // Add module name to choices list
                $choices[] = $name;
            }

            // Add actions to choices list
            $availableChoices = array_merge($choices, [
                $createEditModuleChoice,
                $removeDesignedModuleChoice
            ]);

            // Ask the action to perform
            $choice = $this->choice('Some modules are being designed. Choose a module to continue or select an action to perform', $availableChoices);

            // Create or Edit another module
            if ($choice === $createEditModuleChoice) {
                $this->chooseAction(0, true);
            }
            // Remove designed module from the list
            elseif ($choice === $removeDesignedModuleChoice) {
                // Ask the user what designed module he wants to remove
                $designedModuleToDelete = $this->choice('What designed module do you want to remove from the list?', $choices);

                // Delete designed module
                DesignedModule::where('name', $designedModuleToDelete)->delete();
                $this->info('<comment>' . $designedModuleToDelete . '</comment> was deleted from the list');

                // Display the list again
                $this->checkForDesignedModules();
            }
            // Select module and continue
            else {
                $this->module = $modules[$choice];
                $this->line('<info>Selected module:</info> '.$choice);

                // Continue
                $this->chooseAction(1);
            }
        }
        // No designed module available, simply continue
        else {
            $this->chooseAction(0, true);
        }
    }

    /**
     * Ask the user what action he wants to perform
     *
     * @param int|null $defaultChoiceIndex
     * @return void
     */
    protected function chooseAction($defaultChoiceIndex = null, $canCreateModule = false)
    {
        // Default choices
        $choices = [
            'Create a new module',
            'Add a block',
            'Add a field',
            'Add a related list',
            'Add a link',
            'Install module',
            'Exit'
        ];

        // Remove first choice if necessary
        $availableChoices = $choices;
        if (!$canCreateModule) {
            unset($availableChoices[0]);
        }

        $choice = $this->choice('What action do you want to perform?', $availableChoices, $defaultChoiceIndex);

        switch ($choice) {
            // Create a new module
            case $choices[0]:
                $this->createModule();
                break;

            // Add a block
            case $choices[1]:
                $this->createBlock();
                break;

            // Add a field
            case $choices[2]:
                $this->createField();
                break;

            // Add a related list
            case $choices[3]:
                $this->createRelatedList();
                break;

            // Add a link
            case $choices[4]:
                $this->createLink();
                break;

            // Install module
            case $choices[5]:
                $this->installModule();
                break;

            // Exit
            case $choices[6]:
                // Do nothing
                break;
        }
    }

    /**
     * Ask the user information to make the skeleton of the module.
     *
     * @return void
     */
    protected function createModule()
    {
        $moduleName = $this->ask('<info>What is the module name? (e.g. book_type)</info>');

        // The snake_case function converts the given string to snake_case
        $moduleName = snake_case($moduleName);

        // If module name is not defined, ask again
        if (!$moduleName) {
            $this->error('You must specify a module name');
            return $this->createModule();
        }
        // Check if module name is only with alphanumeric characters
        elseif (!preg_match('`^[a-z0-9_]+$`', $moduleName)) {
            $this->error('You must use only alphanumeric characters');
            return $this->createModule();
        }

        // Create an empty object
        $this->module = new \stdClass();
        $this->module->lang = new \StdClass();
        $this->module->lang->{$this->locale} = new \StdClass();

        // Name
        $this->module->name = snake_case($moduleName);

        // Translation
        $this->module->lang->{$this->locale}->{$this->module->name} = $this->ask('Translation plural (' . $this->locale . ')');
        $this->module->lang->{$this->locale}->{'single.' . $this->module->name} = $this->ask('Translation single (' . $this->locale . ')');

        // Model class
        $defaultModelClass = 'App\\' . studly_case($moduleName); // The studly_case function converts the given string to StudlyCase
        $this->module->model = $this->ask('Model class', $defaultModelClass);

        // Table name
        $this->module->tableName = $this->ask('Table name', str_plural($this->module->name));

        // Table prefix
        $this->module->tablePrefix = $this->ask('Table prefix');

        // Icon
        $this->module->icon = $this->ask('Material icon name (e.g. book)');

        // Is for administration
        $this->module->isForAdmin = $this->confirm('Is this module for administration panel?');

        // Link
        $this->module->link = $this->ask('Main page name', 'list');

        //TODO: Ask for package name

        // Display module data
        $this->table(
            [
                'Name', 'Model', 'Table', 'Prefix', 'Icon', 'For admin', 'Main page'
            ],
            [
                [$this->module->name, $this->module->model, $this->module->tableName, $this->module->tablePrefix,  $this->module->icon, ($this->module->isForAdmin ? 'Yes' : 'No'), $this->module->link]
            ]
        );

        // If information is not correct, restart step
        $isCorrect = $this->confirm('Is this information correct?', true);
        if (!$isCorrect) {
            return $this->createModule();
        }

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a block)
        $this->chooseAction(1);
    }

    /**
     * Ask the user information to make the a new block.
     *
     * @return void
     */
    protected function createBlock()
    {
        // Initialize blocks list if necessary
        if (!isset($this->module->blocks)) {
            $this->module->blocks = [];
        }

        $block = new \StdClass();
        $block->fields = [];

        // Label
        $defaultLabel = count($this->module->blocks) === 0 ? 'block.general' : 'block.block' . count($this->module->blocks);
        $block->label = $this->ask('Block label (will be translated)', $defaultLabel);

        // Translation
        $this->module->lang->{$this->locale}->{$block->label} = $this->ask('Translation (' . $this->locale . ')');

        // Icon
        $icon = $this->ask('Icon CSS class name (type <comment>NULL</comment> for not define it)', 'NULL');
        if (strtoupper($icon) === 'NULL') {
            $icon = null;
        }
        $block->icon = $icon;

        // Sequence
        if (count($this->module->blocks) > 0) {

            $choices = [];
            foreach ($this->module->blocks as $moduleBlock) {
                $choices[] = 'Before - ' . $moduleBlock->label;
                $choices[] = 'After - ' . $moduleBlock->label;
            }

            $position = $this->choice('Where do you want to add this block?', $choices, $choices[count($choices)-1]);
            $blockIndex = floor(array_search($position, $choices) / 2);

            $block->sequence = preg_match('`After`', $position) ? $blockIndex + 1 : $blockIndex;

        } else {
            $block->sequence = 0;
        }

        // Update other blocks sequence
        foreach ($this->module->blocks as &$moduleBlock) {
            if ($moduleBlock->sequence >= $block->sequence) {
                $moduleBlock->sequence += 1;
            }
        }

        // Add block
        $this->module->blocks[] = $block;

        // Sort blocks by sequence
        usort($this->module->blocks, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a field)
        $this->chooseAction(2);
    }

    /**
     * Ask the user information to make a new field.
     *
     * @return void
     */
    protected function createField()
    {
        // Get all module fields
        $moduleFields = $this->getAllFields();

        // Select a block
        $block = $this->selectBlock();

        // Initialize fields list if necessary
        if (!isset($block->fields)) {
            $block->fields = [];
        }

        $field = new \StdClass();
        $field->data = new \StdClass();

        // Name
        $field->name = $this->ask('Field name');
        $field->name = snake_case($field->name);

        // Check if the name already exists
        foreach ($moduleFields as $moduleField) {
            if ($moduleField->name === $field->name) {
                $this->error("A field called $field->name already exists.");
                $this->chooseAction();
                return;
            }
        }

        // Translation
        $this->module->lang->{$this->locale}->{'field.' . $field->name} = $this->ask('Translation (' . $this->locale . ')');

        // Uitype
        $field->uitype = $this->choice('Choose an uitype', $this->getUitypes(), 'text');

        // Displaytype
        $field->displaytype = $this->choice('Choose a display type', $this->getDisplaytypes(), 'everywhere');

        // Ask the user if the field is required
        $required = $this->confirm('Is the field required?');
        if ($required) {
            $field->data->rules = "required";
        }

        // Large
        $large = $this->confirm('Display the field in two columns?', false);
        if ($large) {
            $field->data->large = true;
        }

        // Default value
        $default = $this->ask('Default value');
        if (!is_null($default)) {
            $field->data->default = $default;
        }

        // Other rules
        $rules = $this->ask('Other rules (See https://laravel.com/docs/5.7/validation#available-validation-rules)');
        if (!is_null($rules)) {
            // Add to previous rules if defined
            if (!empty($field->data->rules)) {
                $rules = $field->data->rules . '|' . $rules;
            }

            $field->data->rules = $rules;
        }

        // Add specific options according to the selected uitype ($field is modified directly in the called function)
        uitype($field->uitype)->askFieldOptions($this->module, $field, $this->input, $this->output);

        // Sequence
        if (count($block->fields) > 0) {
            $choices = [];
            foreach ($block->fields as $blockField) {
                $choices[] = 'Before - ' . $blockField->name;
                $choices[] = 'After - ' . $blockField->name;
            }

            $position = $this->choice('Where do you want to add this field?', $choices, $choices[count($choices)-1]);
            $fieldIndex = floor(array_search($position, $choices) / 2);

            $field->sequence = preg_match('`After`', $position) ? $fieldIndex + 1 : $fieldIndex;

        } else {
            $field->sequence = 0;
        }

        // Update other fields sequence
        foreach ($block->fields as &$blockField) {
            if ($blockField->sequence >= $field->sequence) {
                $blockField->sequence += 1;
            }
        }

        // Add field
        $block->fields[] = $field;

        // Sort fields by sequence
        usort($block->fields, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a field)
        $this->chooseAction(2);
    }

    /**
     * Ask the user information to make a new related list.
     *
     * @return void
     */
    protected function createRelatedList()
    {
        //TODO:
    }

    /**
     * Ask the user information to make a new link.
     *
     * @return void
     */
    protected function createLink()
    {
        //TODO:
    }

    /**
     * Generate all files and database structure to install the new module
     *
     * @return void
     */
    protected function installModule()
    {
        // Create module structure
        $module = $this->createModuleStructure();

        // Create module table
        $this->createModuleTable($module);

        // Activate module on all domains
        $this->activateModuleOnDomain($module);

        // Create default filter
        $this->createDefaultFilter($module);

        // Create language file
        $this->createLanguageFile($module);

        // Create model file
        $this->createModelFile($module);
    }

    /**
     * Create module structure in the database
     *
     * @param Module $module
     * @return void
     */
    protected function createModuleStructure()
    {
        // Create new module
        $module = new Module([
            'name' => $this->module->name,
            'icon' => $this->module->icon,
            'model_class' => $this->module->model,
        ]);
        $module->save(); //TODO: or update

        // Create tab
        // TODO: Multi tabs
        $tab = new Tab([
            'label' => 'tab.main',
            'icon' => null,
            'sequence' => 0,
            'module_id' => $module->id,
        ]);
        $tab->save();

        // Create blocks
        foreach ($this->module->blocks as $_block) {
            $block = new Block([
                'label' => $_block->label,
                'icon' => $_block->icon,
                'sequence' => $_block->sequence,
                'tab_id' => $tab->id,
                'module_id' => $module->id,
            ]);
            $block->save();

            // Create fields
            foreach ($_block->fields as $_field) {
                $field = new Field([
                    'name' => $_field->name,
                    'sequence' => $_field->sequence,
                    'data' => $_field->data ?? null,
                    'uitype_id' => uitype($_field->uitype)->id,
                    'displaytype_id' => displaytype($_field->displaytype)->id,
                    'block_id' => $block->id,
                    'module_id' => $module->id,
                ]);
                $field->save();
            }
        }

        return $module;
    }

    /**
     * Create module table from fields information
     *
     * @param Module $module
     * @return void
     */
    protected function createModuleTable(Module $module)
    {
        Schema::create($this->module->tablePrefix . $this->module->tableName, function (Blueprint $table) use ($module) {
            $table->increments('id');

            // Create each column according to the selected uitype
            foreach ($module->fields as $field) {
                // Do not recreate id
                if ($field->name === 'id') {
                    continue;
                }

                // Get field rules
                if (isset($field->data->rules)) {
                    $rules = explode('|', $field->data->rules);
                } else {
                    $rules = [];
                }

                $column = uitype($field->uitype->id)->createFieldColumn($field, $table);

                // Add nullable() if the field is not required
                if (!in_array('required', $rules)) {
                    $column->nullable();
                }
            }

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Create default filter
     *
     * @param Module $module
     * @return void
     */
    protected function createDefaultFilter(Module $module)
    {
        // Add all field in the filter
        $columns = [];
        foreach ($module->fields as $field) { //TODO: Choose fields to display in filter
            $columns[] = $field->name;
        }

        $filter = new Filter();
        $filter->module_id = $module->id;
        $filter->domain_id = null;
        $filter->user_id = null;
        $filter->name = 'filter.all';
        $filter->type = 'list';
        $filter->columns = $columns;
        $filter->conditions = null;
        $filter->order_by = null;
        $filter->is_default = true;
        $filter->is_public = false;
        $filter->save();
    }

    /**
     * Activate module on all domains
     *
     * @param Module $module
     * @return void
     */
    protected function activateModuleOnDomain(Module $module)
    {
        $domains = Domain::all();

        foreach($domains as $domain) {
            $domain->modules()->attach($module);
        }
    }

    /**
     * Create language file
     *
     * @param Module $module
     * @return void
     */
    protected function createLanguageFile(Module $module)
    {
        $languageFile = 'resources/lang/' . $this->locale . '/' . $this->module->name . '.php';

        if (!file_exists($languageFile)) {
            $content = "<?php\n\n".
                        "return [\n";

            foreach ($this->module->lang->{$this->locale} as $key => $val) {
                $content .= "    '$key' => '". str_replace("'", "\'", $val) ."',\n";
            }

            $content .= "];";

            file_put_contents($languageFile, $content);
        }
    }

    /**
     * Create model file
     *
     * @param Module $module
     * @return void
     */
    protected function createModelFile(Module $module)
    {
        $modelClassData = explode("\\", $this->module->model);

        // Extract class name
        $className = $modelClassData[count($modelClassData) - 1];

        // Extract namespace
        $namespace = str_replace("\\$className", "", $this->module->model);

        // File path
        $modelFile = "app/$className.php";

        if (file_exists($modelFile)) {
            return false;
        }

        // Generate relations
        $relations = "";
        foreach ($this->getAllFields() as $field) {
            if ($field->uitype === 'entity') {
                $relatedModule = Module::where('name', $field->data->module)->first();

                if ($relatedModule) {
                    $relations .= "    public function ". $field->name . "()\n".
                                "    {\n".
                                "        return \$this->belongsTo(\\". $relatedModule->model_class . "::class);\n".
                                "    }\n\n";
                }
            }
        }

        // Generate content
        $content = "<?php\n\n".
                    "namespace $namespace;\n\n".
                    "use Illuminate\Database\Eloquent\Model;\n".
                    "\n".
                    "class $className extends Model\n".
                    "{\n".
                    $relations.
                    "    /**\n".
                    "    * Returns record label\n".
                    "    *\n".
                    "    * @return string\n".
                    "    */\n".
                    "    public function getRecordLabelAttribute() : string\n".
                    "    {\n".
                    "        return \$this->id;\n".
                    "    }\n".
                    "}";

        file_put_contents($modelFile, $content);
    }

    /**
     * Create or update a line into designed_modules table
     *
     * @return void
     */
    protected function saveModuleStructure()
    {
        $designedModule = DesignedModule::updateOrCreate(
            ['name' => $this->module->name],
            ['data' => $this->module]
        );
    }

    /**
     * Get all module fields
     *
     * @return array
     */
    protected function getAllFields()
    {
        $fields = [];

        foreach ($this->module->blocks as $block) {
            if (isset($block->fields)) {
                foreach ($block->fields as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Get all uitypes
     *
     * @return array
     */
    protected function getUitypes()
    {
        $uitypes = [];

        foreach (Uitype::all() as $uitype) {
            $uitypes[] = $uitype->name;
        }

        return $uitypes;
    }

    /**
     * Get all displaytypes
     *
     * @return array
     */
    protected function getDisplaytypes()
    {
        $displaytypes = [];

        foreach (Displaytype::all() as $displaytype) {
            $displaytypes[] = $displaytype->name;
        }

        return $displaytypes;
    }

    /**
     * Ask user to select an existant block
     *
     * @return \StdClass
     */
    protected function selectBlock()
    {
        $choices = [];

        foreach($this->module->blocks as $block) {
            $choices[] = $block->label;
        }

        $choice = $this->choice('Choose the block in which to add the field', $choices, count($choices) - 1);

        $index = array_search($choice, $choices);

        return $this->module->blocks[$index];
    }

    /**
     * Sort $a and $b by sequence
     *
     * @param \StdClass $a
     * @param \StdClass $b
     * @return int
     */
    protected function sortBySequence(\StdClass $a, \StdClass $b) {
        if ($a->sequence == $b->sequence) {
            return 0;
        }

        return ($a->sequence < $b->sequence) ? -1 : 1;
    }
}
