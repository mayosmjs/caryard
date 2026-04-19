<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use Majos\Sellers\Models\Invoice;
use Majos\Sellers\Classes\SubscriptionService;
use Log;

/**
 * Invoices Backend Controller
 */
class Invoices extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        \BackendMenu::setContext('Majos.Sellers', 'sellers', 'invoices');
    }

    public function onResendReceiptEmail()
    {
        $invoiceId = post('id');
        $invoice = Invoice::find($invoiceId);
        
        if (!$invoice) {
            \Flash::error('Invoice not found');
            return;
        }
        
        try {
            // Use the shared service method to send invoice receipt
            $service = new SubscriptionService();
            $service->sendInvoiceReceipt($invoice);
            
            Log::info('Resending invoice receipt', [
                'invoice_id' => $invoice->id, 
                'invoice_number' => $invoice->invoice_number,
                'email' => $invoice->subscription && $invoice->subscription->seller 
                    ? $invoice->subscription->seller->user->email 
                    : 'unknown'
            ]);
            
            \Flash::success('Receipt email sent successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to resend receipt email: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id ?? null,
                'invoice_number' => $invoice->invoice_number ?? null
            ]);
            \Flash::error('Failed to resend email: ' . $e->getMessage());
        }
    }
}