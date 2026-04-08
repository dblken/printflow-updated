<?php
require_once __DIR__ . '/includes/db.php';

function add_column_if_not_exists($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $res->num_rows === 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if ($conn->query($sql)) {
            echo "Column '$column' added to '$table'.\n";
        } else {
            echo "Error adding column '$column' to '$table': " . $conn->error . "\n";
        }
    } else {
        echo "Column '$column' already exists in '$table'.\n";
    }
}

add_column_if_not_exists($conn, 'orders', 'payment_link', 'TEXT AFTER status');
add_column_if_not_exists($conn, 'orders', 'payment_status', 'VARCHAR(50) DEFAULT "PENDING" AFTER payment_link');

echo "Update complete.\n";
