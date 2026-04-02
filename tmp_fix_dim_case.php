<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);

    // Remove uppercase from .dim-label CSS
    $content = preg_replace('/(\.dim-label\s*\{[^}]*)text-transform\s*:\s*uppercase\s*;?/im', '$1', $content);
    
    // Also change exact words if they are hardcoded uppercase in HTML: "WIDTH (FT)", "LENGTH (FT)", etc.
    // e.g., `<label class="dim-label">WIDTH (FT)</label>` -> `<label class="dim-label">Width (ft)</label>`
    $content = preg_replace_callback('/(<label class="dim-label"[^>]*>)\s*([^<]+)\s*(<\/label>)/i', function($m) {
        $text = strtolower(trim($m[2]));
        $text = ucfirst($text);
        return $m[1] . $text . $m[3];
    }, $content);

    // Some places might have `Dimensions (FT)`
    $content = preg_replace_callback('/(<label[^>]*>)\s*Dimensions \((FT|IN|ft|in)\) \*/i', function($m) {
        return $m[1] . 'Dimensions (' . strtolower($m[2]) . ') *';
    }, $content);

    // Check specific `WIDTH` label found in `order_create.php` from grep:
    // `<label style="... text-transform: uppercase;">WIDTH</label>`
    $content = preg_replace_callback('/(<label[^>]*>)\s*WIDTH\s*(<\/label>)/i', function($m) {
        return $m[1] . 'Width' . $m[2];
    }, $content);
    $content = preg_replace_callback('/(<label[^>]*>)\s*LENGTH\s*(<\/label>)/i', function($m) {
        return $m[1] . 'Length' . $m[2];
    }, $content);

    // Also remove text-transform:uppercase from inline styles of labels just in case
    $content = preg_replace('/(<label[^>]*style="[^"]*)text-transform\s*:\s*uppercase\s*;?([^"]*">)\s*Width(.*?)<\/label>/i', '$1$2Width$3</label>', $content);
    $content = preg_replace('/(<label[^>]*style="[^"]*)text-transform\s*:\s*uppercase\s*;?([^"]*">)\s*Length(.*?)<\/label>/i', '$1$2Length$3</label>', $content);

    file_put_contents($file, $content);
}
echo "Done fixing uppercase dimension labels.";
?>
