<?php
/**
 * Customer Order Details Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';
require_once __DIR__ . '/../includes/xendit_config.php';

require_role('Customer');

// Check available payment methods
$qr_dir = __DIR__ . '/../public/assets/uploads/qr/';
$payment_cfg_path = $qr_dir . 'payment_methods.json';
$payment_methods = file_exists($payment_cfg_path) ? json_decode(file_get_contents($payment_cfg_path), true) : [];
if (!is_array($payment_methods)) $payment_methods = [];
$enabled_methods = array_filter($payment_methods, function($m) { return !empty($m['enabled']); });

$has_dynamic_payment = count($enabled_methods) > 0;
$has_xendit = defined('XENDIT_ENABLED') && XENDIT_ENABLED;

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();
// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

if (!$order_id) {
    redirect('orders.php');
}

// Get order details (ensure it belongs to the customer)
$order_result = db_query("
    SELECT * FROM orders 
    WHERE order_id = ? AND customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (empty($order_result)) {
    // Order not found or doesn't belong to customer
    redirect('orders.php');
}
$order = $order_result[0];

// Get order items
$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$product_image_select = "'' AS product_image";
if ($has_product_image && $has_photo_path) {
    $product_image_select = "COALESCE(p.photo_path, p.product_image) AS product_image";
} elseif ($has_product_image) {
    $product_image_select = "p.product_image AS product_image";
} elseif ($has_photo_path) {
    $product_image_select = "p.photo_path AS product_image";
}

$items = db_query("
    SELECT oi.*, p.name as product_name, p.category, {$product_image_select}
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

// Determine if price/payment should be shown.
// Hide while order is still in pending/design-review stages.
$price_pending_statuses = ['Pending', 'Pending Review', 'Pending Approval', 'For Revision', 'Approved'];
$show_price = !in_array($order['status'], $price_pending_statuses, true);

// Derive a more descriptive title based on items
$display_title = "Order #{$order_id}";
if (!empty($items)) {
    // Collect unique item names
    $names = [];
    foreach ($items as $item) {
        $custom = json_decode($item['customization_data'] ?? '{}', true);
        
        // Prioritize service_type from customization, then product_name, then fallback
        $itemName = $custom['service_type'] ?? ($item['product_name'] ?? '');
        
        // Clean up generic names
        if (empty($itemName) || $itemName === 'Customer Order' || $itemName === 'Custom Order' || $itemName === 'Custom Item' || $itemName === 'Order Item') {
            $itemName = get_service_name_from_customization($custom, 'Order #' . $order_id);
        }
        $itemName = normalize_service_name($itemName, 'Order #' . $order_id);

        if (!in_array($itemName, $names)) {
            $names[] = $itemName;
        }
    }
    
    if (count($names) === 1) {
        $display_title = $names[0];
    } else {
        $display_title = implode(", ", $names);
    }
    
    // Truncate if too long
    if (strlen($display_title) > 60) {
        $display_title = substr($display_title, 0, 57) . '...';
    }
}

$page_title = htmlspecialchars($display_title) . " - PrintFlow";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/printflow/public/assets/css/chat.css">

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 1080px;">
        <!-- Header with back button -->
        <div style="display:flex; align-items:center; margin-bottom: 2rem; gap: 1rem;">
            <a href="orders.php" class="btn-chat" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <h1 class="ct-page-title" style="margin:0; flex:1; text-align:center; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo htmlspecialchars($display_title); ?></h1>

            <div style="text-align:right;">
                <?php echo status_badge($order['status'], 'order'); ?>
            </div>
        </div>

        <style>
            .order-container { max-width: 960px; margin: 0 auto; }
            .compact-card { padding: 1.25rem !important; }
            .section-header { font-size:0.95rem; font-weight:700; margin-bottom:1rem; color:#111827; display:flex; align-items:center; gap:8px; }
            .order-summary-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; font-size:0.9rem; color:#4b5563; font-weight:600; }
            .order-summary-row .label { white-space:nowrap; }
            .order-summary-row .value { text-align:right; }
        </style>

        <div style="display:flex; flex-direction:column; gap:1.25rem;">
            
            <!-- 1. Order Status & Date Alert -->
            <div style="padding:1rem; background:rgba(255,255,255,0.05); color:var(--lp-text); border: 1px solid var(--lp-border); border-radius:12px; font-weight:700; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                <span>Placed on: <?php echo format_datetime($order['order_date']); ?></span>
                <a href="/printflow/customer/chat.php?order_id=<?php echo $order_id; ?>" class="btn-chat" style="padding:5px 12px; border-radius:6px; font-weight:800; font-size:0.75rem; text-decoration:none; display:inline-block;">
                    💬 Chat Support
                </a>
            </div>



            <!-- Revision Required Alert -->
            <?php if ($order['status'] === 'For Revision'): ?>
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 16px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start; backdrop-filter: blur(8px);">
                    <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);">⚠️</div>
                    <div>
                        <h3 style="color: #fca5a5; font-weight: 800; font-size: 0.95rem; margin-bottom: 0.35rem; letter-spacing: 0.02em;">Revision Required</h3>
                        <p style="color: #fecaca; font-size: 0.85rem; line-height: 1.6; margin-bottom:0.75rem;">
                            The shop has requested a revision for this order. Please review the reason below and update your order details.
                        </p>
                        <div style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(239, 68, 68, 0.2); padding: 12px; border-radius: 10px; font-size: 0.85rem; color: #fca5a5; font-weight: 500; line-height: 1.55; white-space: normal; overflow-wrap: anywhere; word-break: break-word; max-width: 100%;">
                            <strong style="color: #f87171;">Reason:</strong> <?php echo nl2br(htmlspecialchars($order['revision_reason'] ?? 'Not specified')); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cancellation Alert for Cancelled Orders -->
            <?php if ($order['status'] === 'Cancelled'): ?>
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 16px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start; backdrop-filter: blur(8px);">
                    <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);">✕</div>
                    <div style="font-size: 0.85rem;">
                        <h3 style="color: #fca5a5; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.02em; font-size: 0.95rem;">Order Cancelled</h3>
                        <p style="color: #fecaca; line-height: 1.6; margin:0; font-weight: 500;">
                            <strong style="color:#f87171;">Cancelled By:</strong> <?php echo htmlspecialchars($order['cancelled_by'] ?? 'N/A'); ?><br>
                            <strong style="color:#f87171;">Reason:</strong> <?php echo htmlspecialchars($order['cancel_reason'] ?? 'Not specified'); ?><br>
                            <strong style="color:#f87171;">Date:</strong> <?php echo !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : 'N/A'; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>



            <!-- Payment Submitted Status Alert -->
            <?php if ($order['status'] === 'Downpayment Submitted'): ?>
                <div style="background: rgba(34, 197, 94, 0.07); border: 1px solid rgba(34, 197, 94, 0.25); border-radius: 16px; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start; backdrop-filter: blur(8px);">
                    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(34, 197, 94, 0.2);">⏳</div>
                    <div>
                        <h3 style="color: #86efac; font-weight: 800; font-size: 0.95rem; margin-bottom: 0.35rem; letter-spacing: 0.02em;">Payment Under Review</h3>
                        <p style="color: #bbf7d0; font-size: 0.85rem; line-height: 1.6; margin:0; font-weight: 500;">
                            Your payment proof has been submitted. We'll notify you once it's verified and production begins.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        <!-- Payment Modal -->
        <!-- Payment Modal -->
        <style>
            /* Custom scrollbar for modal */
            #paymentModal .card::-webkit-scrollbar {
                width: 6px;
            }
            #paymentModal .card::-webkit-scrollbar-track {
                background: transparent; 
            }
            #paymentModal .card::-webkit-scrollbar-thumb {
                background: rgba(83, 197, 224, 0.2); 
                border-radius: 10px;
            }
            #paymentModal .card::-webkit-scrollbar-thumb:hover {
                background: rgba(83, 197, 224, 0.4); 
            }
            
            /* Premium Options UI */
            .payment-policy-option {
                display: flex; align-items: center; gap: 14px; padding: 14px 18px; 
                border: 2px solid rgba(83, 197, 224, 0.15); border-radius: 14px; 
                cursor: pointer; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                background: rgba(255, 255, 255, 0.03);
            }
            .payment-policy-option:hover {
                background: rgba(83, 197, 224, 0.08);
                border-color: rgba(83, 197, 224, 0.3);
            }
            .payment-policy-option input[type="radio"] {
                appearance: none; -webkit-appearance: none;
                width: 22px; height: 22px; border: 2px solid rgba(83, 197, 224, 0.4);
                border-radius: 50%; outline: none; transition: all 0.2s;
                display: flex; align-items: center; justify-content: center;
                background: rgba(0,0,0,0.2); flex-shrink: 0;
            }
            .payment-policy-option input[type="radio"]:checked {
                border-color: #53c5e0; background: rgba(83, 197, 224, 0.1);
            }
            .payment-policy-option input[type="radio"]:checked::after {
                content: ""; width: 10px; height: 10px; border-radius: 50%;
                background: #53c5e0; box-shadow: 0 0 8px rgba(83, 197, 224, 0.8);
            }
            .pm-tab-btn {
                flex: 1; padding: 12px; border-radius: 12px; 
                border: 2px solid rgba(83, 197, 224, 0.15); background: rgba(255, 255, 255, 0.03); 
                color: #8bbdcc; font-weight: 800; font-family: inherit; font-size: 0.85rem; 
                cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
                white-space: nowrap; text-transform: uppercase; letter-spacing: 0.05em;
            }
            .pm-tab-btn:hover:not(.active) {
                background: rgba(83, 197, 224, 0.08); border-color: rgba(83, 197, 224, 0.3); color: #eaf6fb;
            }
            .pm-tab-btn.active {
                border-color: #53c5e0; background: rgba(83, 197, 224, 0.15); color: #53c5e0;
                box-shadow: 0 4px 15px rgba(83, 197, 224, 0.15);
            }
        </style>
        <div id="paymentModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,10,15,0.8); z-index:9999; align-items:flex-start; justify-content:center; padding: 80px 10px 10px 10px; backdrop-filter: blur(12px);">
            <div class="card" style="width:100%; max-width: 800px; position:relative; border-radius: 0; padding: 1.5rem; max-height: calc(100vh - 100px); overflow-y: auto; background: rgba(0, 35, 43, 0.98); border: 1px solid rgba(83, 197, 224, 0.3); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 120px; background: radial-gradient(circle at top right, rgba(83, 197, 224, 0.15), transparent 70%); pointer-events: none; border-radius: 0;"></div>
                
                <button type="button" onclick="closePaymentModal()" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(83, 197, 224, 0.4); color: #ffffff; width: 36px; height: 36px; border-radius: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: all 0.2s; font-size: 1.25rem;">
                    ✕
                </button>

                <h2 style="font-size:1.4rem; font-weight:800; margin-bottom:0.25rem; color:#eaf6fb; display: flex; align-items: center; gap: 10px; position:relative; letter-spacing: -0.02em;">
                    Submit Payment
                </h2>

                <form id="paymentForm" enctype="multipart/form-data" style="position:relative;">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <?php echo csrf_field(); ?>

                    <p style="color:#ffffff; font-size:0.85rem; margin-bottom:1rem; font-weight: 500;">Follow the steps below to finalize your order.</p>
                    
                    <div id="paymentDetailsSection">
                        <label style="display:block; font-size:0.75rem; font-weight:800; color: #ffffff; margin-bottom:0.75rem; text-transform:uppercase; letter-spacing:0.06em;">Step 1: Choose Method & Transfer</label>
                        <?php if (empty($enabled_methods)): ?>
                            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; color: #ffffff; font-size: 0.9rem; font-weight: 500; text-align: center;">
                                ⚠️ No online payment methods are currently configured by the shop. Please contact support.
                            </div>
                        <?php else: ?>
                            <!-- Payment Methods Tabs/Selector -->
                            <div style="display: flex; gap: 10px; margin-bottom: 0.75rem; overflow-x: auto; padding-bottom: 4px;">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <button type="button" onclick="selectPaymentMethod(<?php echo $index; ?>)" id="btn-pm-<?php echo $index; ?>" class="pm-tab-btn <?php echo $first ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($pm['provider']); ?>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </div>

                            <!-- Payment Provider Details -->
                            <div style="background: rgba(0, 20, 26, 0.6); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 0.75rem; margin-bottom: 0.75rem; text-align: center; min-height: 100px; display: flex; flex-direction: column; justify-content: center; align-items: center; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);">
                                <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                    <div id="pm-details-<?php echo $index; ?>" class="pm-details-panel" style="display: <?php echo $first ? 'block' : 'none'; ?>; width: 100%; animation: fadeIn 0.3s ease;">
                                        <?php if (!empty($pm['file'])): ?>
                                            <div style="background: white; padding: 6px; border-radius: 12px; margin: 0 auto 10px auto; width: fit-content; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                                                <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>?t=<?php echo time(); ?>" style="width: 100px; height: 100px; object-fit: contain; display: block;" alt="QR Code">
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 1.15rem; font-weight: 800; color: #ffffff; margin-bottom: 4px; letter-spacing: -0.01em;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                        <?php if (!empty($pm['label'])): ?>
                                            <div style="font-size: 0.85rem; color: #ffffff; font-weight: 700; background: rgba(255, 255, 255, 0.1); padding: 2px 10px; border-radius: 999px; display: inline-block; border: 1px solid rgba(255, 255, 255, 0.2);"><?php echo htmlspecialchars($pm['label']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php $first = false; endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div id="proofUploadSection">
                            <div style="margin-bottom:0.75rem; background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 0.75rem 1rem;">
                                <label style="display:block; font-size:0.75rem; font-weight:800; color: #ffffff; margin-bottom:0.5rem; text-transform:uppercase; letter-spacing: 0.05em;">Amount to Pay (PHP)</label>
                                <input type="number" name="amount" id="paymentAmountInput" step="0.01"  
                                       value="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>" 
                                       min="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>" 
                                       style="width:100%; font-size: 1.5rem; font-weight: 800; color: #ffffff; background: transparent; border: none; outline: none;" required>
                                <p id="minPaymentText" style="font-size: 0.8rem; color: #d1d5db; margin-top: 6px; font-weight: 600;">Full Total: <?php echo format_currency($order['total_amount']); ?></p>
                            </div>
                            
                            <div style="margin-bottom:1rem;">
                                <label style="display:block; font-size:0.75rem; font-weight:800; color: #ffffff; margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:0.06em;">Step 2: Upload Proof of Payment</label>
                                <div id="dropzone" style="border: 2px dashed rgba(255, 255, 255, 0.3); background: rgba(0,0,0,0.25); border-radius: 0; padding: 0.75rem; text-align: center; cursor: pointer; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.borderColor='rgba(255, 255, 255, 0.6)'; this.style.background='rgba(255, 255, 255, 0.05)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.3)'; this.style.background='rgba(0,0,0,0.25)'">
                                    <input type="file" name="payment_proof" id="proofInput" style="display: none;" accept="image/*" required>
                                    <div id="uploadPlaceholder">
                                        <p style="font-size: 0.85rem; color: #ffffff; font-weight: 700; margin-bottom: 2px;">Click to upload or drag image</p>
                                        <p style="font-size: 0.7rem; color: #d1d5db; font-weight: 500;">PNG, JPG, JPEG (Max 10MB)</p>
                                    </div>
                                    <div id="filePreview" style="display: none; align-items: center; justify-content: center; flex-direction: column; overflow: hidden;">
                                        <img id="previewImg" src="" style="max-height: 100px; border-radius: 10px; margin-bottom: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 2px solid rgba(255, 255, 255, 0.3);">
                                        <p id="fileName" style="font-size: 0.75rem; color: #ffffff; font-weight: 700; background: rgba(0,0,0,0.6); padding: 4px 12px; border-radius: 4px; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:12px; padding-top: 1rem; border-top: 1px solid rgba(83, 197, 224, 0.15);">
                        <button type="button" onclick="closePaymentModal()" class="btn-secondary" style="border-radius: 0; font-weight: 700; font-family: inherit; font-size: 0.9rem; padding: 12px 24px; background: rgba(255,255,255,0.05); color: #8bbdcc; border: 1px solid rgba(83, 197, 224, 0.15);">Cancel</button>
                        <button type="submit" id="submitPaymentBtn" class="btn-primary" <?php echo empty($enabled_methods) ? 'disabled' : ''; ?> style="background: linear-gradient(135deg, #53c5e0, #32a1c4); color:#00151b; border-radius: 0; padding: 12px 28px; border: none; font-weight: 800; font-family: inherit; font-size: 0.9rem; text-transform:uppercase; letter-spacing:0.04em; box-shadow: 0 8px 20px rgba(83, 197, 224, 0.3); flex: 1; transition: all 0.2s; <?php echo empty($enabled_methods) ? 'opacity:0.6; cursor:not-allowed;' : ''; ?>">Submit & Confirm</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cancellation Modal -->
        <div id="cancelModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,10,15,0.8); z-index:9999; align-items:flex-start; justify-content:center; padding: 80px 10px 10px 10px; backdrop-filter: blur(12px);">
            <div class="card" style="width:100%; max-width: 800px; position:relative; border-radius: 0; padding: 1.5rem; max-height: calc(100vh - 100px); overflow-y: auto; background: rgba(0, 35, 43, 0.98); border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 120px; background: radial-gradient(circle at top right, rgba(239, 68, 68, 0.15), transparent 70%); pointer-events: none; border-radius: 0;"></div>
                
                <button type="button" onclick="closeCancelModal()" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ffffff; width: 36px; height: 36px; border-radius: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: all 0.2s; font-size: 1.25rem;">
                    ✕
                </button>

                <h2 style="font-size:1.4rem; font-weight:800; margin-bottom:0.75rem; color:#ffffff; position:relative;">Cancel Order #<?php echo $order_id; ?></h2>
                <p style="color:#ffffff; font-size:0.9rem; margin-bottom:1rem; position:relative;">Please tell us why you want to cancel this order. <strong>This cannot be undone.</strong></p>
                
                <form action="cancel_order.php" method="POST" style="position:relative;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    
                    <div style="margin-bottom:1rem; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.15);">
                        <label style="display:block; font-size:0.85rem; font-weight:700; margin-bottom:1rem; color: #ffffff; text-transform:uppercase; letter-spacing:0.04em;">Reason for Cancellation</label>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <label style="display:flex; align-items:center; gap:10px; font-size:0.95rem; cursor:pointer; color: #ffffff;">
                                <input type="radio" name="reason" value="Wrong item ordered" style="width: 18px; height: 18px; cursor: pointer; accent-color: #ffffff;" required> Wrong item ordered
                            </label>
                            <label style="display:flex; align-items:center; gap:10px; font-size:0.95rem; cursor:pointer; color: #ffffff;">
                                <input type="radio" name="reason" value="Found better price elsewhere" style="width: 18px; height: 18px; cursor: pointer; accent-color: #ffffff;"> Found better price elsewhere
                            </label>
                            <label style="display:flex; align-items:center; gap:10px; font-size:0.95rem; cursor:pointer; color: #ffffff;">
                                <input type="radio" name="reason" value="Changed my mind" style="width: 18px; height: 18px; cursor: pointer; accent-color: #ffffff;"> Changed my mind
                            </label>
                            <label style="display:flex; align-items:center; gap:10px; font-size:0.95rem; cursor:pointer; color: #ffffff;">
                                <input type="radio" name="reason" value="Other" style="width: 18px; height: 18px; cursor: pointer; accent-color: #ffffff;"> Other (Please specify below)
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-bottom:1rem;">
                        <label style="display:block; font-size:0.85rem; font-weight:700; margin-bottom:0.75rem; color: #ffffff; text-transform:uppercase; letter-spacing:0.04em;">Additional Details (Optional)</label>
                        <textarea name="details" style="width:100%; min-height:80px; font-size:0.95rem; background:rgba(0,0,0,0.25); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 12px; padding: 12px; color: #ffffff; outline: none; transition: border-color 0.2s; resize: none;" onfocus="this.style.borderColor='rgba(255, 255, 255, 0.6)'" onblur="this.style.borderColor='rgba(255, 255, 255, 0.2)'" placeholder="e.g. personal issue..."></textarea>
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" onclick="closeCancelModal()" class="btn-secondary" style="background: rgba(255,255,255,0.05); color: #8bbdcc; border: 1px solid rgba(255,255,255,0.1); border-radius: 0; padding: 10px 20px; font-weight: 700;">Keep Order</button>
                        <button type="submit" name="confirm_cancel" class="btn-primary" style="background: linear-gradient(135deg, #ef4444, #b91c1c); color:white; border-radius: 0; padding: 10px 20px; font-weight: 800; border: none; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openCancelModal() {
                document.getElementById('cancelModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            function closeCancelModal() {
                document.getElementById('cancelModal').style.display = 'none';
                document.body.style.overflow = '';
            }

            function openPaymentModal() {
                document.getElementById('paymentModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            function closePaymentModal() {
                document.getElementById('paymentModal').style.display = 'none';
                document.body.style.overflow = '';
            }

            function updatePaymentUI(choice) {
                // Update active state of containers
                document.querySelectorAll('.payment-policy-option').forEach(el => {
                    el.classList.remove('active-policy');
                });
                
                const selected = document.querySelector(`input[name="payment_choice"][value="${choice}"]`);
                if (selected) {
                    const parent = selected.closest('.payment-policy-option');
                    parent.classList.add('active-policy');
                }

                const detailsSection = document.getElementById('paymentDetailsSection');
                const proofSection = document.getElementById('proofUploadSection');
                const amountInput = document.getElementById('paymentAmountInput');
                const minText = document.getElementById('minPaymentText');
                const proofInput = document.getElementById('proofInput');

                const total = <?php echo (float)$order['total_amount']; ?>;
                const half = total * 0.5;

                detailsSection.style.display = 'block';
                proofSection.style.display = 'block';
                proofInput.required = true;
                amountInput.required = true;

                if (choice === 'half') {
                    amountInput.value = half.toFixed(2);
                    amountInput.min = half.toFixed(2);
                    minText.textContent = 'Min. 50%: PHP ' + half.toLocaleString(undefined, {minimumFractionDigits: 2});
                } else {
                    amountInput.value = total.toFixed(2);
                    amountInput.min = total.toFixed(2);
                    minText.textContent = 'Total: PHP ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
                }
            }

            function selectPaymentMethod(selectedIndex) {
                // Reset all tabs
                document.querySelectorAll('.pm-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Set active tab
                const activeBtn = document.getElementById('btn-pm-' + selectedIndex);
                if (activeBtn) {
                    activeBtn.classList.add('active');
                }

                // Hide all details
                document.querySelectorAll('.pm-details-panel').forEach(el => {
                    el.style.display = 'none';
                });
                
                // Show active details
                const activeDetails = document.getElementById('pm-details-' + selectedIndex);
                if (activeDetails) {
                    activeDetails.style.display = 'block';
                }
            }

            // File upload UI handling
            const dropzone = document.getElementById('dropzone');
            const proofInput = document.getElementById('proofInput');

            // Auto-open payment modal if ?pay=1 is in URL
            window.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('pay') === '1') {
                    openPaymentModal();
                }
            });
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const filePreview = document.getElementById('filePreview');
            const previewImg = document.getElementById('previewImg');
            const fileName = document.getElementById('fileName');

            if (dropzone) {
                dropzone.addEventListener('click', () => proofInput.click());
                proofInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        fileName.textContent = file.name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            previewImg.src = e.target.result;
                            uploadPlaceholder.style.display = 'none';
                            filePreview.style.display = 'flex';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // AJAX Submission
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('submitPaymentBtn');
                    btn.disabled = true;
                    btn.textContent = 'Submitting...';

                    const formData = new FormData(this);
                    
                    fetch('api_submit_payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showSuccessModal(
                                'Payment Submitted',
                                data.message,
                                'order_details.php?id=<?php echo $order_id; ?>',
                                'orders.php',
                                'View Order',
                                'Back to Orders'
                            );
                            closePaymentModal();
                        } else {
                            alert('Error: ' + data.message);
                            btn.disabled = false;
                            btn.textContent = 'Submit Payment';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred. Please try again.');
                        btn.disabled = false;
                        btn.textContent = 'Submit Payment';
                    });
                });
            }

            // Trigger success modal if success message exists
            window.addEventListener('DOMContentLoaded', () => {
                <?php if (isset($_SESSION['success'])): 
                    $msg = $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
                showSuccessModal(
                    'Order Placed!',
                    '<?php echo addslashes($msg); ?>',
                    'orders.php',
                    'services.php',
                    'View My Orders',
                    'Go to Services',
                    'services.php',
                    3500
                );
                <?php endif; ?>
            });
        </script>

            <!-- 2. Order Summary (Items & Total) -->
            <div class="card compact-card">
                <h2 class="section-header">
                    <span>🛒</span> Order Summary
                </h2>
                
                <div style="display:flex; flex-direction:column; gap: 0.5rem;">
                    <?php foreach ($items as $item): ?>
                        <?php render_order_item_clean($item, false, $show_price); ?>
                    <?php endforeach; ?>
                </div>

                <div style="border-top:1px solid #f3f4f6; padding-top:1rem; margin-top:1rem; display:flex; flex-direction:column; gap:0.5rem;">
                    <div class="order-summary-row">
                        <span class="label">Order Status:</span>
                        <span class="value"><?php echo status_badge($order['status'], 'order'); ?></span>
                    </div>
                    <?php if ($show_price): ?>
                        <div class="order-summary-row">
                            <span class="label">Payment Status:</span>
                            <span class="value"><?php echo status_badge($order['payment_status'], 'payment'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div style="border-top:1px solid #e5e7eb; padding-top:1rem; margin-top:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color:#ffffff; font-size:0.95rem;">Grand Total</span>
                        <?php if ($show_price): ?>
                            <span style="font-size:1.5rem; font-weight:800; color:#ffffff;"><?php echo format_currency($order['total_amount']); ?></span>
                        <?php else: ?>
                            <span style="font-size:0.85rem; font-weight:700; color:#d1d5db; font-style:italic;">Price will be confirmed by the shop</span>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                        <?php if (in_array($order['status'], ['To Pay', 'TO PAY'], true)): ?>
                            <?php if ($has_xendit): ?>
                                <a href="payment.php?order_id=<?php echo $order_id; ?>" class="btn-primary" style="width:fit-content; margin:0 auto; padding:12px 30px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; background:linear-gradient(135deg, #4F46E5, #3730a3); display:inline-block; text-align:center; text-decoration:none; border-radius:0; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);">
                                    Pay Now with Xendit
                                </a>
                            <?php endif; ?>
                            <?php if ($has_dynamic_payment): ?>
                                <button onclick="openPaymentModal()" class="btn-primary" style="width:260px; margin:0 auto; padding:12px 20px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; background:linear-gradient(135deg, #ea580c, #c2410c); border:none; display:block; text-align:center; color:white; border-radius:0; box-shadow: 0 10px 15px -3px rgba(234, 88, 12, 0.3); border: none; cursor:pointer;">
                                    Upload Payment Proof
                                </button>
                            <?php endif; ?>
                            <?php if (!$has_xendit && !$has_dynamic_payment): ?>
                                <div style="text-align:center; padding:10px; background:#fef2f2; color:#b91c1c; border-radius:10px; font-size:0.85rem;">
                                    No payment methods are currently available. Please contact support.
                                </div>
                            <?php endif; ?>
                        <?php elseif ($show_price && $order['payment_status'] === 'Unpaid' && !in_array($order['status'], ['Downpayment Submitted', 'Cancelled'], true)): ?>
                            <?php if ($has_xendit): ?>
                                <a href="payment.php?order_id=<?php echo $order_id; ?>" class="btn-primary" style="width:fit-content; margin:0 auto; padding:12px 30px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; display:inline-block; text-align:center; text-decoration:none; border-radius:0;">
                                    Pay now
                                </a>
                            <?php elseif ($has_dynamic_payment): ?>
                                <button onclick="openPaymentModal()" class="btn-primary" style="width:fit-content; margin:0 auto; padding:12px 30px; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; display:inline-block; text-align:center; text-decoration:none; border-radius:0; border:none; color:white; cursor:pointer; background:linear-gradient(135deg, #ea580c, #c2410c);">
                                    Upload Payment Proof
                                </button>
                            <?php endif; ?>
                        <?php elseif (!$show_price): ?>
                            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-left:4px solid #0ea5e9; border-radius:10px; padding:12px 14px; display:flex; gap:10px; align-items:center;">
                                <span style="font-size:1.1rem;">⏳</span>
                                <div style="font-size:0.75rem; color:#0369a1; font-weight:600; line-height:1.4;">Order is under review. Pricing and payment options will be available soon.</div>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'For Revision'): ?>
                            <a href="edit_order.php?id=<?php echo $order_id; ?>" class="btn-primary" style="width:100%; padding:12px; background:#f59e0b; font-weight:800; text-transform:uppercase; text-decoration:none; text-align:center;">
                                Edit order
                            </a>
                        <?php endif; ?>

                        <?php if (can_customer_cancel_order($order)): ?>
                            <button type="button" onclick="openCancelModal()" style="width:260px; margin:0 auto; padding:12px 20px; background:transparent; color:#ef4444; font-size:0.8rem; font-weight:700; border:1px solid #fee2e2; border-radius:0; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                Cancel order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Contact Information -->
            <div class="card compact-card">
                <h2 class="section-header">
                     Contact Information
                </h2>
                <?php 
                $cust_res = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$order['customer_id']]);
                $customer_info = $cust_res[0] ?? [];
                ?>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
            <div>
                <div style="font-size:0.65rem; color:#d1d5db; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Full Name</div>
                <div style="font-weight:700; color:#ffffff; font-size:0.9rem;"><?php echo htmlspecialchars(trim(($customer_info['first_name'] ?? '') . ' ' . ($customer_info['middle_name'] ?? '') . ' ' . ($customer_info['last_name'] ?? ''))); ?></div>
            </div>
            <div>
                <div style="font-size:0.65rem; color:#d1d5db; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Phone Number</div>
                <div style="font-weight:700; color:#ffffff; font-size:0.9rem;"><?php echo htmlspecialchars($customer_info['contact_number'] ?? '—'); ?></div>
            </div>
            <div style="grid-column: span 2;">
                <div style="font-size:0.65rem; color:#d1d5db; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Email Address</div>
                <div style="font-weight:700; color:#ffffff; font-size:0.9rem;"><?php echo htmlspecialchars($customer_info['email'] ?? ''); ?></div>
            </div>
                </div>
            </div>

            <!-- 4. Order Notes -->
            <?php if (!empty($order['notes'])): ?>
                <div class="card compact-card" style="background:rgba(255, 251, 235, 0.05); border:1px solid rgba(253, 230, 138, 0.2);">
                    <h2 class="section-header" style="color:#fcd34d; margin-bottom:0.75rem;">
                        <span>📝</span> Order Notes
                    </h2>
                    <div style="font-size:0.85rem; color:#ffffff; line-height:1.5; font-weight:600; max-height: 120px; overflow-y: auto; word-break: break-word;">
                        <?php echo nl2br(htmlspecialchars($order['notes'] ?? '')); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>


<script>
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('chat') === 'open') {
        window.location.href = '/printflow/customer/chat.php?order_id=<?php echo $order_id; ?>';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

