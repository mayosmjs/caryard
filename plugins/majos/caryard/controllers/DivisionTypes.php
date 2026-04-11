<?php namespace Majos\Caryard\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class DivisionTypes extends Controller
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
        BackendMenu::setContext('Majos.Caryard', 'caryard', 'divisiontypes');
    }
}