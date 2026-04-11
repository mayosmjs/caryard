<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Majos\Sellers\Models\SubscriptionPlan;

/**
 * Subscription Plans Controller
 */
class SubscriptionPlans extends Controller
{
    public $requiredPermissions = ['majos.sellers.manage_plans'];

    public $implement = [
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.FormController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Sellers', 'sellers', 'subscriptionplans');
    }

    /**
     * Extend index to add reorder
     */
    public function index_onDelete()
    {
        if (($checkedIds = post('checked')) && is_array($checkedIds)) {
            foreach ($checkedIds as $recordId) {
                if (($record = SubscriptionPlan::find($recordId))) {
                    $record->delete();
                }
            }
        }

        return $this->listRefresh();
    }

    /**
     * Get available tiers
     */
    public function getTierOptions()
    {
        return [
            'trial' => 'Trial',
            'basic' => 'Basic',
            'premium' => 'Premium',
        ];
    }
}