<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);

    // Change button styles across all services
    $original_btn_html = '                        <div class="flex gap-4 flex-1">
                            <a href="services.php" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">Back</a>
                            <button type="button" onclick="submitTshirtOrder(\'add_to_cart\')" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; border-color: var(--lp-accent); color: var(--lp-accent); font-weight: 700;">
                                <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Add To Cart
                            </button>
                            <button type="button" onclick="submitTshirtOrder(\'buy_now\')" class="shopee-btn-primary" style="flex: 1.5; height: 3.5rem; font-size: 1.1rem; font-weight: 800;">Buy Now</button>
                        </div>';
    
    // Instead of regex on tags, just use regex on the classes and styles directly
    $content = preg_replace(
        '/<div class="flex gap-4 flex-1(?: justify-end)?">\\s*<a href="[^"]*services.php" class="shopee-btn-outline" style="[^"]*flex:\s*1[^"]*height:\s*3\.5rem[^"]*">Back<\/a>\\s*<button type="button" onclick="([a-zA-Z0-9_]+)\(\'add_to_cart\'\)" class="shopee-btn-outline" style="[^"]*flex:\s*1[^"]*height:\s*3\.5rem[^"]*">\\s*<svg[^>]*>.*?<\/svg>\\s*Add To Cart\\s*<\/button>\\s*<button type="button" onclick="[^"]*\'buy_now\'\)" class="shopee-btn-primary" style="[^"]*flex:\s*1\.5[^"]*height:\s*3\.5rem[^"]*">Buy Now<\/button>\\s*<\/div>/is',
        '<div class="flex gap-4 flex-1 justify-end">
                            <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="width: 90px; height: 2.25rem; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; white-space: nowrap;">Back</a>
                            <button type="button" onclick="$1(\'add_to_cart\')" class="shopee-btn-outline" style="width: 140px; height: 2.25rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700; font-size: 0.85rem; white-space: nowrap;">
                                <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Add To Cart
                            </button>
                            <button type="button" onclick="$1(\'buy_now\')" class="shopee-btn-primary" style="width: 160px; height: 2.25rem; font-size: 0.95rem; font-weight: 800; white-space: nowrap;">Buy Now</button>
                        </div>',
        $content
    );
    
    // Notes normalization: Set textareas back
    // Match standard Notes textarea blocks gently
    $content = preg_replace(
        '/<div class="shopee-form-row[^>]*id="card-notes[^"]*"[^>]*>\s*<label[^>]*>(Notes|Special Instructions)[\s\*]*<\/label>\s*<div[^>]*>\s*<textarea[^>]*id="notes"[^>]*>.*?<\/textarea>(\s*<div[^>]*maxlength[^>]*>.*?<\/div>)?\s*<\/div>\s*<\/div>/is',
        '<div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
            <label class="shopee-form-label" style="padding-top: 0.75rem;">$1</label>
            <div class="shopee-form-field">
                <div style="display:flex; justify-content:flex-end; align-items:center; gap: 10px; margin-bottom: 0.5rem; width: 100%;">
                    <span id="notes-warn" style="font-size: 0.75rem; color: #ef4444; font-weight: 800; opacity: 0; transform: translateY(5px); transition: all 0.3s ease; pointer-events: none;">Maximum characters reached</span>
                    <span id="notes-counter" style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; transition: color 0.3s ease;">0 / 500</span>
                </div>
                <!-- FIXED PREG -->
                <textarea name="notes" id="notes-textarea" rows="3" class="input-field" placeholder="Provide extra details for your order..." maxlength="500" oninput="updateNotesCounter(this)" style="min-height: 100px; max-height: 250px; resize: vertical; width: 100%;"></textarea>
            </div>
        </div>',
        $content
    );

    // Fix upper case formatting:
    $content = preg_replace_callback('/(<label class="dim-label"[^>]*>)\s*([^<]+)\s*(<\/label>)/i', function($m) {
        $text = strtolower(trim($m[2]));
        $text = ucfirst($text);
        return $m[1] . $text . $m[3];
    }, $content);
    $content = preg_replace('/(<label[^>]*>)\s*Dimensions \(FT\) \*/i', '$1Dimensions (ft) *', $content);

    // Quantity max="999" injection on ALL files
    $content = preg_replace('/<input type="number"([^>]*name="quantity"[^>]*)max="[^"]*"/i', '<input type="number"$1max="999"', $content);

    // Opt grids for branch in all files
    // Find: <div class="shopee-opt-group"> inside branch row
    if (strpos($content, 'name="branch_id"') !== false) {
        $content = preg_replace('/(<div class="shopee-form-row[^>]*>.*?Branch \*.?<.*?<div class="shopee-form-field">\s*)<div class="shopee-opt-group">/is', '$1<div class="shopee-opt-group opt-grid-3">', $content);
    }
    
    file_put_contents($file, $content);
}
echo "Buttons, strings, and branches normalized securely.\n";
?>
