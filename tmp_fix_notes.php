<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

$new_placeholder = "Any special requests or instructions (e.g., preferred layout, color adjustments, or specific details).";

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);

    // Find all variations of placeholder="..." in the notes textarea and replace them
    // Make sure it only replaces the Notes placeholder, not others.
    $content = preg_replace(
        '/(<textarea[^>]*id="(?:notes|notes-textarea|notes_global)"[^>]*placeholder=")[^"]*("[^>]*>)/i',
        '$1' . $new_placeholder . '$2',
        $content
    );

    // Just in case, if id is something else but name="notes"
    $content = preg_replace(
        '/(<textarea[^>]*name="notes"[^>]*placeholder=")[^"]*("[^>]*>)/i',
        '$1' . $new_placeholder . '$2',
        $content
    );
    
    // Ensure the textarea has max-height and resize vertical
    $content = preg_replace_callback(
        '/(<textarea[^>]*name="notes"[^>]*)style="([^"]*)"/i',
        function($m) {
            $style = $m[2];
            // Remove existing resize and max-height
            $style = preg_replace('/resize\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/max-height\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/min-height\s*:\s*[^;]+;?/i', '', $style);
            $style = trim($style);
            if ($style !== '' && substr($style, -1) !== ';') $style .= ';';
            $style .= ' resize: vertical; max-height: 250px; min-height: 100px;';
            return $m[1] . 'style="' . $style . '"';
        },
        $content
    );
    
    // If it doesn't have a style attribute at all, add it
    $content = preg_replace(
        '/(<textarea[^>]*name="notes"(?![^>]*style=)[^>]*)>/i',
        '$1 style="resize: vertical; max-height: 250px; min-height: 100px;">',
        $content
    );

    file_put_contents($file, $content);
}

echo "Notes placeholders and expanding styles updated globally.\n";
?>
