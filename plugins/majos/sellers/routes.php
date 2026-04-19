<?php

use Illuminate\Support\Facades\Route;

Route::post('/api/stripe/webhook', [\Majos\Sellers\Controllers\StripeWebhookController::class, 'handle']);
Route::post('/api/mpesa/callback', [\Majos\Sellers\Controllers\MpesaWebhookController::class, 'handle']);

// NEW: Direct verification endpoint with PayPal API fallback
Route::get('/subscription/paypal/verify', [\Majos\Sellers\Controllers\PayPalController::class, 'verify']);

// Processing page with polling - shows "Processing" state
Route::get('/subscription/paypal/processing-page', [\Majos\Sellers\Controllers\PayPalController::class, 'processing']);

// Original return route - now redirects to processing page
Route::get('/subscription/paypal/return', [\Majos\Sellers\Controllers\PayPalController::class, 'handleReturn']);

Route::get('/subscription/paypal/cancel', [\Majos\Sellers\Controllers\PayPalController::class, 'handleCancel']);

Route::post('/api/paypal/webhook', [\Majos\Sellers\Controllers\PayPalWebhookController::class, 'handle']);
