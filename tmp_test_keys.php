<?php
require_once 'includes/db.php';
require_once 'includes/xendit_config.php';

echo "Testing Key 1: " . XENDIT_SECRET_KEY . "\n";
$res1 = xendit_generate_payment_link(2277, 800, 'test@example.com', '');
print_r($res1);

// Test alternative key
$alt_key = 'xnd_development_QKQR9DF4sA9Xq2w1VFvxtVa5tDLHcYxj276FSlyPNX6ROuE9YAQGIASMpMUJ';
echo "\nTesting Key 2: $alt_key\n";

function custom_xendit_test($key, $orderId, $amount) {
    $url = XENDIT_INVOICE_URL;
    $ch = curl_init();
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($key . ':')
    ];
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    $payload = [
        'external_id' => 'order_' . $orderId,
        'amount' => $amount,
        'description' => 'Test',
        'invoice_duration' => 86400,
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

print_r(custom_xendit_test($alt_key, 2277, 800));

?>
