<?php namespace Majos\Conference\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Conference Days Backend Controller
 */
class ConferenceDays extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $modelClass = \Majos\Conference\Models\ConferenceDay::class;

    public $requiredPermissions = ['majos.conference.manage_days'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Majos.Conference', 'conference', 'conferencedays');
    }
}