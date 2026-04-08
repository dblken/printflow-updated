<?php
require_once 'includes/db.php';
require_once 'includes/xendit_config.php';

$key = 'xnd_development_3rmH8dvEAR06lv0b9LCq4IOLG7dNpDp6F61A8XlKJVpKYXMeWJJi9LwLiVDvTS';
echo "Testing New Key 3: $key\n";

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
        'external_id' => 'order_' . $orderId . '_' . time(),
        'amount' => $amount,
        'description' => 'Test Order',
        'invoice_duration' => 86400,
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    echo "HTTP Status Code: " . $info['http_code'] . "\n";
    return json_decode($response, true);
}

print_r(custom_xendit_test($key, 2277, 800));

?>
