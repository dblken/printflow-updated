<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);

    // Some files might not use 'shopee-form-row' if they are heavily customized, but all mine do.
    // We want to extract Needed Date, Quantity, and Notes, and append them just before the Submit buttons.
    
    // 1. Extract Needed Date
    $needed_date_html = '';
    if (preg_match('/<div class="shopee-form-row[^>]*id="(?:card-date[^"]*|needed-date-section|date-section)"[^>]*>.*?<\/div>\s*<\/div>/is', $content, $m)) {
        $needed_date_html = $m[0];
        $content = str_replace($m[0], '', $content);
    } elseif (preg_match('/<div class="shopee-form-row[^>]*>\s*<label[^>]*>.*?(?:Needed date|Date needed).*?<\/label>.*?<\/div>\s*<\/div>/is', $content, $m)) {
        $needed_date_html = $m[0];
        $content = str_replace($m[0], '', $content);
    }
    
    // 2. Extract Quantity
    $qty_html = '';
    if (preg_match('/<div class="shopee-form-row[^>]*id="card-qty[^"]*"[^>]*>.*?<\/div>\s*<\/div>/is', $content, $m)) {
        $qty_html = $m[0];
        $content = str_replace($m[0], '', $content);
    } elseif (preg_match('/<div class="shopee-form-row[^>]*>\s*<label[^>]*>.*?Quantity.*?<\/label>.*?<\/div>\s*<\/div>/is', $content, $m)) {
        $qty_html = $m[0];
        $content = str_replace($m[0], '', $content);
    }

    // 3. Extract Notes
    $notes_html = '';
    if (preg_match('/<div class="shopee-form-row[^>]*>\s*<label[^>]*>.*?Notes.*?<\/label>.*?<\/textarea>\s*<\/div>\s*<\/div>/is', $content, $m)) {
        $notes_html = $m[0];
        $content = str_replace($m[0], '', $content);
    }

    // Now insert them in order before the buttons container
    // The buttons container is usually `<div class="shopee-form-row pt-8">` or similar that contains "Back", "Add To Cart"
    if ($needed_date_html || $qty_html || $notes_html) {
        $replacement = $needed_date_html . "\n\n" . $qty_html . "\n\n" . $notes_html . "\n\n$0";
        $content = preg_replace('/<div class="shopee-form-row pt-8">/i', $replacement, $content);
    }
    
    file_put_contents($file, $content);
}

echo "Sorted Needed Date, Quantity, Notes at the bottom of all forms.\n";
?>
