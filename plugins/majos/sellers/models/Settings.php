<?php namespace Majos\Sellers\Models;

use System\Models\SettingModel as SettingModelBase;

/**
 * Settings Model
 * Stores payment provider configurations using October's settings system
 */
class Settings extends SettingModelBase
{
    /**
     * @var string Settings code for the system_settings table
     */
    public $settingsCode = 'majos_sellers_settings';

    /**
     * @var string Fields configuration
     */
    public $settingsFields = 'fields.yaml';
    
    /**
     * @var array Attachments - logo uploads
     */
    public $attachOne = [
        'mpesa_logo' => 'System\Models\File',
        'paypal_logo' => 'System\Models\File',
        'stripe_logo' => 'System\Models\File',
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [];

    /**
     * Check if M-Pesa is enabled and configured
     */
    public function isMpesaEnabled(): bool
    {
        return !empty($this->mpesa_enabled) 
            && !empty($this->mpesa_consumer_key)
            && !empty($this->mpesa_consumer_secret)
            && !empty($this->mpesa_shortcode);
    }

    /**
     * Check if PayPal is enabled and configured
     */
    public function isPayPalEnabled(): bool
    {
        return !empty($this->paypal_enabled)
            && !empty($this->paypal_client_id)
            && !empty($this->paypal_secret);
    }

    /**
     * Check if Stripe is enabled and configured
     */
    public function isStripeEnabled(): bool
    {
        return !empty($this->stripe_enabled)
            && !empty($this->stripe_api_key);
    }

    /**
     * Check if Stripe Connect is enabled
     */
    public function isStripeConnectEnabled(): bool
    {
        return !empty($this->stripe_connect_enabled) && $this->isStripeEnabled();
    }

    /**
     * Get available payment providers
     */
    public function getAvailableProviders(): array
    {
        $providers = [];
        
        if ($this->isMpesaEnabled()) {
            $providers[] = 'mpesa';
        }
        
        if ($this->isPayPalEnabled()) {
            $providers[] = 'paypal';
        }
        
        if ($this->isStripeEnabled()) {
            $providers[] = 'stripe';
        }

        return $providers;
    }

    /**
     * Get M-Pesa configuration
     */
    public function getMpesaConfig(): array
    {
        return [
            'environment' => $this->mpesa_environment ?? 'sandbox',
            'consumer_key' => $this->mpesa_consumer_key ?? '',
            'consumer_secret' => $this->mpesa_consumer_secret ?? '',
            'business_short_code' => $this->mpesa_shortcode ?? '',
            'passkey' => $this->mpesa_passkey ?? '',
            'initiator_name' => $this->mpesa_initiator_name ?? '',
            'security_credential' => $this->mpesa_security_credential ?? '',
            'callback_url' => $this->mpesa_callback_url ?? url('/api/mpesa/callback'),
        ];
    }

    /**
     * Get PayPal configuration
     */
    public function getPayPalConfig(): array
    {
        return [
            'environment' => $this->paypal_environment ?? 'sandbox',
            'client_id' => $this->paypal_client_id ?? '',
            'secret' => $this->paypal_secret ?? '',
            'webhook_id' => $this->paypal_webhook_id ?? '',
            'return_url' => $this->paypal_return_url ?? url('/subscription/paypal/return'),
            'cancel_url' => $this->paypal_cancel_url ?? url('/subscription/paypal/cancel'),
        ];
    }

    /**
     * Get Stripe configuration
     */
    public function getStripeConfig(): array
    {
        return [
            'environment' => $this->stripe_environment ?? 'test',
            'api_key' => $this->stripe_api_key ?? '',
            'publishable_key' => $this->stripe_publishable_key ?? '',
            'webhook_secret' => $this->stripe_webhook_secret ?? '',
            'connect_enabled' => $this->stripe_connect_enabled ?? false,
            'return_url' => $this->stripe_return_url ?? url('/subscription/stripe/return'),
            'cancel_url' => $this->stripe_cancel_url ?? url('/subscription/stripe/cancel'),
        ];
    }
}
