<?php namespace Majos\Conference\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Speakers Backend Controller
 */
class Speakers extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $modelClass = \Majos\Conference\Models\Speaker::class;

    public $requiredPermissions = ['majos.conference.manage_speakers'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Majos.Conference', 'conference', 'speakers');
    }
}