<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Missing message ID']);
    exit;
}

// Toggle pinned status
$check = db_query("SELECT is_pinned FROM order_messages WHERE message_id = ?", 'i', [$message_id]);
if (!$check) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

$new_status = $check[0]['is_pinned'] ? 0 : 1;
$res = db_execute("UPDATE order_messages SET is_pinned = ? WHERE message_id = ?", 'ii', [$new_status, $message_id]);

echo json_encode(['success' => (bool)$res, 'is_pinned' => (bool)$new_status]);
