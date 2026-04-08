<?php
/**
 * Xendit Webhook Endpoint
 * -----------------------
 * Handles invoice.paid and invoice.expired events from Xendit.
 */

// 1. Initial requirements
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/xendit_config.php';

// 2. Validate Token (Security)
$callback_token = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
if (defined('XENDIT_CALLBACK_TOKEN') && XENDIT_CALLBACK_TOKEN !== '') {
    if ($callback_token !== XENDIT_CALLBACK_TOKEN) {
        error_log("Xendit Webhook: Invalid Callback Token");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
}

// 3. Log incoming notification (optional, good for debugging)
$raw_input = file_get_contents('php://input');
$json = json_decode($raw_input, true);

if (!$json) {
    http_response_code(400);
    error_log("Xendit Webhook: Invalid JSON received.");
    exit('Invalid JSON');
}

$event = $json['status'] ?? null; // For invoices, status is PAID, EXPIRED, etc.
$external_id = $json['external_id'] ?? ''; // Format: order_123
$order_id = (int)str_replace('order_', '', $external_id);

if (!$order_id) {
    http_response_code(400);
    error_log("Xendit Webhook: External ID format invalid: " . $external_id);
    exit('Invalid Order ID');
}

// 4. Update the order based on status
if ($event === 'PAID') {
    // Payment Successful
    $update_sql = "UPDATE orders SET payment_status = 'PAID', updated_at = NOW() WHERE order_id = ?";
    if (db_execute($update_sql, 'i', [$order_id])) {
        // Also update job_orders if linked
        db_execute("UPDATE job_orders SET payment_status = 'PAID' WHERE order_id = ?", 'i', [$order_id]);
        
        // Find customer for notification
        $order = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id])[0] ?? null;
        if ($order) {
            $customer_id = (int)$order['customer_id'];
            $msg = "✅ Payment Confirmed! Your order #{$order_id} has been paid via Xendit.";
            create_notification($customer_id, 'Customer', $msg, 'Payment', false, false, $order_id);
            add_order_system_message($order_id, $msg);
            
            // Log as Paid manually if PrintFlow has that log format
            log_activity(0, 'Xendit Payment', "Order #{$order_id} marked as PAID via webhook");
        }
    }
} elseif ($event === 'EXPIRED') {
    // Payment Expired
    db_execute("UPDATE orders SET payment_status = 'EXPIRED', updated_at = NOW() WHERE order_id = ?", 'i', [$order_id]);
    db_execute("UPDATE job_orders SET payment_status = 'EXPIRED' WHERE order_id = ?", 'i', [$order_id]);
    
    $order = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id])[0] ?? null;
    if ($order) {
        $customer_id = (int)$order['customer_id'];
        $msg = "⚠️ Payment Expired: The payment link for order #{$order_id} has expired.";
        create_notification($customer_id, 'Customer', $msg, 'Payment', false, false, $order_id);
        add_order_system_message($order_id, $msg);
    }
}

// Always return HTTP 200 to Xendit
http_response_code(200);
echo json_encode(['success' => true]);
