<?php
require_once 'includes/db.php';
function check($table, $id, $col) {
    global $conn;
    $res = $conn->query("SELECT * FROM $table WHERE $col = $id");
    if ($res) return $res->fetch_assoc();
    return null;
}
echo "ORDER DATA:\n";
print_r(check('orders', 2277, 'order_id'));
echo "\nJOB ORDER DATA:\n";
print_r(check('job_orders', 2277, 'id'));
?>
