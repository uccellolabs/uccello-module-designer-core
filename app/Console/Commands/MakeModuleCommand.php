<?php

namespace Uccello\ModuleDesigner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Uccello\Core\Models\Uitype;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Tab;
use Uccello\Core\Models\Block;
use Uccello\Core\Models\Field;
use Uccello\Core\Models\Displaytype;
use Uccello\ModuleDesigner\Support\ModuleImport;
use Uccello\ModuleDesigner\Support\ModuleExport;
use Uccello\ModuleDesigner\Models\DesignedModule;

class MakeModuleCommand extends Command
{
    /**
     * The structure of the module.
     *
     * @var \StdClass
     */
    protected $module;

    /**
     * The default locale.
     *
     * @var string
     */
    protected $locale;

    /**
     * File system implementation
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

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
    public function __construct(FileSystem $files)
    {
        parent::__construct();

        $this->files = $files;

        $this->locale = \Lang::getLocale();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!in_array(env('APP_ENV'), ['local', 'dev', 'development'])) {
            $this->error('You can use this command only in development environment');
            $this->line('<comment>APP_ENV</comment> (in .env file) must equals to <comment>local | dev | development</comment>');
            return;
        }

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


        $choices = [];
        $modules = [];

        $createModuleChoice = 'Create a new module';
        $editModuleChoice = 'Edit a module';
        $removeDesignedModuleChoice = count($designedModules) > 0 ? 'Remove a designed module from the list' : null;

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
            $createModuleChoice,
            $editModuleChoice
        ]);

        // If designed modules are found display other options
        if (count($designedModules) > 0) {
            $availableChoices[] = $removeDesignedModuleChoice;
            $message = 'Some modules are being designed. Choose a module to continue or select an action to perform';
        } else {
            $message = 'Select an action to perform';
        }

        // Ask the action to perform
        $choice = $this->choice($message, $availableChoices);

        // Create a new module
        if ($choice === $createModuleChoice) {
            $this->createModule();
            return;
        }
        // Edit a module
        elseif ($choice === $editModuleChoice) {
            $module = $this->selectModule('Select the module you want to edit');

            $import = new ModuleExport($this->files, $this);
            $this->module = $import->getStructure($module);

            // Show an error if the module is already being edited
            if (DesignedModule::where('name', $module->name)->first() !== null) {
                $this->error('You have already started to edit this module');

                // Display the list again
                $this->checkForDesignedModules();
                return;
            }

            $designedModule = new DesignedModule([
                'name' => $module->name,
                'data' => $this->module
            ]);
            $designedModule->save();

            // Continue
            $this->chooseAction(1);
            return;
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
            return;
        }
        // Select module and continue
        else {
            $this->module = $modules[$choice];
            $this->line('<info>Selected module:</info> '.$choice);

            // Continue
            $this->chooseAction(1);
            return;
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
            'Add a tab',
            'Add a block',
            'Add a field',
            'Add a related list',
            'Add a link',
            'Delete an element',
            'Install module',
            'Make migration',
            'Exit'
        ];

        // Remove first choice if necessary
        $availableChoices = $choices;
        if (!$canCreateModule) {
            unset($availableChoices[0]);
        }

        // Remove other choices if module does not exist
        if (empty($this->module)) {
            unset($availableChoices[1]);
            unset($availableChoices[2]);
            unset($availableChoices[3]);
            unset($availableChoices[4]);
            unset($availableChoices[5]);
            unset($availableChoices[6]);
            unset($availableChoices[7]);
            unset($availableChoices[8]);
        }

        $choice = $this->choice('What action do you want to perform?', $availableChoices, $defaultChoiceIndex);

        switch ($choice) {
            // Create a new module
            case $choices[0]:
                $this->createModule();
                break;

            // Add a tab
            case $choices[1]:
                $this->createTab();
                break;

            // Add a block
            case $choices[2]:
                $this->createBlock();
                break;

            // Add a field
            case $choices[3]:
                $this->createField();
                break;

            // Add a related list
            case $choices[4]:
                $this->createRelatedList();
                break;

            // Add a link
            case $choices[5]:
                $this->createLink();
                break;

            // Install module
            case $choices[6]:
                $this->deleteElement();
                break;

            // Install module
            case $choices[7]:
                $this->installModule();
                break;

            // Make migration
            case $choices[8]:
                $this->makeMigration();
                break;

            // Exit
            case $choices[9]:
                // Do nothing
                break;
        }
    }

    /**
     * Check module existence or notice the user
     *
     * @return void
     */
    protected function checkModuleExistence()
    {
        if (empty($this->module)) {
            $this->error('You must create a module first');
            $this->chooseAction(0, true);
            return;
        }
    }

