// M-Pesa Provider JavaScript
// Handles M-Pesa STK Push payment flow

(function() {
    'use strict';

    var PAYMENT_POLL_INTERVAL = 5000; // 5 seconds
    var PAYMENT_POLL_DURATION = 90000; // 90 seconds

    // Show phone modal for M-Pesa
    window.MpesaProvider = {
        showPhoneModal: function(planId, billingCycle) {
            var modalHtml = 
                '<div id="phone-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[1000]" style="display:flex;">' +
                    '<div class="bg-white rounded-2xl p-8 max-w-sm w-full mx-4 shadow-2xl border border-slate-100">' +
                        '<div class="text-center">' +
                            '<div class="mb-4">' +
                                '<div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4">' +
                                    '<svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>' +
                                    '</svg>' +
                                '</div>' +
                            '</div>' +
                            '<h3 class="pricing-header text-xl font-bold text-slate-900 mb-2">M-Pesa Payment</h3>' +
                            '<p class="text-sm text-slate-500 mb-6">Enter your phone number to receive the STK Push notification.</p>' +
                            '<form id="mpesa-phone-form">' +
                                '<input type="hidden" name="plan_id" value="' + planId + '">' +
                                '<input type="hidden" name="billing_cycle" value="' + billingCycle + '">' +
                                '<input type="hidden" name="provider" value="mpesa">' +
                                '<div class="mb-6">' +
                                    '<div class="relative">' +
                                        '<input type="tel" name="phone" id="mpesa-phone-input" ' +
                                            'class="w-full px-4 py-4 text-xl font-bold border border-slate-200 rounded-xl text-center focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all outline-none" ' +
                                            'placeholder="2547XXXXXXXX" ' +
                                            'pattern="[0-9]{12}" ' +
                                            'maxlength="12" ' +
                                            'required>' +
                                    '</div>' +
                                    '<p class="text-[10px] font-bold text-slate-400 uppercase mt-2 tracking-widest text-center">Format: 254712345678</p>' +
                                '</div>' +
                                '<div class="flex flex-col gap-3">' +
                                    '<button type="submit" class="w-full btn-modern btn-amber py-4 text-base">' +
                                        'Send STK Push' +
                                    '</button>' +
                                    '<button type="button" id="cancel-phone-btn" class="w-full btn-modern btn-outline py-3 border-none hover:bg-slate-50">' +
                                        'Cancel' +
                                    '</button>' +
                                '</div>' +
                            '</form>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
            
            // Cancel button handler
            $('#cancel-phone-btn').on('click', function() {
                $('#phone-modal').remove();
            });
        },

        // Show payment pending modal
        showPaymentPendingModal: function(message, transactionId, checkoutRequestId) {
            var modalHtml = 
                '<div id="payment-status-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[1000]" style="display:flex;">' +
                    '<div class="bg-white rounded-2xl p-10 max-w-sm w-full mx-4 shadow-2xl border border-slate-100">' +
                        '<div class="text-center">' +
                            '<div class="mb-6 relative">' +
                                '<div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto pulse-amber">' +
                                    '<svg class="w-10 h-10 text-amber-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
                                    '</svg>' +
                                '</div>' +
                            '</div>' +
                            '<h3 class="pricing-header text-xl font-bold text-slate-900 mb-2">Payment Pending</h3>' +
                            '<p class="text-sm text-slate-500 mb-6">' + message +'</p>' +
                            
                            '<div class="space-y-4 mb-8">' +
                                '<div class="p-4 bg-slate-50 rounded-xl border border-slate-100">' +
                                    '<p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Time Remaining</p>' +
                                    '<p class="text-xl font-black text-amber-600"><span id="poll-countdown">90</span>s</p>' +
                                '</div>' +
                                '<div id="payment-status" class="text-xs font-bold text-amber-500 uppercase tracking-widest animate-pulse">Waiting for M-Pesa...</div>' +
                            '</div>' +
                            
                            '<button id="cancel-payment-btn" class="w-full btn-modern btn-outline py-3 border-none text-slate-400 hover:text-slate-600">' +
                                'Cancel Payment' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
            
            window.pendingPayment = {
                transactionId: transactionId,
                provider: 'mpesa',
                pollInterval: null,
                checkoutRequestId: checkoutRequestId
            };
            
            startPolling();
            
            $('#cancel-payment-btn').on('click', function() {
                stopPolling();
                $('#payment-status-modal').remove();
            });
        }
    };

    // Polling for M-Pesa payment status
    function startPolling() {
        var pollCount = 0;
        var maxPolls = PAYMENT_POLL_DURATION / PAYMENT_POLL_INTERVAL;
        
        window.pendingPayment.pollInterval = setInterval(function() {
            pollCount++;
            var totalSeconds = PAYMENT_POLL_DURATION / 1000;
            var remaining = Math.max(0, totalSeconds - (pollCount * (PAYMENT_POLL_INTERVAL / 1000)));
            $('#poll-countdown').text(Math.ceil(remaining));
            
            $.request('onCheckPaymentStatus', {
                data: {
                    transaction_id: window.pendingPayment.transactionId,
                    provider: 'mpesa'
                },
                success: function(data) {
                    if (data.success && data.status === 'completed') {
                        stopPolling();
                        showPaymentSuccess();
                    } else if (data.success && data.status === 'failed') {
                        stopPolling();
                        showPaymentFailed(data.message);
                    }
                }
            });
            
            if (pollCount >= maxPolls) {
                stopPolling();
                showPaymentTimeout();
            }
        }, PAYMENT_POLL_INTERVAL);
    }

    function stopPolling() {
        if (window.pendingPayment && window.pendingPayment.pollInterval) {
            clearInterval(window.pendingPayment.pollInterval);
        }
    }

    function showPaymentSuccess() {
        $('#payment-status-modal').find('.bg-white').html(
            '<div class="text-center">' +
                '<div class="mb-6">' +
                    '<div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto">' +
                        '<svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
                        '</svg>' +
                    '</div>' +
                '</div>' +
                '<h3 class="pricing-header text-2xl font-bold text-slate-900 mb-2">Success!</h3>' +
                '<p class="text-sm text-slate-500 mb-8">Your subscription has been successfully activated. Redirecting you now...</p>' +
                '<button onclick="window.location.href=\'/account/subscription\'" class="w-full btn-modern btn-amber py-4">' +
                    'Continue to Dashboard' +
                '</button>' +
            '</div>'
        );
        setTimeout(function() { window.location.href = '/account/subscription'; }, 2000);
    }

    function showPaymentFailed(message) {
        $('#payment-status-modal').find('.bg-white').html(
            '<div class="text-center">' +
                '<div class="mb-4">' +
                    '<svg class="w-16 h-16 mx-auto text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>' +
                    '</svg>' +
                '</div>' +
                '<h3 class="text-lg font-bold text-gray-900 mb-2">Payment Failed</h3>' +
                '<p class="text-sm text-gray-600 mb-4">' + (message || 'Your payment could not be processed.') + '</p>' +
                '<button id="close-failed-modal" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">' +
                    'Close' +
                '</button>' +
            '</div>'
        );
        $('#close-failed-modal').on('click', function() {
            $('#payment-status-modal').remove();
        });
    }

    function showPaymentTimeout() {
        $('#payment-status-modal').find('#payment-status').html('<span class="text-red-600">Payment not received</span>');
        $('#payment-status-modal').find('#cancel-payment-btn').text('Close').on('click', function() {
            $('#payment-status-modal').remove();
        });
    }

})();