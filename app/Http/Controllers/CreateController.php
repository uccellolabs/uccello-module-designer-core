<?php

namespace Uccello\ModuleDesigner\Http\Controllers;

use Kris\LaravelFormBuilder\FormBuilder;
use Illuminate\Http\Request;
use Uccello\Core\Http\Controllers\Core\Controller;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;
use Uccello\Core\Forms\EditForm;

class CreateController extends Controller
{
    protected $viewName = 'create.main';
    protected $formBuilder;

    /**
     * Check user permissions
     */
    protected function checkPermissions()
    {
        $this->middleware('uccello.permissions:admin');
    }

    public function __construct(FormBuilder $formBuilder)
    {
        $this->formBuilder = $formBuilder;

        parent::__construct();
    }

    /**
     * Process and display asked page
     * @param Domain|null $domain
     * @param Module $module
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function process(?Domain $domain, Module $module, Request $request)
    {
        // Pre-process
        $this->preProcess($domain, $module, $request);

        return $this->autoView();
    }

    public function getUitypes(?Domain $domain, Module $module, Request $request)
    {
        // Pre-process
        $this->preProcess($domain, $module, $request);

        // Retrieve record or get a new empty instance
        $record = $this->getRecordFromRequest();

        // Get form
        $form = $this->getForm($record);

        return view('module-designer::modules.module_designer.create.uitypes', [
            'domain' => $domain,
            'module' => $module,
            'record' => $record,
            'form' => $form,
            'mode' => 'create',
        ]);
    }

    public function getForm($record = null)
    {
        return $this->formBuilder->create(EditForm::class, [
            'model' => $record,
            'data' => [
                'domain' => $this->domain,
                'module' => $this->module
            ]
        ]);
    }
}