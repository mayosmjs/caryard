<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * SellerProfiles Backend Controller
 */
class SellerProfiles extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\DeleteController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Sellers', 'sellers', 'sellerprofiles');
    }
}