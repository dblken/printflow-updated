<?php
require_once __DIR__ . '/includes/functions.php';
$check = db_query("SHOW COLUMNS FROM order_messages LIKE 'is_pinned'");
if (empty($check)) {
    $res = db_execute("ALTER TABLE order_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
    echo "Added is_pinned column: " . ($res ? 'SUCCESS' : 'FAILED');
} else {
    echo "is_pinned column already exists.";
}
