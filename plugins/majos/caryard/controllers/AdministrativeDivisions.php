<?php namespace Majos\Caryard\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class AdministrativeDivisions extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $requiredPermissions = ['majos.caryard.access_divisions'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Caryard', 'caryard', 'divisions');
    }

    /**
     * AJAX: return child divisions for a given parent (used in cascading dropdowns).
     */
    public function onGetChildren()
    {
        $parentId = post('parent_id');
        if (!$parentId) return ['result' => []];

        return [
            'result' => \Majos\Caryard\Models\AdministrativeDivision::getChildOptions($parentId)
        ];
    }
}