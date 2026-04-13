<?php namespace Majos\Caryard\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class LoanApplications extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Caryard', 'loan-applications');
    }
}