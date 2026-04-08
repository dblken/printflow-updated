<?php
/**
 * Customer Order Payment Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';
require_once __DIR__ . '/../includes/xendit_config.php';
require_once __DIR__ . '/../includes/JobOrderService.php';

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();
$is_job_order = false;
$items = [];

// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND (customer_id = ? OR (user_id = ? AND customer_id IS NULL))", 'iii', [$notification_id, $customer_id, $customer_id]);
}

if (!defined('XENDIT_ENABLED') || !XENDIT_ENABLED) {
    header("Location: order_details.php?id=" . $order_id . "&pay=1");
    exit;
}

if (!$order_id) {
    die('<div style="text-align:center; padding: 50px; font-family: sans-serif; background: #00151b; min-height: 100vh; color: #eaf6fb;">
            <h2 style="color: #ef4444; font-size: 2rem; margin-bottom: 1rem;">Invalid Order</h2>
            <p style="color: #8bbdcc; margin-bottom: 2rem;">The order ID is missing or invalid.</p>
            <a href="orders.php" style="display: inline-block; padding: 10px 24px; background: #53c5e0; color: #00232b; text-decoration: none; font-weight: 800; border-radius: 12px;">Back to My Orders</a>
         </div>');
}

// 1. Fetch Order (Regular or Job)
$order_result = db_query("
    SELECT o.*, c.email, c.contact_number, c.customer_type
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.order_id = ? AND o.customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (!empty($order_result)) {
    $order = $order_result[0];
    $order_status = $order['status'];
    $payment_status = strtoupper($order['payment_status'] ?? ''); 
    $total_amount = (float)$order['total_amount'];
    
    $items = db_query("
        SELECT oi.*, p.name as product_name, p.category, 
        COALESCE(p.photo_path, p.product_image, '') AS product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ", 'i', [$order_id]);
} else {
    // 2. Fallback to Customer Service Job Orders
    $job = JobOrderService::getOrder($order_id);
    if (!$job || (int)$job['customer_id'] !== $customer_id) {
        die('<div style="text-align:center; padding: 50px; font-family: sans-serif; background: #00151b; min-height: 100vh; color: #eaf6fb;">
                <h2 style="color: #ef4444; font-size: 2rem; margin-bottom: 1rem;">Order Not Found</h2>
                <p style="color: #8bbdcc; margin-bottom: 2rem;">The requested order was not found or you do not have permission to view it.</p>
                <a href="orders.php" style="display: inline-block; padding: 10px 24px; background: #53c5e0; color: #00232b; text-decoration: none; font-weight: 800; border-radius: 12px;">Back to My Orders</a>
             </div>');
    }
    $order = $job;
    $is_job_order = true;
    $total_amount = (float)$order['estimated_total'];
    $payment_status = strtoupper($order['payment_status'] ?? ''); 
    $order_status = $order['status'];
    $items = $order['items'] ?? [];
}

// Standardize Status for Logic
$is_to_pay = (strtoupper($order_status) === 'TO PAY' || strtoupper($order_status) === 'TO_PAY' || $payment_status === 'TO PAY');

// AUTOMATIC REDIRECTION LOGIC (Ensure Payment Link Exists)
if ($is_to_pay && !isset($_GET['retry'])) {
    $pay_link = $order['payment_link'] ?? '';
    
    // If no link, generate one now (if Xendit is enabled)
    if (empty($pay_link) && defined('XENDIT_ENABLED') && XENDIT_ENABLED) {
        $amount_to_bill = $total_amount;
        // For regular customers, maybe they only pay 50%
        if (!$is_job_order && strtolower($order['customer_type'] ?? '') === 'regular') {
            $amount_to_bill = $total_amount * 0.5;
        } elseif ($is_job_order && !empty($order['required_payment']) && (float)$order['required_payment'] > 0) {
            $amount_to_bill = (float)$order['required_payment'];
        }

        $cust_email = $order['email'] ?? $order['customer_email'] ?? '';
        $cust_phone = $order['contact_number'] ?? $order['customer_contact'] ?? '';

        $res = xendit_generate_payment_link($order_id, $amount_to_bill, $cust_email, $cust_phone);
        if ($res && $res['success']) {
            $pay_link = $res['data']['invoice_url'];
            // Save link to database
            db_execute("UPDATE orders SET payment_link = ?, payment_status = 'TO PAY' WHERE order_id = ?", 'si', [$pay_link, $order_id]);
            $order['payment_link'] = $pay_link;
        } else {
            error_log("Xendit: Generation FAILED for Order #{$order_id}. Result: " . json_encode($res));
        }
    }

    if (!empty($pay_link) && !isset($_GET['manual'])) {
        header("Location: " . $pay_link);
        exit;
    }
}

$page_title = "Payment - Order #{$order_id}";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .payment-container { max-width: 1000px; margin: 0 auto; padding: 2rem 1rem 6rem; }
    .glass-card { background: rgba(10, 37, 48, 0.48); backdrop-filter: blur(12px); border: 1px solid rgba(83, 197, 224, 0.2); border-radius: 24px; padding: 2rem; }
    .amount-box { text-align: center; background: rgba(83, 197, 224, 0.05); border: 1px solid rgba(83, 197, 224, 0.15); border-radius: 20px; padding: 2.5rem 1.5rem; margin-bottom: 2rem; }
    .amount-label { font-size: 0.85rem; font-weight: 700; color: #9fc4d4; letter-spacing: 0.1em; margin-bottom: 0.75rem; text-transform: uppercase; }
    .amount-val { font-size: 3.25rem; font-weight: 950; color: #53c5e0; letter-spacing: -0.02em; }
    
    .pay-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
        padding: 1.25rem;
        background: linear-gradient(135deg, #53c5e0, #32a1c4);
        color: #00151b;
        font-weight: 900;
        font-size: 1.1rem;
        border-radius: 16px;
        text-decoration: none;
        transition: all 0.3s;
        box-shadow: 0 10px 25px rgba(83, 197, 224, 0.3);
        border: none;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .pay-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(83, 197, 224, 0.45); filter: brightness(1.1); }
    .pay-btn svg { width: 24px; height: 24px; }

    .status-msg { margin-top: 1.5rem; padding: 1.25rem; border-radius: 16px; background: rgba(83, 197, 224, 0.1); border: 1px solid rgba(83, 197, 224, 0.2); display: flex; align-items: flex-start; gap: 12px; }
    .status-msg-icon { font-size: 1.5rem; line-height: 1; }
    .status-msg-text { font-size: 0.95rem; color: #eaf6fb; line-height: 1.5; font-weight: 500; }
</style>

<div class="payment-container">
    <div class="flex items-center justify-between mb-8">
        <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn-secondary flex items-center gap-2">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
        <h1 class="text-3xl font-black text-white">Payment</h1>
        <div style="min-width: 100px; text-align: right;">
            <?php echo status_badge($order_status, 'order'); ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Summary -->
        <div class="lg:col-span-7 space-y-6">
            <div class="glass-card">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                    <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    Order Summary
                </h2>
                
                <div class="space-y-4">
                    <?php if (!$is_job_order): ?>
                        <?php foreach ($items as $item): ?>
                            <?php render_order_item_clean($item, false, true); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Job Order Display -->
                        <?php if (empty($items)): ?>
                            <div class="p-5 rounded-2xl border border-white/10 bg-white/5 flex gap-4">
                                <div class="w-20 h-20 rounded-xl bg-black/20 border border-white/5 flex items-center justify-center shrink-0">
                                    <span class="text-3xl">🛠️</span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white"><?php echo htmlspecialchars($order['job_title']); ?></h3>
                                    <p class="text-sm text-gray-400"><?php echo htmlspecialchars($order['service_type']); ?></p>
                                    <p class="text-sm font-bold text-primary-400 mt-1"><?php echo $order['quantity']; ?> Unit(s)</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <div class="p-5 rounded-2xl border border-white/10 bg-white/5 flex gap-4">
                                    <div class="w-20 h-20 rounded-xl bg-black/20 border border-white/5 overflow-hidden shrink-0">
                                        <?php if (!empty($item['design_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['design_url']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gray-800 text-2xl">📦</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                        <div class="flex justify-between items-center mt-2">
                                            <p class="text-sm text-gray-400">Qty: <span class="text-white font-bold"><?php echo $item['quantity']; ?></span></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Payment Action -->
        <div class="lg:col-span-5">
            <div class="glass-card sticky top-6">
                <div class="amount-box">
                    <div class="amount-label">Total Amount Due</div>
                    <div class="amount-val"><?php echo format_currency($total_amount); ?></div>
                </div>

                <?php if ($payment_status === 'PAID'): ?>
                    <div class="text-center py-8">
                        <div class="text-6xl mb-4">✅</div>
                        <h3 class="text-2xl font-black text-green-400 mb-2">Payment Confirmed</h3>
                        <p class="text-gray-400">This order has been fully paid via Xendit.</p>
                        <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn-primary w-full mt-8 flex items-center justify-center">View Order Details</a>
                    </div>
                <?php elseif ($is_to_pay): ?>
                    <div class="space-y-4">
                        <div class="status-msg">
                            <span class="status-msg-icon">🛡️</span>
                            <div class="status-msg-text">
                                You can now pay your order securely using <strong>Xendit</strong>. Click the button below to proceed to the secure checkout page.
                            </div>
                        </div>

                        <?php $pay_url = $order['payment_link'] ?? ''; ?>
                        <?php if ($pay_url): ?>
                            <a href="<?php echo htmlspecialchars($pay_url); ?>" target="_blank" class="pay-btn">
                                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                Pay Now via Secure Payment
                            </a>
                            <p class="text-center text-xs text-gray-500 font-medium">Powered by Xendit Gateway</p>
                        <?php else: ?>
                            <div class="p-5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-center">
                                <div class="text-3xl mb-2">⏳</div>
                                <h4 class="text-amber-400 font-bold mb-1">Payment Link Not Ready</h4>
                                <p class="text-xs text-amber-200/60 mb-4">We are still preparing your secure checkout URL. Please try again in 1-2 minutes.</p>
                                <a href="?order_id=<?php echo $order_id; ?>" class="btn-secondary w-full text-center py-2 text-sm justify-center">Refresh Page</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 bg-white/5 rounded-2xl border border-white/5">
                        <div class="text-5xl mb-4">🏠</div>
                        <h3 class="text-lg font-bold text-white mb-2">Payment Not Ready</h3>
                        <p class="text-sm text-gray-400 px-6">Your order status currently is <strong><?php echo $order_status; ?></strong>. Payment will be enabled once staff sets it to "To Pay".</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
/footer.php'; ?>
