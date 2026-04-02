<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    // 1. Branch: 3 columns per row
    // Locate the branch row explicitly, or just replace the shopee-opt-group inside it.
    // Looking for "Branch *" label and its following <div class="shopee-opt-group">
    $content = preg_replace('/(<label[^>]*>\s*Branch \*?<\/label>\s*<div[^>]*>\s*<div class="shopee-opt-group)\b/i', '$1 opt-grid-3', $content);

    // 2. Quantity validation: max 999
    // <input ... name="quantity" ... max="999" ... oninput="clampQty()">
    // We update JS and HTML for notes formatting
    
    // Notes section standardizing
    // We find `<label ...>Notes</label>` and replace the associated textarea logic.
    $notes_html = <<<HTML
<div style="display:flex; justify-content:flex-end; align-items:center; gap: 10px; margin-bottom: 0.5rem; width: 100%;">
                            <span id="notes-warn" style="font-size: 0.75rem; color: #ef4444; font-weight: 800; opacity: 0; transform: translateY(5px); transition: all 0.3s ease; pointer-events: none;">Maximum characters reached.</span>
                            <span id="notes-counter" style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; transition: color 0.3s ease;">0 / 500</span>
                        </div>
                        <textarea name="notes" id="notes-textarea" rows="3" class="input-field" placeholder="Provide extra details for your order..." maxlength="500" oninput="globalUpdateNotesCounter(this)" style="min-height: 100px; max-height: 250px; resize: vertical; width: 100%;"></textarea>
HTML;

    // Search and replace notes div
    // We match from `<div style="display:flex;` down to `</textarea>` or `</div>`
    $content = preg_replace('/<div style="display:flex;.*?<textarea[^>]*name="notes"[^>]*>.*?<\/textarea>(\s*<div id="notes-warn" [^>]*>.*?<\/div>)?/is', $notes_html, $content);
    
    // We inject globalUpdateNotesCounter to JS if not present
    $global_js = <<<JS
function globalUpdateNotesCounter(el) {
    const count = el.value.length;
    const counter = document.getElementById('notes-counter');
    const warn = document.getElementById('notes-warn');
    if(counter && warn) {
        counter.textContent = count + ' / 500';
        if(count >= 500) {
            counter.style.color = '#ef4444';
            warn.style.opacity = '1';
            warn.style.transform = 'translateY(0)';
        } else {
            counter.style.color = '#94a3b8';
            warn.style.opacity = '0';
            warn.style.transform = 'translateY(5px)';
        }
    }
}
JS;

    if (strpos($content, 'globalUpdateNotesCounter') === false) {
        $content = str_replace('<script>', "<script>\n" . $global_js . "\n", $content);
    }
    
    // Ensure quantity validator enforces 999 max oninput
    // Find functions like stickerQtyClamp(), default clampQty() etc. and check their max 999 logic.
    // Better yet, add a unified clamp qty logic.
    $qty_js = <<<JS
function unifiedClampQty(el) {
    let v = parseInt(el.value, 10);
    if (!v || v < 1) v = 1;
    if (v > 999) {
        v = 999;
        // Optionally show indicator
        let warn = document.getElementById('qty-warn');
        if(warn) { warn.style.display = 'block'; setTimeout(() => warn.style.display='none', 2000); }
    }
    el.value = v;
}
JS;
    if (strpos($content, 'unifiedClampQty') === false) {
        $content = str_replace('<script>', "<script>\n" . $qty_js . "\n", $content);
    }

    // Update the qty input oninput property
    $content = preg_replace('/(<input[^>]*name="quantity"[^>]*oninput=")([^"]+)(")/i', '${1}unifiedClampQty(this)$3', $content);
    // If it doesn't have oninput, add it:
    $content = preg_replace('/(<input[^>]*name="quantity"(?!.*oninput)[^>]*)>/i', '$1 oninput="unifiedClampQty(this)">', $content);

    // Make sure max="999" is set
    $content = preg_replace('/(<input[^>]*name="quantity"(?!.*max="999")[^>]*)>/i', '$1 max="999">', $content);

    // Add qty validator div note below qty control
    if (strpos($content, 'id="qty-warn"') === false) {
        $content = preg_replace('/(name="quantity"[^>]*>.*?<\/button>\s*<\/div>)/is', "$1\n<div id=\"qty-warn\" style=\"display:none; color: #ef4444; font-size: 0.75rem; font-weight: 700; margin-top: 4px;\">Maximum quantity is 999</div>", $content);
    }
    
    file_put_contents($file, $content);
}
echo "Done applying specific UI UX requirements.";
?>
