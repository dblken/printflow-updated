<?php
/**
 * notifications/list.php — Fetch latest notifications as JSON for dropdown.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = (int) get_user_id();
$user_type = get_user_type() ?? 'Customer';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;

$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$product_image_column = 'NULL';
if ($has_photo_path && $has_product_image) {
    $product_image_column = "COALESCE(p.photo_path, p.product_image)";
} elseif ($has_photo_path) {
    $product_image_column = "p.photo_path";
} elseif ($has_product_image) {
    $product_image_column = "p.product_image";
}

$sql = "SELECT 
            n.notification_id AS id, 
            n.message, 
            n.type, 
            n.data_id, 
            n.is_read, 
            n.order_type, 
            n.created_at,
            CASE WHEN n.type = 'Job Order' THEN jo.job_title ELSE 
                (SELECT p.name FROM order_items oi 
                 LEFT JOIN products p ON oi.product_id = p.product_id 
                 WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
            END as service_name,
            CASE WHEN n.type = 'Job Order' THEN jo.service_type ELSE NULL END as jo_service_category,
            CASE WHEN n.type = 'Job Order' THEN jo.artwork_path ELSE 
                (SELECT {$product_image_column} FROM products p 
                 INNER JOIN order_items oi ON oi.product_id = p.product_id 
                 WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
            END as product_image,
            (SELECT oi.customization_data FROM order_items oi 
             WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
            (SELECT oi.order_item_id FROM order_items oi 
             WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
            (SELECT oi.design_image FROM order_items oi 
             WHERE oi.order_id = n.data_id AND oi.design_image IS NOT NULL ORDER BY oi.order_item_id ASC LIMIT 1) as design_image
        FROM notifications n
        LEFT JOIN orders o ON n.data_id = o.order_id AND (n.type = 'Order' OR n.type = 'Payment' OR n.type = 'Status')
        LEFT JOIN job_orders jo ON n.data_id = jo.id AND n.type = 'Job Order'
        WHERE n." . ($user_type === 'Customer' ? 'customer_id' : 'user_id') . " = ?
        ORDER BY n.created_at DESC
        LIMIT " . (int)$limit;

$rows = db_query($sql, 'i', [$user_id]) ?: [];

foreach ($rows as &$n) {
    $name_data = !empty($n['first_item_customization']) ? json_decode($n['first_item_customization'], true) : [];
    $raw_service_name = trim((string)($name_data['service_type'] ?? $n['jo_service_category'] ?? $n['service_name'] ?? ''));
    if (empty($raw_service_name) || in_array(strtolower($raw_service_name), ['custom order', 'customer order', 'service order', 'order item', 'order update'])) {
        $raw_service_name = get_service_name_from_customization($name_data, $n['service_name'] ?? 'Order Update');
    }
    $display_name = normalize_service_name($raw_service_name, 'Order Update');
    
    $final_image_url = "";
    if (!empty($n['design_image'])) {
        $final_image_url = "/printflow/staff/get_design_image.php?id=" . $n['first_item_id'];
    } elseif (!empty($n['product_image']) && strtolower(trim($display_name)) === strtolower(trim($n['service_name'] ?? ''))) {
        $final_image_url = $n['product_image'];
        if (strpos($final_image_url, 'uploads/') === 0) {
            $final_image_url = '/printflow/' . $final_image_url;
        }
    } else {
        $final_image_url = get_service_image_url($raw_service_name ?: $display_name);
    }
    $n['image_url'] = $final_image_url;
    
    // Remove heavy meta fields to keep JSON light
    unset($n['first_item_customization'], $n['product_image'], $n['design_image']);
}
unset($n);

$unread = get_unread_notification_count($user_id, $user_type);

echo json_encode([
    'success'       => true,
    'notifications' => $rows,
    'unread_count'  => (int) $unread
]);
