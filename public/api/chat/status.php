<?php
require_once __DIR__ . '/../../../includes/auth.php';

// Prevent accidental output (warnings/notices) from breaking JSON
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$is_typing = isset($_POST['is_typing']) ? (int)$_POST['is_typing'] : 0;
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

// Map Admin/Manager/Staff to 'Staff'
$db_user_type = ($user_type === 'Customer') ? 'Customer' : 'Staff';

$sql = "INSERT INTO user_status (user_type, user_id, order_id, is_typing, last_activity) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_activity = CURRENT_TIMESTAMP";

$result = db_execute($sql, 'siii', [$db_user_type, $user_id, $order_id, $is_typing]);

// Also update the global user/customer last_activity for the "Online" indicator (defensive check)
if ($user_type === 'Customer') {
    $has_col = !empty(db_query("SHOW COLUMNS FROM customers LIKE 'last_activity'"));
    if ($has_col) {
        db_execute("UPDATE customers SET last_activity = CURRENT_TIMESTAMP WHERE customer_id = ?", 'i', [$user_id]);
    }
} else {
    $has_col = !empty(db_query("SHOW COLUMNS FROM users LIKE 'last_activity'"));
    if ($has_col) {
        db_execute("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE user_id = ?", 'i', [$user_id]);
    }
}

// Clear any accidental output before sending JSON
ob_end_clean();

// Fetch partner info for calling system
$partner = null;
if ($user_type === 'Customer') {
    // Customer calling Staff - look for last staff message sender
    $p_res = db_query("SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.profile_picture as avatar 
                       FROM order_messages om JOIN users u ON om.sender_id = u.user_id 
                       WHERE om.order_id = ? AND om.sender = 'Staff' 
                       ORDER BY om.message_id DESC LIMIT 1", 'i', [$order_id]);
    if (empty($p_res)) {
        // Fallback to shop admin (ID 1)
        $p_res = db_query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name, profile_picture as avatar FROM users WHERE user_id = 1");
    }
} else {
    // Staff calling Customer
    $p_res = db_query("SELECT c.customer_id as user_id, CONCAT(c.first_name, ' ', c.last_name) as name, c.profile_picture as avatar 
                       FROM orders o JOIN customers c ON o.customer_id = c.customer_id 
                       WHERE o.order_id = ?", 'i', [$order_id]);
}

if (!empty($p_res)) {
    $partner = [
        'id' => (int)$p_res[0]['user_id'],
        'name' => $p_res[0]['name'],
        'avatar' => $p_res[0]['avatar']
    ];
}

echo json_encode([
    'success' => (bool)$result,
    'partner' => $partner
]);
?>