    /**
     * Ask the user information to make the skeleton of the module.
     *
     * @return void
     */
    protected function createModule()
    {
        $moduleName = $this->ask('What is the module name? (e.g. book-type)');

        // The snake_case function converts the given string to snake_case
        $moduleName = snake_case($moduleName);

        // If module name is not defined, ask again
        if (!$moduleName) {
            $this->error('You must specify a module name');
            $this->createModule();
            return;
        }
        // Check if module name is only with alphanumeric characters
        elseif (!preg_match('`^[a-z0-9-]+$`', $moduleName)) {
            $this->error('You must use only alphanumeric characters in lowercase');
            $this->createModule();
            return;
        }

        // Create an empty object
        $this->module = new \StdClass();
        $this->module->data = new \StdClass();
        $this->module->lang = new \StdClass();
        $this->module->lang->{$this->locale} = new \StdClass();

        // Name
        $this->module->name = kebab_case($moduleName);

        // Translation
        $this->module->lang->{$this->locale}->{$this->module->name} = $this->ask('Translation plural [' . $this->locale . ']');
        $this->module->lang->{$this->locale}->{'single.' . $this->module->name} = $this->ask('Translation single [' . $this->locale . ']');

        // Model class
        $defaultModelClass = 'App\\' . studly_case($moduleName); // The studly_case function converts the given string to StudlyCase
        $this->module->model = $this->ask('Model class', $defaultModelClass);

        // Package
        $package = null;

        // If the model class does not begin by App\, ask the user if he wants to create the module in an external package
        $modelClassParts = explode('\\', $this->module->model);
        if ($modelClassParts[0] !== 'App') {
            if ($this->confirm('Do you want to create this module in an external package?', true)) {
                // Select an external package
                $package = $this->selectPackage();
                if (!is_null($package)) {
                    $this->module->data->package = $package;
                }
            }
        }

        // Table name
        $this->module->tableName = $this->ask('Table name', str_plural($this->module->name));

        // Table prefix
        if (!empty($this->module->data->package)) {
            $packageParts = explode('/', $this->module->data->package);
            $packageName = array_pop($packageParts);
            $defaultPrefix = $packageName . '_';
        } else {
            $defaultPrefix = '';
        }
        $this->module->tablePrefix = $this->ask('Table prefix', $defaultPrefix);

        // Icon
        $this->module->icon = $this->ask('Material icon name (See https://material.io/tools/icons)');

        // Is for administration
        $isForAdmin = $this->confirm('Is this module for administration panel?');
        if ($isForAdmin) {
            $this->module->data->admin = true;
        }

        // Link
        $defaultRoute = $this->ask('Default route', 'uccello.list');
        if ($defaultRoute !== 'uccello.list') {
            $this->module->data->route = $defaultRoute;
        }

        // Display module data
        $this->table(
            [
                'Name',
                'Package',
                'Model',
                'Table',
                'Prefix',
                'Icon',
                'For admin',
                'Default route'
            ],
            [
                [
                    $this->module->name,
                    $package,
                    $this->module->model,
                    $this->module->tableName,
                    $this->module->tablePrefix,
                    $this->module->icon,
                    ($isForAdmin ? 'Yes' : 'No'),
                    $defaultRoute
                ]
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
     * Ask the user information to make the a new tab.
     *
     * @return void
     */
    protected function createTab()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Initialize tabs list if necessary
        if (!isset($this->module->tabs)) {
            $this->module->tabs = [];
        }

        $tab = new \StdClass();
        $tab->id = null;
        $tab->data = new \StdClass();
        $tab->blocks = [];

        // Label
        $defaultLabel = count($this->module->tabs) === 0 ? 'tab.main' : 'tab.tab' . count($this->module->tabs);
        $tab->label = $this->ask('Tab label (will be translated)', $defaultLabel);

        // Translation
        $this->module->lang->{$this->locale}->{$tab->label} = $this->ask('Translation [' . $this->locale . ']');

        // Icon
        $tab->icon = $this->ask('Icon CSS class name (See https://material.io/tools/icons)');

        // Sequence
        if (count($this->module->tabs) > 0) {

            $choices = [];
            foreach ($this->module->tabs as $moduleTab) {
                $choices[] = 'Before - ' . $moduleTab->label;
                $choices[] = 'After - ' . $moduleTab->label;
            }

            $position = $this->choice('Where do you want to add this tab?', $choices, $choices[count($choices)-1]);
            $tabIndex = floor(array_search($position, $choices) / 2);

            $tab->sequence = preg_match('`After`', $position) ? $tabIndex + 1 : $tabIndex;

        } else {
            $tab->sequence = 0;
        }

        // Update other blocks sequence
        foreach ($this->module->tabs as &$moduleTab) {
            if ($moduleTab->sequence >= $tab->sequence) {
                $moduleTab->sequence += 1;
            }
        }

        // Add tab
        $this->module->tabs[] = $tab;

        // Sort tabs by sequence
        usort($this->module->tabs, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a block)
        $this->chooseAction(2);
    }

    /**
     * Ask the user information to make the a new block.
     *
     * @return void
     */
    protected function createBlock()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Select a tab
        $tab = $this->selectTab();

        // Initialize blocks list if necessary
        if (!isset($tab->blocks)) {
            $tab->blocks = [];
        }

        $block = new \StdClass();
        $block->id = null;
        $block->data = new \StdClass();
        $block->fields = [];

        // Label
        $defaultLabel = count($tab->blocks) === 0 ? 'general' : 'block' . count($tab->blocks);
        $label = $this->ask('Block label (will be translated)', $defaultLabel);
        $block->label = 'block.' . $label;

        // Translation
        $this->module->lang->{$this->locale}->{$block->label} = $this->ask('Translation [' . $this->locale . ']');

        // Description
        if ($this->confirm('Do you want to add a description?')) {
            $block->data->description = $block->label . '.description';
            $this->module->lang->{$this->locale}->{$block->data->description} = $this->ask('Description translation [' . $this->locale . ']');
        }

        // Icon
        $block->icon = $this->ask('Icon CSS class name (See https://material.io/tools/icons)');

        // Sequence
        if (count($tab->blocks) > 0) {

            $choices = [];
            foreach ($tab->blocks as $moduleBlock) {
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
        foreach ($tab->blocks as &$moduleBlock) {
            if ($moduleBlock->sequence >= $block->sequence) {
                $moduleBlock->sequence += 1;
            }
        }

        // Add block
        $tab->blocks[] = $block;

        // Sort blocks by sequence
        usort($tab->blocks, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a field)
        $this->chooseAction(3);
    }

    /**
     * Ask the user information to make a new field.
     *
     * @return void
     */
    protected function createField()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Get all module fields
        $moduleFields = $this->getAllFields();

        // Select a block
        $block = $this->selectBlock('Choose the block in which to add the field');

        // Initialize fields list if necessary
        if (!isset($block->fields)) {
            $block->fields = [];
        }

        $field = new \StdClass();
        $field->id = null;
        $field->data = new \StdClass();

        // Name
        $field->name = $this->ask('Field name');
        $field->name = snake_case($field->name);

        // Check if the name already exists
        foreach ($moduleFields as $moduleField) {
            if ($moduleField->name === $field->name) {
                $this->error("A field called $field->name already exists");
                $this->chooseAction();
                return;
            }
        }

        // Translation
        $this->module->lang->{$this->locale}->{'field.' . $field->name} = $this->ask('Translation [' . $this->locale . ']');

        // Uitype
        $field->uitype = $this->choice('Choose an uitype', $this->getUitypes(), 'text');

        // Displaytype
        $field->displaytype = $this->choice('Choose a display type', $this->getDisplaytypes(), 'everywhere');

        // Ask the user if the field is required
        $required = $this->confirm('Is the field required?');
        if ($required) {
            $field->data->rules = "required";
        }

        $field->displayInFilter = $this->confirm('Display this field by default in the list view?', true);

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

        // Info
        if ($this->confirm('Do you want to add an information text?')) {
            $field->data->info = 'field.' . $field->name . '.info';
            $this->module->lang->{$this->locale}->{$field->data->info} = $this->ask('Information translation [' . $this->locale . ']');
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
        $this->chooseAction(3);
    }

    /**
     * Ask the user information to make a new related list.
     *
     * @return void
     */
    protected function createRelatedList()
    {
        // Check module existence
        $this->checkModuleExistence();

        if (!isset($this->module->relatedLists)) {
            $this->module->relatedLists = [];
        }

        $relatedList = new \StdClass();
        $relatedList->id = null;
        $relatedList->data = new \StdClass();

        // Label
        $relatedListIndex = count($this->module->relatedLists)+1;
        $defaultLabel = 'relatedlist'.$relatedListIndex;
        $label = $this->ask('Choose a label (will be translated)', $defaultLabel);
        $relatedList->label = 'relatedlist.' . $label;

        // Translation
        $this->module->lang->{$this->locale}->{$relatedList->label} = $this->ask('Translation [' . $this->locale . ']');

        // Type
        $relatedList->type = $this->choice('Choose a type', ['Relation n-1', 'Relation n-n']);
        $relatedList->type = str_replace('Relation ', '', $relatedList->type);

        // Related Module
        $relatedModule = $this->selectModule('Select the related module');
        $relatedList->related_module = $relatedModule->name;

        // Related field
        if ($relatedList->type === 'n-1') {
            $relatedField = $this->selectField($relatedModule);
            $relatedList->related_field = $relatedField->name;
        } else {
            $relatedList->related_field = null;
        }

        // Tab
        $displayInTab = $this->confirm('Do you want to display it in an existant tab? By default it will create a new tab.', false);
        if ($displayInTab) {
            $tab = $this->selectTab();
            $relatedList->tab = $tab->label;
        } else {
            $relatedList->tab = null;
        }

        // Method
        $defaultMethod = $relatedList->type === 'n-n' ? 'getRelatedList' : 'getDependentList';
        $relatedList->method = $this->ask('Choose a method', $defaultMethod);

        // Actions
        if ($relatedList->type === 'n-1') {
            $actionsChoices = [
                'add',
                'Nothing'
            ];
        } else {
            $actionsChoices = [
                'add',
                'select',
                'add,select',
                'Nothing'
            ];
        }
        $actionsAnswer = $this->choice('Choose available actions', $actionsChoices, 'Nothing');
        $relatedList->data->actions = $actionsAnswer === 'Nothing' ? [] : explode(",", $actionsAnswer);

        // Icon
        $relatedList->icon = $this->ask('Icon CSS class name (See https://material.io/tools/icons)');

        // Sequence
        if (count($this->module->relatedLists) > 0) {

            $choices = [];
            foreach ($this->module->relatedLists as $moduleRelatedList) {
                $choices[] = 'Before - ' . $moduleRelatedList->label;
                $choices[] = 'After - ' . $moduleRelatedList->label;
            }

            $position = $this->choice('Where do you want to add this related list?', $choices, $choices[count($choices)-1]);
            $relatedListIndex = floor(array_search($position, $choices) / 2);

            $relatedList->sequence = preg_match('`After`', $position) ? $relatedListIndex + 1 : $relatedListIndex;

        } else {
            $relatedList->sequence = 0;
        }

        // Update other related lists sequence
        foreach ($this->module->relatedLists as &$moduleRelatedList) {
            if ($moduleRelatedList->sequence >= $relatedList->sequence) {
                $moduleRelatedList->sequence += 1;
            }
        }

        // Add related list
        $this->module->relatedLists[] = $relatedList;

        // Sort fields by sequence
        usort($this->module->relatedLists, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a related list)
        $this->chooseAction(4);
    }

    /**
     * Ask the user information to make a new link.
     *
     * @return void
     */
    protected function createLink()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Initialize links list if necessary
        if (!isset($this->module->links)) {
            $this->module->links = [];
        }

        $link = new \StdClass();
        $link->id = null;
        $link->data = new \StdClass();

        // Label
        $defaultLabel = 'link' . count($this->module->links);
        $label = $this->ask('Link label (will be translated)', $defaultLabel);
        $link->label = 'link.' . $label;

        // Translation
        $this->module->lang->{$this->locale}->{$link->label} = $this->ask('Translation [' . $this->locale . ']');

        // Icon
        $link->icon = $this->ask('Icon CSS class name (See https://material.io/tools/icons)');

        // Type
        $link->type = $this->choice('Type of link', ['detail', 'detail.action'], 'detail');

        // URL
        $link->url = $this->ask('URL');

        // Action type
        $link->data->actionType = $this->choice('Action type', ['link', 'ajax', 'modal'], 'link');

        // Color
        $link->data->color = $this->choice('Button color', [
            'default',
            'primary',
            'success',
            'info',
            'warning',
            'danger',
            'red',
            'pink',
            'purple',
            'deep-purple',
            'indigo',
            'blue',
            'light-blue',
            'cyan',
            'teal',
            'green',
            'light-green',
            'lime',
            'yellow',
            'amber',
            'orange',
            'deep-orange',
            'brown',
            'grey',
            'blue-grey',
            'black'
        ], 'primary');

        // Confirm
        $confirm = $this->confirm('Do you want to show a confirm alert?', false);
        if ($confirm) {
            $link->data->confirm = true;

            $customize = $this->confirm('Do you want to customize the confirm dialog?', false);
            if ($customize) {
                $link->data->dialog = new \StdClass();

                $link->data->dialog->title = $this->ask('Title', 'Are you sure?');
                $link->data->dialog->confirmButtonText = $this->ask('Confirm button text', 'Yes');
                $link->data->dialog->confirmButtonColor = $this->ask('Confirm button color', '#DD6B55');
                $link->data->dialog->closeOnConfirm = $this->confirm('Close dialog on confirm?', true);
            }
        }

        // Add options according to action type
        switch ($link->data->actionType) {
            // Link
            case 'link':
                    // Target
                    $target = $this->ask('Link target (e.g. _blank)');
                    if (!is_null($target)) {
                        $link->data->target = $target;
                    }
                break;

            // Ajax
            case 'ajax':
                    $link->data->ajax = new \StdClass();

                    // HTTP method
                    $link->data->ajax->method = $this->choice('HTTP method', ['get', 'post', 'put', 'delete', 'head', 'patch', 'connect', 'options', 'trace'], 'get');

                    // Query params
                    $params = $this->ask('Query params');
                    if (!is_null($params)) {
                        $link->data->ajax->params = $params;
                    }

                    // Update DOM
                    $updateDom = $this->confirm('Do you want to update the DOM?', false);
                    if ($updateDom) {
                        // Element to update
                        $link->data->ajax->elementToUpdate = $this->ask('What is the DOM selector of the element to update? (e.g. .card:eq(1) .body)');
                    }
                break;

            // Modal
            case 'modal':
                    $link->data->modal = new \StdClass();
                    $link->data->modal->id = $this->ask('What is the id of the modal to show? (e.g. productModal)');
                break;
        }

        // Sequence
        if (count($this->module->links) > 0) {

            $choices = [];
            foreach ($this->module->links as $moduleLink) {
                $choices[] = 'Before - ' . $moduleLink->label;
                $choices[] = 'After - ' . $moduleLink->label;
            }

            $position = $this->choice('Where do you want to add this link?', $choices, $choices[count($choices)-1]);
            $linkIndex = floor(array_search($position, $choices) / 2);

            $link->sequence = preg_match('`After`', $position) ? $linkIndex + 1 : $linkIndex;

        } else {
            $link->sequence = 0;
        }

        // Update other links sequence
        foreach ($this->module->links as &$moduleLink) {
            if ($moduleLink->sequence >= $link->sequence) {
                $moduleLink->sequence += 1;
            }
        }

        // Add block
        $this->module->links[] = $link;

        // Sort blocks by sequence
        usort($this->module->links, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a link)
        $this->chooseAction(5);
    }

    protected function deleteElement()
    {
        $choices = [
            'Tab',
            'Block',
            'Field',
            'Related list',
            'Link'
        ];

        $choice = $this->choice('What type of element do you want to delete?', $choices);

        switch ($choice) {
            // Tab
            case $choices[0]:
                    $tab = $this->selectTab('Select the tab you want to delete. <error>WARNING: It will delete also all its blocks and fields!</error>', false);

                    // Delete tab
                    foreach ($this->module->tabs as $i => $_tab) {
                        if ($tab->label === $_tab->label) {
                            // Delete translation
                            $this->deleteTranslation($_tab->label);

                            // Delete blocks translations
                            foreach ($_tab->blocks as $_block) {
                                $this->deleteTranslation($_block->label);

                                // Delete fields translations
                                foreach ($_block->fields as $_field) {
                                    $this->deleteTranslation('field.' . $_field->name);
                                }
                            }

                            // Delete tab
                            unset($this->module->tabs[$i]);
                            break;
                        }
                    }
                break;

            // Block
            case $choices[1]:
                    $block = $this->selectBlock('Select the block you want to delete. <error>WARNING: It will delete also all its fields!</error>', false);

                    // Delete block
                    foreach ($this->module->tabs as $i => $_tab) {
                        foreach ($_tab->blocks as $j => $_block) {
                            if ($block->label === $_block->label) {
                                // Delete translation
                                $this->deleteTranslation($_block->label);

                                // Delete fields translations
                                foreach ($_block->fields as $_field) {
                                    $this->deleteTranslation('field.' . $_field->name);
                                }

                                // Delete block
                                unset($this->module->tabs[$i]->blocks[$j]);
                                break;
                            }
                        }
                    }
                break;

            // Field
            case $choices[2]:
                    $field = $this->selectField(null, 'Select the field you want to delete.', false);

                    // Delete field
                    foreach ($this->module->tabs as $i => $_tab) {
                        foreach ($_tab->blocks as $j => $_block) {
                            foreach ($_block->fields as $k => $_field) {
                                if ($field->name === $_field->name) {
                                    // Delete translation
                                    $this->deleteTranslation('field.' . $_field->name);

                                    // Delete field
                                    unset($this->module->tabs[$i]->blocks[$j]->fields[$k]);
                                    break;
                                }
                            }
                        }
                    }
                break;

            // Related list
            case $choices[3]:
                    $relatedlist = $this->selectRelatedList('Select the related list you want to delete.', false);

                    // Delete related list
                    foreach ($this->module->relatedlists as $i => $_relatedlist) {
                        if ($relatedlist->label === $_relatedlist->label) {
                            unset($this->module->relatedlists[$i]);
                            break;
                        }
                    }
                break;

            // Link
            case $choices[4]:
                    $link = $this->selectLink('Select the link you want to delete.', false);

                    // Delete link
                    foreach ($this->module->links as $i => $_link) {
                        if ($link->label === $_link->label) {
                            unset($this->module->links[$i]);
                            break;
                        }
                    }
                break;
        }

        // Save module structure
        $this->saveModuleStructure();

        // Ask the user to choose another action
        $this->chooseAction();
    }

    /**
     * Install module
     *
     * @return void
     */
    protected function installModule()
    {
        // Check module existence
        $this->checkModuleExistence();

        $import = new ModuleImport($this->files, $this);
        $import->install($this->module);
    }

    /**
     * Make migration file
     *
     * @return void
     */
    protected function makeMigration()
    {
        // Check migration stub file existence (from module-designer package)
        $stubsDirectory = base_path('vendor/uccello/module-designer/app/Console/Commands/stubs');

        if (!$this->files->exists($stubsDirectory . '/migration.stub')) {
            $this->line('<error>You have to install module-designer to generate the migration file</error> : <comment>composer require uccello/module-designer</comment>');
            return;
        }

        $modelClassData = explode('\\', $this->module->model);

        // Extract class name
        $className = array_pop($modelClassData); // Remove last element: Model

        // Module fields
        if (!empty((array) $this->module->data)) {
            $data = "json_decode('". json_encode($this->module->data)."')";
        } else {
            $data = 'null';
        }

        $icon = !empty($this->module->icon) ? "'{$this->module->icon}'" : "null";

        $moduleFields = "            'name' => '". $this->module->name ."',\n".
                        "            'icon' => $icon,\n".
                        "            'model_class' => '". ($this->module->model ?? null) ."',\n".
                        "            'data' => $data";

        // Table fields
        $tableFields = $this->getTableFieldsMigration();

        // Tabs, Blocks and Fields
        $tabsBlocksFields = $this->getTabsBlocksFieldsMigration();

        // Filters
        $filters = $this->getFiltersMigration();

        // Related lists
        $relatedLists = $this->getRelatedListsMigration();

        // Links
        $links = $this->getLinksMigration();

        // Get base path
        $basePath = '';
        if (isset($this->module->data->package)) {
            // Extract vendor and package names
            $packageParts = explode('/', $this->module->data->package);

            if (count($packageParts) === 2) {
                $basePath = 'packages/' . $packageParts[0] . '/' . $packageParts[1] . '/';
            }
        }

        // New file path
        $migrationFilePath = $basePath . 'database/migrations/' . date('Y_m_d_His') . '_create_' . str_replace('-', '_', $this->module->name) . '_module.php';

        // Generate content
        $fileContent = $this->files->get($stubsDirectory . '/migration.stub');

        $content = str_replace(
            [
                'ClassName',
                '%table_prefix%',
                '%table_name%',
                '%module_name%',
                '// %module_fields%',
                '// %table_fields%',
                '// %tabs_blocks_fields',
                '// %filters%',
                '// %relatedlists%',
                '// %links%',
            ],
            [
                'Create' . $className . 'Module',
                $this->module->tablePrefix,
                $this->module->tableName,
                $this->module->name,
                $moduleFields,
                $tableFields,
                $tabsBlocksFields,
                $filters,
                $relatedLists,
                $links,
            ],
            $fileContent
        );

        $this->files->put($migrationFilePath, $content);

        $this->line('The file <info>'. $migrationFilePath .' was created.</info>');

        $this->chooseAction();
    }

    /**
     * Generate migration code for table fields
     *
     * @return string
     */
    protected function getTableFieldsMigration()
    {
        $tableFields = '';

        foreach ($this->getAllFields() as $_field) {

            if (!empty((array) $_field->data)) {
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

            // Get field column creation in string format
            $createFieldColumnStr = uitype($_field->uitype)->createFieldColumnStr($field);

            // Check if the field is required
            $isRequired = false;
            if (isset($_field->data->rules)) {
                $rules = explode('|', $_field->data->rules);

                if (in_array('required', $rules)) {
                    $isRequired = true;
                }
            }

            // Add nullable() if the field is not required
            if (!$isRequired) {
                $createFieldColumnStr .= '->nullable()';
            }

            // Add semi comma at the and
            $createFieldColumnStr .= ';';

            $tableFields .= "            $createFieldColumnStr\n";
        }

        return $tableFields;
    }

    /**
     * Generate migration code for tabs, blocks and fields
     *
     * @return string
     */
    protected function getTabsBlocksFieldsMigration()
    {
        $tabsBlocksFields = '';

        if (!empty($this->module->tabs)) {
            foreach ($this->module->tabs as $_tab) {

                if (!empty((array) $_tab->data)) {
                    $data = "json_decode('". json_encode($_tab->data)."')";
                } else {
                    $data = 'null';
                }

                $icon = !empty($_tab->icon) ? "'$_tab->icon'" : "null";

                $tabsBlocksFields .= "\n        // Tab $_tab->label\n".
                                    "        \$tab = new Tab([\n".
                                    "            'module_id' => \$module->id,\n".
                                    "            'label' => '$_tab->label',\n".
                                    "            'icon' => $icon,\n".
                                    "            'sequence' => $_tab->sequence,\n".
                                    "            'data' => $data\n".
                                    "        ]);\n".
                                    "        \$tab->save();\n";

                if (!empty($_tab->blocks)) {
                    foreach ($_tab->blocks as $_block) {

                        if (!empty((array) $_block->data)) {
                            $data = "json_decode('". json_encode($_block->data)."')";
                        } else {
                            $data = 'null';
                        }

                        $icon = !empty($_block->icon) ? "'$_block->icon'" : "null";

                        $tabsBlocksFields .= "\n        // Block $_block->label\n".
                                            "        \$block = new Block([\n".
                                            "            'module_id' => \$module->id,\n".
                                            "            'tab_id' => \$tab->id,\n".
                                            "            'label' => '$_block->label',\n".
                                            "            'icon' => $icon,\n".
                                            "            'sequence' => $_block->sequence,\n".
                                            "            'data' => $data\n".
                                            "        ]);\n".
                                            "        \$block->save();\n";

                        if (!empty($_block->fields)) {
                            foreach ($_block->fields as $_field) {

                                if (!empty((array) $_field->data)) {
                                    $data = "json_decode('". json_encode($_field->data)."')";
                                } else {
                                    $data = 'null';
                                }

                                $tabsBlocksFields .= "\n        // Field $_field->name\n".
                                            "        \$field = new Field([\n".
                                            "            'module_id' => \$module->id,\n".
                                            "            'block_id' => \$block->id,\n".
                                            "            'name' => '$_field->name',\n".
                                            "            'uitype_id' => uitype('$_field->uitype')->id,\n".
                                            "            'displaytype_id' => displaytype('$_field->displaytype')->id,\n".
                                            "            'sequence' => $_field->sequence,\n".
                                            "            'data' => $data\n".
                                            "        ]);\n".
                                            "        \$field->save();\n";
                            }
                        }
                    }
                }
            }
        }

        return $tabsBlocksFields;
    }

    /**
     * Generate migration code for filters
     *
     * @return string
     */
    protected function getFiltersMigration()
    {
        $columns = [];
        foreach ($this->getAllFields() as $_field) {
            if ($_field->displayInFilter === true) {
                $columns[] = $_field->name;
            }
        }

        if (!empty($columns)) {
            $columnsStr = "'" . implode("', '", $columns) . "'";
        } else {
            $columnsStr = '';
        }

        return "\n        // Filter\n".
                                    "        \$filter = new Filter([\n".
                                    "            'module_id' => \$module->id,\n".
                                    "            'domain_id' => null,\n".
                                    "            'user_id' => null,\n".
                                    "            'name' => 'filter.all',\n".
                                    "            'type' => 'list',\n".
                                    "            'columns' => [$columnsStr],\n".
                                    "            'conditions' => null,\n".
                                    "            'order_by' => null,\n".
                                    "            'is_default' => true,\n".
                                    "            'is_public' => false\n".
                                    "        ]);\n".
                                    "        \$filter->save();\n";
    }

    protected function getRelatedListsMigration()
    {
        $relatedlists = '';

        if (!empty($this->module->relatedlists)) {
            foreach ($this->module->relatedlists as $_relatedlist) {

                if (!empty($_relatedlist->tab)) {
                    $tab = "\$relatedModule->tabs->where('label', '". $_relatedlist->tab ."')->first()";
                } else {
                    $tab = 'null';
                }

                if (!empty((array) $_relatedlist->data)) {
                    $data = "json_decode('". json_encode($_relatedlist->data)."')";
                } else {
                    $data = 'null';
                }

                $relatedlists .= "\n        // Related List $_relatedlist->label\n".
                                "        \$relatedModule = ucmodule('". $_relatedlist->related_module . "');\n".
                                "        \$tab = $tab;\n".
                                "        \$relatedlist = new Relatedlist([\n".
                                "            'module_id' => \$module->id,\n".
                                "            'related_module_id' => \$relatedModule->id,\n".
                                "            'tab_id' => !empty(\$tab) ? \$tab->id : null,\n".
                                "            'label' => '$_relatedlist->label',\n".
                                "            'type' => '$_relatedlist->type',\n".
                                "            'method' => '$_relatedlist->method',\n".
                                "            'data' => $data,\n".
                                "            'sequence' => $_relatedlist->sequence\n".
                                "        ]);\n".
                                "        \$relatedlist->save();\n";
            }
        }

        return $relatedlists;
    }

    protected function getLinksMigration()
    {
        $links = '';

        if (!empty($this->module->links)) {
            foreach ($this->module->links as $_link) {

                if (!empty((array) $_link->data)) {
                    $data = "json_decode('". json_encode($_link->data)."')";
                } else {
                    $data = 'null';
                }

                $icon = !empty($_link->icon) ? "'$_link->icon'" : "null";

                $links .= "\n        // Link $_link->label\n".
                                "        \$link = new Link([\n".
                                "            'module_id' => \$module->id,\n".
                                "            'label' => '$_link->label',\n".
                                "            'icon' => $icon,\n".
                                "            'type' => '$_link->type',\n".
                                "            'url' => '$_link->url',\n".
                                "            'sequence' => '$_link->sequence',\n".
                                "            'data' => $data,\n".
                                "            'sequence' => $_link->sequence\n".
                                "        ]);\n".
                                "        \$link->save();\n";
            }
        }

        return $links;
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

        if (!empty($this->module->tabs)) {
            foreach ($this->module->tabs as $tab) {
                if (!empty($tab->blocks)) {
                    foreach ($tab->blocks as $block) {
                        if (!empty($block->fields)) {
                            foreach ($block->fields as $field) {
                                $fields[] = $field;
                            }
                        }
                    }
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

        // Sort by name
        sort($uitypes);

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
     * Ask the user the package in which he wants to create the new module
     *
     * @return string
     */
    protected function selectPackage()
    {
        $package = null;

        // Get all packages
        $choices = $this->getPackages();

        if (count($choices) > 0) {
            $choice = $this->choice('In which package do you want to create the module?', $choices);

            $index = array_search($choice, $choices);

            $package = $choices[$index];
        }

        return $package;
    }

    /**
     * Ask user to select an existant tab
     *
     * @param string|null $message
     * @param boolean $autoSelect
     * @return \StdClass
     */
    protected function selectTab($message = null, $autoSelect = true)
    {
        if (empty($this->module->tabs)) {
            $this->error('You must create a tab first');
            $this->chooseAction(1);
            return;
        }

        if (empty($message)) {
            $message = 'Choose the tab';
        }

        $tabs = $this->module->tabs;

        return $this->selectFromList($tabs, 'label', $message, $autoSelect);
    }

    /**
     * Ask user to select an existant block
     *
     * @param string|null $message
     * @param boolean $autoSelect
     * @return \StdClass
     */
    protected function selectBlock($message = null, $autoSelect = true)
    {
        if (!$message) {
            $message = 'Choose the block';
        }

        $choices = [];

        $allBlocks = [];

        if (!empty($this->module->tabs)) {
            foreach ($this->module->tabs as $tab) {
                if (!empty($tab->blocks)) {
                    foreach($tab->blocks as $block) {
                        $choices[] = $block->label;

                        $allBlocks[] = $block;
                    }
                }
            }
        }

        if (empty($allBlocks)) {
            $this->error('You must create a block first');
            $this->chooseAction(2);
            return;
        }

        $defaultChoice = $autoSelect ? count($choices) - 1 : null;

        $choice = $this->choice($message, $choices, $defaultChoice);

        $index = array_search($choice, $choices);

        return $allBlocks[$index];
    }

    /**
     * Ask user to select an existant field
     *
     * @param \Uccello\Core\Models\Module|null $module
     * @param string|null $message
     * @param boolean $autoSelect
     * @return \StdClass
     */
    protected function selectField(?Module $module, $message = null, $autoSelect = true)
    {
        if (empty($message)) {
            $message = 'Choose the field';
        }

        if (!empty($module)) {
            $fields = $module->fields;
        } else {
            $fields = $this->getAllFields();
        }

        if (empty($fields)) {
            $this->error('No field available');
            $this->chooseAction();
            return;
        }

        return $this->selectFromList($fields, 'name', $message, $autoSelect);
    }

    /**
     * Ask the user to select a module
     *
     * @param string $message
     * @return string
     */
    protected function selectModule($message = null)
    {
        if (!$message) {
            $message = 'Choose the module in which to perform the action';
        }

        $modules = Module::whereNotNull('model_class')->orderBy('name')->get();

        $choices = [];
        foreach ($modules as $_module) {
            $choices[] = $_module->name;
        }

        // Add module itself if necessary
        if (!is_null($this->module) && !in_array($this->module->name, $choices)) {
            $choices[] = $this->module->name;
        }

        // We clone the array before to sort it to retrieve the good choice index
        $choices_orig = $choices;

        // Sort
        sort($choices);

        $choice = $this->choice($message, $choices);

        $index = array_search($choice, $choices_orig);

        return $modules[$index];
    }

    /**
     * Ask user to select an existant related list
     *
     * @param string|null $message
     * @param boolean $autoSelect
     * @return \StdClass
     */
    protected function selectRelatedList($message = null, $autoSelect = true)
    {
        if (empty($message)) {
            $message = 'Choose the related list';
        }

        $relatedlists = $this->module->relatedlists;

        if (empty($relatedlists)) {
            $this->error('No related list available');
            $this->chooseAction();
            return;
        }

        return $this->selectFromList($relatedlists, 'label', $message, $autoSelect);
    }

    /**
     * Ask user to select an existant link
     *
     * @param string|null $message
     * @param boolean $autoSelect
     * @return \StdClass
     */
    protected function selectLink($message = null, $autoSelect = true)
    {
        if (empty($message)) {
            $message = 'Choose the link';
        }

        $links = $this->module->links;

        if (empty($links)) {
            $this->error('No link available');
            $this->chooseAction();
            return;
        }

        return $this->selectFromList($links, 'label', $message, $autoSelect);
    }

    /**
     * Select item from a list
     *
     * @param array $list
     * @param string $attribute
     * @param string|null $message
     * @param boolean $autoSelect
     * @return mixed
     */
    protected function selectFromList(array $list, string $attribute, string $message = null, bool $autoSelect = false)
    {
        if (empty($list)) {
            return null;
        }

        $choices = [];

        foreach($list as $item) {
            $choices[] = $item->{$attribute};
        }

        // We clone the array before to sort it to retrieve the good choice index
        $choices_orig = $choices;

        // Sort by label
        sort($choices);

        $defaultChoice = $autoSelect ? count($choices) - 1 : null;

        $choice = $this->choice($message, $choices, $defaultChoice);

        $index = array_search($choice, $choices_orig);

        return $list[$index];
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

    /**
     * Scans packages directory and returns the packages list with the following format: vendor/package
     *
     * @return array
     */
    protected function getPackages() {
        $packages = [];

        // Get packages list from
        $packagePath = base_path('packages');

        if (is_dir($packagePath)) {
            // First level directories are vendors
            $vendors = $this->files->directories($packagePath);

            foreach ($vendors as $vendor) {
                // Second level directories are packages
                $vendorPackages = $this->files->directories($vendor);

                foreach ($vendorPackages as $vendorPackage) {
                    $packages[] = $this->files->basename($vendor) . '/' . $this->files->basename($vendorPackage);
                }
            }
        }

        // Sort packages by name
        sort($packages);

        return $packages;
    }

    /**
     * Delete translation from module structure
     *
     * @param string $label
     * @return void
     */
    protected function deleteTranslation($label)
    {
        $locale = $this->locale;

        unset($this->module->lang->{$locale}->{$label});

        if (empty($this->module->translationsToRemove)) {
            $this->module->translationsToRemove = [];
        }

        $this->module->translationsToRemove[] = $label;
    }
}
