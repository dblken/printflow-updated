<?php
/**
 * public/api/reviews/list.php — Fetch reviews for a specific service or product via AJAX.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

$service_id = (int)($_GET['service_id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($service_id <= 0 && $product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'No service or product ID provided.']);
    exit();
}

$sql = "
    SELECT r.id, r.order_id, r.rating, r.comment as message, r.video_path, r.created_at, r.service_type,
           COALESCE(c.first_name, u.first_name) as first_name,
           COALESCE(c.last_name, u.last_name) as last_name
    FROM reviews r
    LEFT JOIN customers c ON c.customer_id = r.user_id
    LEFT JOIN users u ON u.user_id = r.user_id
    WHERE 1=1
";
$params = [];
$types = '';

if ($product_id > 0) {
    $sql .= " AND r.reference_id = ? AND r.review_type = 'product'";
    $params[] = $product_id;
    $types .= 'i';
} elseif ($service_id > 0) {
    $sql .= " AND r.reference_id = ? AND r.review_type = 'custom'";
    $params[] = $service_id;
    $types .= 'i';
}

$sql .= " ORDER BY r.created_at DESC LIMIT " . $limit;

$reviews_list = db_query($sql, $types ?: null, $params ?: null) ?: [];

$final_reviews = [];
foreach ($reviews_list as $r) {
    $rid = (int)$r['id'];
    
    // Fetch images
    $images = db_query("SELECT image_path FROM review_images WHERE review_id = ?", 'i', [$rid]) ?: [];
    foreach ($images as &$img) {
        $ipath = $img['image_path'];
        if ($ipath && strpos($ipath, '/') !== 0 && strpos($ipath, 'http') !== 0) {
            $ipath = '/printflow/' . $ipath;
        }
        $img['image_path'] = $ipath;
    }
    unset($img);

    // Fetch replies
    $replies = db_query("
        SELECT rr.reply_message, rr.created_at, u.first_name as staff_fname, u.last_name as staff_lname
        FROM review_replies rr
        INNER JOIN users u ON u.user_id = rr.staff_id
        WHERE rr.review_id = ?
        ORDER BY rr.created_at ASC
    ", 'i', [$rid]) ?: [];

    // Prep video path
    if (!empty($r['video_path'])) {
        $vpath = $r['video_path'];
        if ($vpath && strpos($vpath, '/') !== 0 && strpos($vpath, 'http') !== 0) {
            $vpath = '/printflow/' . $vpath;
        }
        $r['video_path'] = $vpath;
    }

    $r['images'] = $images;
    $r['replies'] = $replies;
    $r['initials'] = strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1));
    $r['display_name'] = htmlspecialchars($r['first_name'] . ' ' . substr($r['last_name'], 0, 1) . '.');
    $r['formatted_date'] = date('F j, Y g:i A', strtotime($r['created_at']));
    
    $final_reviews[] = $r;
}

echo json_encode([
    'success' => true,
    'reviews' => $final_reviews
]);
