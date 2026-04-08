<?php
/**
 * Xendit Payment Gateway Configuration
 */

// Load Xendit Credentials from settings
$xendit_cfg_path = __DIR__ . '/xendit_config.json';
$xendit_cfg = file_exists($xendit_cfg_path) ? json_decode(file_get_contents($xendit_cfg_path), true) : [];

// Xendit Secret Key
define('XENDIT_SECRET_KEY', $xendit_cfg['secret_key'] ?? 'xnd_development_QKQR9DF4sA9Xq2w1VFvxtVa5tDLHcYxj276FSlyPNX6ROuE9YAQGIASMpMUJ');

// Callback Token for Webhook Validation
define('XENDIT_CALLBACK_TOKEN', $xendit_cfg['callback_token'] ?? '');

// Xendit Status Flag
define('XENDIT_ENABLED', (bool)($xendit_cfg['is_enabled'] ?? true));

// Base URL for Xendit Invoice API
define('XENDIT_INVOICE_URL', 'https://api.xendit.co/v2/invoices');

/**
 * Helper function to call Xendit API
 */
function xendit_api_call($method, $url, $data = null) {
    if (!$url) {
        $url = XENDIT_INVOICE_URL;
    }
    
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(XENDIT_SECRET_KEY . ':')
    ];
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log('Xendit Curl Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($http_code >= 400) {
        error_log('Xendit API Error: ' . $response);
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Unknown error',
            'error_code' => $result['error_code'] ?? 'UNKNOWN'
        ];
    }
    
    return [
        'success' => true,
        'data' => $result
    ];
}

/**
 * Generate a payment link for an order
 */
function xendit_generate_payment_link($orderId, $amount, $customerEmail = '', $customerPhone = '') {
    // Determine redirect URL based on app structure (adjust if needed)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '/printflow'; // Adjust if root
    $successUrl = "{$protocol}://{$host}{$basePath}/customer/order_details.php?id={$orderId}";

    $payload = [
        'external_id' => 'order_' . $orderId,
        'amount' => $amount,
        'description' => 'Payment for PrintFlow order #' . $orderId,
        'invoice_duration' => 86400, // 24 hours
        'customer' => [
            'email' => $customerEmail ?: 'customer@example.com',
            'mobile_number' => $customerPhone ?: null,
        ],
        'items' => [
            [
                'name' => 'PrintFlow Order Payment',
                'quantity' => 1,
                'price' => $amount
            ]
        ],
        'success_redirect_url' => $successUrl,
        'failure_redirect_url' => "{$protocol}://{$host}{$basePath}/customer/order_details.php?id={$orderId}"
    ];
    
    return xendit_api_call('POST', XENDIT_INVOICE_URL, $payload);
}
