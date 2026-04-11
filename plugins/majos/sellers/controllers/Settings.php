<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Majos\Sellers\Models\Settings as SettingsModel;

/**
 * Settings Controller
 */
class Settings extends Controller
{
    public $requiredPermissions = ['majos.sellers.manage_settings'];

    public $implement = [
        'Backend.Behaviors.SettingsController',
    ];

    public $settingsModel = SettingsModel::class;
    public $settingsFile = 'config.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Sellers', 'sellers', 'settings');
    }
}
