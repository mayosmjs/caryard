// Subscription Manager JavaScript
// Coordinates payment provider loading and interactions

(function() {
    'use strict';

    // Global state
    window.subscriptionBtnState = {
        btn: null,
        originalText: ''
    };

    // Provider loader - loads from same directory
    var providersLoaded = {
        stripe: false,
        paypal: false,
        mpesa: false
    };

    // Load a provider script from component directory
    function loadProviderScript(provider) {
        if (providersLoaded[provider]) return Promise.resolve();
        
        var scriptPath = '';
        switch(provider) {
            case 'stripe': scriptPath = 'stripe.js'; break;
            case 'paypal': scriptPath = 'paypal.js'; break;
            case 'mpesa': scriptPath = 'mpesa.js'; break;
            default: return Promise.reject(new Error('Unknown provider: ' + provider));
        }
        
        // Get base URL (removed versioning to prevent caching issues)
        var baseUrl = window.sellerAssetPath ? window.sellerAssetPath + '/' : '';
        
        if (!baseUrl) {
            // Fallback to legacy detection if global not set
            var currentScript = document.querySelector('script[src*="subscription.js"]');
            if (currentScript) {
                var src = currentScript.src;
                var lastSlash = src.lastIndexOf('/');
                baseUrl = src.substring(0, lastSlash + 1);
            }
        }
        
        return new Promise(function(resolve, reject) {
            var fullPath = baseUrl + scriptPath;
            var existing = document.querySelector('script[src*="' + scriptPath + '"]');
            
            function onLoaded() {
                console.log('Script loaded for provider:', provider, 'fullPath:', fullPath);
                providersLoaded[provider] = true;
                // Double check the global object is available
                var providerObjName = provider.charAt(0).toUpperCase() + provider.slice(1) + 'Provider';
                console.log('Looking for global:', providerObjName, 'current value:', window[providerObjName]);
                // Poll to ensure the provider is fully initialized
                var maxAttempts = 10;
                var attempts = 0;
                function checkProvider() {
                    attempts++;
                    console.log('Check attempt:', attempts, 'for', providerObjName, 'window value:', window[providerObjName]);
                    if (window[providerObjName]) {
                        resolve();
                    } else if (attempts < maxAttempts) {
                        setTimeout(checkProvider, 50);
                    } else {
                        console.warn(providerObjName + ' still not found after multiple attempts');
                        resolve(); // Resolve anyway to not block, but error will occur later
                    }
                }
                checkProvider();
            }

            if (existing) {
                if (providersLoaded[provider]) {
                    resolve();
                } else {
                    // Script exists but not marked as loaded yet - wait for it
                    existing.addEventListener('load', onLoaded);
                    existing.addEventListener('error', function() { reject(new Error('Failed to load ' + scriptPath)); });
                    
                    // If it was already loaded but our flag is false
                    var providerObjName = provider.charAt(0).toUpperCase() + provider.slice(1) + 'Provider';
                    if (window[providerObjName]) onLoaded();
                }
                return;
            }
            
            var s = document.createElement('script');
            s.type = 'text/javascript';
            s.src = fullPath;
            s.crossOrigin = 'anonymous';
            s.onload = onLoaded;
            s.onerror = function(e) {
                console.error('Failed to load ' + scriptPath, e);
                reject(new Error('Failed to load ' + scriptPath));
            };
            document.head.appendChild(s);
        });
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        console.log('Document ready - initializing subscription forms');
        initSubscriptionForms();
    });

    function initSubscriptionForms() {
        // Intercept form submission
        $(document).on('submit', '.subscription-checkout-form', function(e) {
            e.preventDefault();
            var form = $(this);
            
            // Check if this is a free plan
            var isFree = form.attr('data-free') === '1' || form.find('input[name="_free"]').val() === '1';
            
            // Get provider (not needed for free plans)
            var provider = null;
            if (!isFree) {
                provider = getProviderFromForm(form);
                if (!provider) {
                    alert('Please select a payment method.');
                    return;
                }
            }
            
            // Store button reference
            var btn = form.find('button[type="submit"]');
            window.subscriptionBtnState.btn = btn;
            window.subscriptionBtnState.originalText = btn.text();
            
            var planId = form.find('input[name="plan_id"]').val();
            var billingCycle = form.find('input[name="billing_cycle"]').val() || 'monthly';
            
            // Delegate to provider or start free plan directly
            if (isFree) {
                // Free plan - submit directly without provider
                btn.prop('disabled', true).text('Processing...');
                $.request('onSubscribe', {
                    data: { plan_id: planId, billing_cycle: billingCycle, provider: 'free' },
                    success: handleSubscribeSuccess,
                    complete: function() {
                        resetButton(btn);
                    }
                });
            } else {
                switch(provider) {
                    case 'stripe':
                        btn.prop('disabled', true).text('Processing...');
                        loadProviderScript('stripe').then(function() {
                            $.request('onSubscribe', {
                                data: { plan_id: planId, billing_cycle: billingCycle, provider: 'stripe' },
                                success: handleSubscribeSuccess,
                                complete: function() {
                                    resetButton(btn);
                                }
                            });
                        });
                        break;
                        
                    case 'paypal':
                        btn.prop('disabled', true).text('Processing...');
                        loadProviderScript('paypal').then(function() {
                            $.request('onSubscribe', {
                                data: { plan_id: planId, billing_cycle: billingCycle, provider: 'paypal' },
                                success: handleSubscribeSuccess,
                                complete: function() {
                                    resetButton(btn);
                                }
                            });
                        });
                        break;
                        
                    case 'mpesa':
                        // Show phone modal via MpesaProvider (ensure script is loaded first)
                        loadProviderScript('mpesa').then(function() {
                            // Additional safety check - ensure provider is fully initialized
                            if (!window.MpesaProvider || typeof window.MpesaProvider.showPhoneModal !== 'function') {
                                console.error('MpesaProvider not properly initialized');
                                alert('M-Pesa payment provider failed to load. Please refresh and try again.');
                                resetButton(btn);
                                return;
                            }
                            window.MpesaProvider.showPhoneModal(planId, billingCycle);
                        })['catch'](function(err) {
                            console.error('Failed to load M-Pesa provider:', err);
                            alert('Could not initialize M-Pesa payment. Please try again.');
                        });
                        break;
                }
            }
        });

        // Handle M-Pesa phone form submission (delegated)
        $(document).on('submit', '#mpesa-phone-form', function(e) {
            e.preventDefault();
            var phone = $('#mpesa-phone-input').val();
            var planId = $(this).find('input[name="plan_id"]').val();
            var billingCycle = $(this).find('input[name="billing_cycle"]').val();
            
            if (phone.length !== 12) {
                alert('Please enter a valid 12-digit phone number starting with 254');
                return;
            }
            
            $('#phone-modal').remove();
            
            if (window.subscriptionBtnState.btn) {
                window.subscriptionBtnState.btn.prop('disabled', true).text('Sending STK Push...');
            }
            
            $.request('onSubscribe', {
                data: {
                    plan_id: planId,
                    billing_cycle: billingCycle,
                    provider: 'mpesa',
                    phone: phone
                },
                success: handleSubscribeSuccess,
                complete: function() {
                    if (window.subscriptionBtnState.btn) {
                        resetButton(window.subscriptionBtnState.btn);
                    }
                }
            });
        });

        // Handle trial button
        $(document).on('click', 'button[data-request="onStartTrial"]', function() {
            var btn = $(this);
            var originalText = btn.text();
            btn.prop('disabled', true).text('Starting Trial...');
            setTimeout(function() {
                btn.prop('disabled', false).text(originalText);
            }, 10000);
        });

        // Handle cancel
        $(document).on('click', 'button[data-request="onCancelSubscription"]', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('Cancelling...');
            setTimeout(function() {
                btn.prop('disabled', false).text('Cancel Subscription');
            }, 10000);
        });

        initPricingToggle();
    }

    function getProviderFromForm(form) {
        var providerHidden = form.find('input#selected-provider');
        var providerRadio = form.find('input[name="provider"]:checked');
        var providerSelect = form.find('select[name="provider"]');
        
        if (providerHidden && providerHidden.val()) {
            return providerHidden.val();
        } else if (providerRadio && providerRadio.length > 0) {
            return providerRadio.val();
        } else if (providerSelect && providerSelect.length > 0) {
            return providerSelect.val();
        }
        return null;
    }

    function resetButton(btn) {
        if (btn) {
            btn.prop('disabled', false).text(window.subscriptionBtnState.originalText);
        }
    }

    function initPricingToggle() {
        $(document).on('change', '#billing-cycle-toggle', function() {
            var isAnnual = $(this).is(':checked');
            var cycle = isAnnual ? 'annual' : 'monthly';
            
            $('input[name="billing_cycle"]').val(cycle);
            
            if (isAnnual) {
                $('.price-monthly').hide();
                $('.price-annual').fadeIn();
                $('.billing-label-monthly').removeClass('text-slate-900 font-bold').addClass('text-slate-400');
                $('.billing-label-annual').removeClass('text-slate-400').addClass('text-slate-900 font-bold');
            } else {
                $('.price-annual').hide();
                $('.price-monthly').fadeIn();
                $('.billing-label-annual').removeClass('text-slate-900 font-bold').addClass('text-slate-400');
                $('.billing-label-monthly').removeClass('text-slate-400').addClass('text-slate-900 font-bold');
            }
        });
    }

    // Handle subscription success - delegates to appropriate provider
    window.handleSubscribeSuccess = function(data) {
        console.log('handleSubscribeSuccess:', data);
        
        // Handle free plan/success without transaction_id
        if (data && data.success && !data.transaction_id) {
            window.location.reload();
            return;
        }
        
        if (data && data.success && data.transaction_id) {
            // Check for Stripe flow
            if (data.data && data.data.client_secret) {
                loadProviderScript('stripe').then(function() {
                    if (!window.StripeProvider || typeof window.StripeProvider.showCardModal !== 'function') {
                        console.error('StripeProvider not properly initialized');
                        alert('Stripe payment provider failed to load. Please refresh and try again.');
                        return;
                    }
                    window.StripeProvider.showCardModal(data.data.client_secret, data.transaction_id);
                });
                return;
            }

            // Check for PayPal flow
            if (data.redirectUrl) {
                loadProviderScript('paypal').then(function() {
                    if (!window.PayPalProvider || typeof window.PayPalProvider.redirectToCheckout !== 'function') {
                        console.error('PayPalProvider not properly initialized');
                        alert('PayPal payment provider failed to load. Please refresh and try again.');
                        return;
                    }
                    window.PayPalProvider.redirectToCheckout(data.redirectUrl);
                });
                return;
            }

            // M-Pesa flow
            if (data.data && data.data.checkout_request_id) {
                loadProviderScript('mpesa').then(function() {
                    if (!window.MpesaProvider || typeof window.MpesaProvider.showPaymentPendingModal !== 'function') {
                        console.error('MpesaProvider not properly initialized');
                        alert('M-Pesa payment provider failed to load. Please refresh and try again.');
                        return;
                    }
                    window.MpesaProvider.showPaymentPendingModal(
                        data.message || 'STK Push sent', 
                        data.transaction_id, 
                        data.data.checkout_request_id
                    );
                });
                return;
            }

            // Default success message
            alert(data.message || 'Payment initiated successfully!');
            resetButton(window.subscriptionBtnState.btn);
        } else if (data && data.success === false) {
            alert(data.message || 'Payment failed');
            resetButton(window.subscriptionBtnState.btn);
        }
    };

})();
