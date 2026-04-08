<?php
require 'includes/db.php';
$res = db_query('DESCRIBE orders');
echo "ORDERS TABLE:\n";
foreach($res as $r) {
    if (strpos($r['Field'], 'status') !== false) {
        echo $r['Field'] . ": " . $r['Type'] . "\n";
    }
}
$res = db_query('DESCRIBE job_orders');
echo "\nJOB_ORDERS TABLE:\n";
foreach($res as $r) {
    if (strpos($r['Field'], 'status') !== false) {
        echo $r['Field'] . ": " . $r['Type'] . "\n";
    }
}
echo "\n";
