<?php namespace Majos\Conference\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class PresentationTypes extends Controller
{
    public $requiredPermissions = ['majos.conference.manage_settings'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $pageTitle = 'Presentation Types';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Conference', 'conference', 'presentation_types');
    }
}
