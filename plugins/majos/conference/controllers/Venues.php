<?php namespace Majos\Conference\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Venues Backend Controller
 */
class Venues extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';


    public $requiredPermissions = ['majos.conference.manage_venues'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Majos.Conference', 'conference', 'venues');
    }
}