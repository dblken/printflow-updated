<?php
require_once 'includes/db.php';
require_once 'includes/xendit_config.php';

$res = xendit_generate_payment_link(2277, 800, 'test@example.com', '');
echo "RESPONSE FROM XENDIT API:\n";
print_r($res);
?>
