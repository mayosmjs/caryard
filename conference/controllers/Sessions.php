<?php namespace Majos\Conference\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Sessions Backend Controller
 */
class Sessions extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $modelClass = \Majos\Conference\Models\Session::class;

    public $requiredPermissions = ['majos.conference.manage_sessions'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Majos.Conference', 'conference', 'sessions');
    }
}