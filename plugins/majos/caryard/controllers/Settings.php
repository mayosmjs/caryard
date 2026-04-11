<?php namespace Majos\Caryard\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;

/**
 * Settings Controller
 *
 * @package majos.caryard
 * @author Majos
 */
class Settings extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
    ];

    public $formConfig = 'config_form.yaml';

    public $bodyClass = 'compact-container';

    public $requiredPermissions = [
        'majos.caryard.settings',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->pageTitle = 'CarYard Settings';
        
        return $this->makePartial('settings_index');
    }

    public function index_onSave()
    {
        $model = \Majos\Caryard\Models\Settings::instance();
        $model->fill(post());
        $model->save();

        \Flash::success('Settings saved successfully!');
        
        return $this->refresh();
    }
}
