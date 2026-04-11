<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Majos\Sellers\Models\SubscriptionTransaction;

/**
 * Subscription Transactions Controller
 */
class SubscriptionTransactions extends Controller
{
    public $requiredPermissions = ['majos.sellers.view_transactions'];

    public $implement = [
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.FormController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Sellers', 'sellers', 'subscriptiontransactions');
    }

    /**
     * Get provider options for filtering
     */
    public function getProviderOptions()
    {
        return [
            'mpesa' => 'M-Pesa',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            '' => 'All Providers',
        ];
    }

    /**
     * Get status options for filtering
     */
    public function getStatusOptions()
    {
        return [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
        ];
    }
}