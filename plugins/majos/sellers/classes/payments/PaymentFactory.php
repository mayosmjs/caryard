<?php namespace Majos\Sellers\Classes\Payments;

use Exception;

/**
 * Payment Factory
 * Factory pattern to create payment provider instances
 */
class PaymentFactory
{
    /**
     * Registered providers
     */
    protected static $providers = [
        'mpesa' => MpesaProvider::class,
        'paypal' => PayPalProvider::class,
        'stripe' => StripeProvider::class,
    ];

    /**
     * Get available providers
     */
    public static function getAvailableProviders(): array
    {
        return array_keys(self::$providers);
    }

    /**
     * Create a payment provider instance
     *
     * @param string $provider Provider name (mpesa, paypal, stripe)
     * @param array $config Provider configuration
     * @return PaymentProviderInterface
     * @throws Exception
     */
    public static function make(string $provider, array $config = []): PaymentProviderInterface
    {
        // Handle undefined or empty provider
        if (empty($provider) || $provider === 'undefined' || $provider === 'null') {
            throw new Exception("Payment provider not specified. Please select a payment method.");
        }
        
        // Handle free plan - no provider needed
        if ($provider === 'free') {
            throw new Exception("No payment provider required for free plan.");
        }
        
        $provider = strtolower($provider);

        if (!isset(self::$providers[$provider])) {
            throw new Exception("Payment provider '{$provider}' not supported. Available: " . implode(', ', self::$providers));
        }

        $providerClass = self::$providers[$provider];
        
        /** @var PaymentProviderInterface $instance */
        $instance = new $providerClass();
        
        // Initialize with config if provided
        if (!empty($config)) {
            $initialized = $instance->initialize($config);
            if (!$initialized) {
                throw new Exception("Failed to initialize {$provider} provider with provided configuration");
            }
        }

        return $instance;
    }

    /**
     * Create a payment provider from settings
     *
     * @param string $provider Provider name
     * @return PaymentProviderInterface
     * @throws Exception
     */
    public static function makeFromSettings(string $provider): ?PaymentProviderInterface
    {
        // Handle free plan - no provider needed
        if ($provider === 'free') {
            return null;
        }
        $config = self::getSettingsConfig($provider);
        return self::make($provider, $config);
    }

    /**
     * Get configuration from system settings
     */
    public static function getSettingsConfig(string $provider): array
    {
        $config = [];
        
        // Load from OctoberCMS settings
        // This would typically come from the plugin's settings
        $settings = \Majos\Sellers\Models\Settings::instance();
        
        switch ($provider) {
            case 'mpesa':
                $config = [
                    'environment' => $settings->mpesa_environment ?? 'sandbox',
                    'consumer_key' => $settings->mpesa_consumer_key ?? '',
                    'consumer_secret' => $settings->mpesa_consumer_secret ?? '',
                    'business_short_code' => $settings->mpesa_shortcode ?? '',
                    'passkey' => $settings->mpesa_passkey ?? '',
                    'callback_url' => !empty($settings->mpesa_callback_url) ? $settings->mpesa_callback_url : (strpos(url('/'), '127.0.0.1') !== false || strpos(url('/'), 'localhost') !== false || strpos(url('/'), 'trycloudflare.com') !== false ? 'https://example.com/api/mpesa/callback' : secure_url('/api/mpesa/callback')),
                    'initiator_name' => $settings->mpesa_initiator_name ?? '',
                    'security_credential' => $settings->mpesa_security_credential ?? '',
                ];
                break;

            case 'paypal':
                $config = [
                    'environment' => $settings->paypal_environment ?? 'sandbox',
                    'client_id'  => $settings->paypal_client_id ?? '',
                    'secret'     => $settings->paypal_secret ?? '',
                    'return_url' => $settings->paypal_return_url ?? url('/subscription/paypal/return'),
                    'cancel_url' => $settings->paypal_cancel_url ?? url('/subscription/paypal/cancel'),
                ];
                break;

            case 'stripe':
                $config = [
                    'api_key' => $settings->stripe_api_key ?? '',
                    'publishable_key' => $settings->stripe_publishable_key ?? '',
                    'webhook_secret' => $settings->stripe_webhook_secret ?? '',
                ];
                break;
        }

        // Filter out empty values
        return array_filter($config, function($value) {
            return $value !== '' && $value !== null;
        });
    }

    /**
     * Register a custom provider
     */
    public static function registerProvider(string $name, string $class): void
    {
        if (!is_a($class, PaymentProviderInterface::class, true)) {
            throw new Exception("Provider class must implement PaymentProviderInterface");
        }

        self::$providers[strtolower($name)] = $class;
    }

    /**
     * Check if a provider is available
     */
    public static function isProviderAvailable(string $provider): bool
    {
        return isset(self::$providers[strtolower($provider)]);
    }

    /**
     * Get provider class
     */
    public static function getProviderClass(string $provider): ?string
    {
        return self::$providers[strtolower($provider)] ?? null;
    }

    /**
     * Validate all configured providers
     */
    public static function validateAllProviders(): array
    {
        $results = [];
        
        foreach (self::$providers as $name => $class) {
            try {
                $config = self::getSettingsConfig($name);
                $instance = self::make($name, $config);
                $results[$name] = [
                    'available' => true,
                    'configured' => $instance->validateConfig(),
                    'error' => null,
                ];
            } catch (\Exception $e) {
                $results[$name] = [
                    'available' => true,
                    'configured' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}