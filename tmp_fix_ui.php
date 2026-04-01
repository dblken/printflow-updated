<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    // 1. Fix Labels
    // Convert <label class="shopee-form-label">SOMETHING here *</label> to <label class="shopee-form-label">Something here *</label>
    $content = preg_replace_callback('/(<label class="shopee-form-label"[^>]*>)([^<]+)(<\/label>)/i', function($matches) {
        $prefix = $matches[1];
        $text = trim($matches[2]);
        $suffix = $matches[3];
        
        // Exclude specific HTML inside label if any, though my labels are plain text usually except for some with <span>
        // e.g., "DESIGN <span..." we handle separately if needed, but my regex ([^<]+) won't match strings with `<`.
        
        // Lowercase the text, then capitalize first letter.
        $text = strtolower($text);
        $text = ucfirst($text);
        
        // Replace " (ft)" with " (ft)"
        $text = str_replace([' (in)', ' (ft)'], [' (in)', ' (ft)'], $text);
        
        return $prefix . $text . $suffix;
    }, $content);
    
    // 2. Fix Add to Cart Button
    // Find the small box button and replace with full button
    $small_cart_regex = '/<button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="width:3\.5rem;height:3\.5rem;[^>]+>.+?<\/button>/is';
    
    $full_cart_button = '<button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700;" title="Add to Cart">
                            <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add To Cart
                        </button>';
                        
    $content = preg_replace($small_cart_regex, $full_cart_button, $content);
    
    // Also check for the label with a span inside <label class="shopee-form-label">DESIGN <span ...>*</span></label>
    $content = preg_replace_callback('/(<label class="shopee-form-label"[^>]*>)([A-Z]+)(\s*<span[^>]*>\s*\*\s*<\/span>\s*<\/label>)/i', function($matches) {
        return $matches[1] . ucfirst(strtolower($matches[2])) . $matches[3];
    }, $content);

    // Some strings might have ? like "Custom print? *" -> "Custom print? *"
    
    file_put_contents($file, $content);
}
echo "Done replacing labels and cart buttons.\n";
?>
