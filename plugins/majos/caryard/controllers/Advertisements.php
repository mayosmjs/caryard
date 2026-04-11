<?php namespace Majos\Caryard\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Advertisements Backend Controller
 */
class Advertisements extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    /**
     * @var string formConfig file
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string listConfig file
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var string reorderConfig file
     */
    public $reorderConfig = 'config_reorder.yaml';

    /**
     * @var array required permissions
     */
    public $requiredPermissions = ['majos.caryard.access_advertisements'];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Majos.Caryard', 'caryard', 'advertisements');
    }
}
