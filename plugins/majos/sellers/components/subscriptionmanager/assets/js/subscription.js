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
            case 'stripe':
                scriptPath = 'stripe.js';
                break;
            case 'paypal':
                scriptPath = 'paypal.js';
                break;
            case 'mpesa':
                scriptPath = 'mpesa.js';
                break;
            default:
                return Promise.reject(new Error('Unknown provider: ' + provider));
        }
        
        // Get base URL from current script
        var baseUrl = '';
        var currentScript = document.querySelector('script[src*="subscription.js"]');
        if (currentScript) {
            var src = currentScript.src;
            var lastSlash = src.lastIndexOf('/');
            baseUrl = src.substring(0, lastSlash + 1);
        }
        
        return new Promise(function(resolve, reject) {
            var existing = document.querySelector('script[src*="' + scriptPath + '"]');
            if (existing) {
                providersLoaded[provider] = true;
                resolve();
                return;
            }
            
            var s = document.createElement('script');
            s.type = 'text/javascript';
            s.src = baseUrl + scriptPath;
            s.onload = function() {
                providersLoaded[provider] = true;
                resolve();
            };
            s.onerror = function() {
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
            console.log('isFree:', isFree, 'data-free attr:', form.attr('data-free'), '_free input:', form.find('input[name="_free"]').val());
            
            // Get provider (not needed for free plans)
            var provider = null;
            if (!isFree) {
                provider = getProviderFromForm(form);
                if (!provider) {
                    alert('Please select a payment method.');
                    return;
                }
            }
            
            console.log('Form submitted, provider:', provider);
            
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
            } else switch(provider) {
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
                    // Show phone modal via MpesaProvider
                    window.MpesaProvider.showPhoneModal(planId, billingCycle);
                    break;
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

        console.log('Subscription forms initialized');
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
            const isAnnual = $(this).is(':checked');
            const cycle = isAnnual ? 'annual' : 'monthly';
            
            console.log('Billing cycle changed to:', cycle);
            
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
            console.log('Free plan activated - reloading page');
            window.location.reload();
            return;
        }
        
        if (data && data.success && data.transaction_id) {
            // Check for Stripe flow
            if (data.data && data.client_secret) {
                loadProviderScript('stripe').then(function() {
                    window.StripeProvider.showCardModal(data.client_secret, data.transaction_id);
                });
                return;
            }

            // Check for PayPal flow
            if (data.redirectUrl) {
                loadProviderScript('paypal').then(function() {
                    window.PayPalProvider.redirectToCheckout(data.redirectUrl);
                });
                return;
            }

            // M-Pesa flow
            if (data.data && data.checkout_request_id) {
                loadProviderScript('mpesa').then(function() {
                    window.MpesaProvider.showPaymentPendingModal(
                        data.message || 'STK Push sent', 
                        data.transaction_id, 
                        data.checkout_request_id
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
