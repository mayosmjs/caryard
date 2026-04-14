// Stripe Provider JavaScript
// Handles Stripe payment form interactions

(function() {
    'use strict';

    var stripe = null;
    var stripeLoaded = false;

    // Load Stripe.js dynamically
    function loadStripeJS() {
        if (stripeLoaded) return Promise.resolve();
        
        return new Promise(function(resolve, reject) {
            if (typeof Stripe !== 'undefined') {
                stripeLoaded = true;
                resolve();
                return;
            }
            
            var s = document.createElement('script');
            s.type = 'text/javascript';
            s.src = 'https://js.stripe.com/v3/';
            s.onload = function() {
                stripeLoaded = true;
                resolve();
            };
            s.onerror = function() {
                reject(new Error('Failed to load Stripe.js'));
            };
            document.head.appendChild(s);
        });
    }

    // Initialize Stripe with publishable key
    function initStripe() {
        if (!window.stripePubKey) {
            console.error('Stripe publishable key not set');
            return null;
        }
        return Stripe(window.stripePubKey);
    }

    // Show Stripe card modal and process payment
    window.StripeProvider = {
        showCardModal: function(clientSecret, transactionId) {
            loadStripeJS().then(function() {
                if (!stripe && window.stripePubKey) {
                    stripe = initStripe();
                }
                
                if (!stripe) {
                    alert('Failed to initialize Stripe. Please refresh and try again.');
                    return;
                }

                var elements = stripe.elements();
                var card = elements.create('card', {
                    style: {
                        base: {
                            color: '#0f172a',
                            fontFamily: '"Inter", sans-serif',
                            fontSmoothing: 'antialiased',
                            fontSize: '16px',
                            '::placeholder': {
                                color: '#94a3b8'
                            }
                        },
                        invalid: {
                            color: '#ef4444',
                            iconColor: '#ef4444'
                        }
                    }
                });

                var modalHtml = 
                    '<div id="stripe-card-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[1000]" style="display:flex;">' +
                        '<div class="bg-white rounded-2xl p-8 max-w-sm w-full mx-4 shadow-2xl border border-slate-100">' +
                            '<div class="text-center">' +
                                '<div class="mb-4">' +
                                    '<div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">' +
                                        '<svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>' +
                                        '</svg>' +
                                    '</div>' +
                                '</div>' +
                                '<h3 class="pricing-header text-xl font-bold text-slate-900 mb-2">Card Payment</h3>' +
                                '<p class="text-sm text-slate-500 mb-6">Enter your card details to complete payment.</p>' +
                                '<form id="stripe-card-form">' +
                                    '<input type="hidden" name="transaction_id" value="' + transactionId + '">' +
                                    '<div class="mb-6">' +
                                        '<div id="card-element" class="px-4 py-4 border border-slate-200 rounded-xl"></div>' +
                                        '<p id="card-errors" class="text-red-500 text-sm mt-2"></p>' +
                                    '</div>' +
                                    '<div class="flex flex-col gap-3">' +
                                        '<button type="submit" class="w-full btn-modern btn-blue py-4 text-base">' +
                                            'Pay Now' +
                                        '</button>' +
                                        '<button type="button" id="cancel-stripe-btn" class="w-full btn-modern btn-outline py-3 border-none hover:bg-slate-50">' +
                                            'Cancel' +
                                        '</button>' +
                                    '</div>' +
                                '</form>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                
                $('body').append(modalHtml);
                
                card.mount('#card-element');
                
                // Handle form submit
                $('#stripe-card-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var btn = $(this).find('button[type="submit"]');
                    btn.prop('disabled', true).text('Processing...');
                    
                    stripe.confirmCardPayment(clientSecret, {
                        payment_method: {
                            card: card
                        }
                    }).then(function(result) {
                        if (result.error) {
                            $('#card-errors').text(result.error.message);
                            btn.prop('disabled', false).text('Pay Now');
                        } else {
                            $('#stripe-card-modal').remove();
                            window.location.href = '/account/subscription';
                        }
                    });
                });
                
                // Cancel button
                $('#cancel-stripe-btn').on('click', function() {
                    $('#stripe-card-modal').remove();
                });
            }).catch(function(err) {
                alert('Failed to load Stripe: ' + err.message);
            });
        }
    };

})();