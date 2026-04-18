// PayPal Provider JavaScript
// Handles PayPal payment flow

(function() {
    'use strict';

    // Handle PayPal redirect
    window.PayPalProvider = {
        redirectToCheckout: function(redirectUrl) {
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return true;
            }
            return false;
        },
        
        // Show PayPal processing modal
        showProcessingModal: function() {
            var modalHtml = `
                <div id="paypal-processing-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[1000]" style="display:flex;">
                    <div class="bg-white rounded-2xl p-10 max-w-sm w-full mx-4 shadow-2xl border border-slate-100">
                        <div class="text-center">
                            <div class="mb-6">
                                <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto pulse-blue">
                                    <svg class="w-10 h-10 text-blue-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-7v2a2 2 0 01-2 2H9a2 2 0 01-2-2V8a2 2 0 012-2h2m4 0h2a2 2 0 012 2v2"></path>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-2">Redirecting to PayPal</h3>
                            <p class="text-sm text-slate-500 mb-8">Please complete your payment on PayPal's secure page.</p>
                            <div class="animate-pulse text-blue-500 font-medium">Loading...</div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        },
        
        // Handle returning from PayPal
        handleReturn: function() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1' || urlParams.get('payment_status') === 'completed') {
                window.location.href = '/account/subscription';
            }
        },
        
        // Handle PayPal cancellation
        handleCancel: function() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('cancelled') === '1') {
                $('#paypal-processing-modal').remove();
                alert('Payment was cancelled. Please try again.');
            }
        }
    };

})();