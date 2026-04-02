<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

$notes_html = '
                <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                    <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                    <div class="shopee-form-field">
                        <div style="display:flex; justify-content:flex-end; align-items:center; gap: 10px; margin-bottom: 0.5rem; width: 100%;">
                            <span class="notes-warn" style="font-size: 0.75rem; color: #ef4444; font-weight: 800; opacity: 0; transform: translateY(5px); transition: all 0.3s ease; pointer-events: none;">Max 500 characters</span>
                            <span class="notes-counter" style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; transition: color 0.3s ease;">0 / 500</span>
                        </div>
                        <textarea name="notes" rows="3" class="input-field notes-textarea-global" placeholder="Any special requests or instructions (e.g., preferred layout, color adjustments, or specific details)." maxlength="500" style="resize: vertical; max-height: 250px; min-height: 100px; width: 100%; border-radius: var(--lp-radius); padding: 0.75rem; font-size: 1rem; border: 1px solid #e2e8f0;"></textarea>
                    </div>
                </div>
';

$js_logic = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const textareas = document.querySelectorAll(".notes-textarea-global");
        textareas.forEach(textarea => {
            textarea.addEventListener("input", function() {
                const wrap = this.closest(".shopee-form-field");
                const counter = wrap.querySelector(".notes-counter");
                const warn = wrap.querySelector(".notes-warn");
                
                counter.textContent = this.value.length + " / 500";
                
                if (this.value.length >= 500) {
                    counter.style.color = "#ef4444";
                    warn.style.opacity = "1";
                    warn.style.transform = "translateY(0)";
                } else {
                    counter.style.color = "#94a3b8";
                    warn.style.opacity = "0";
                    warn.style.transform = "translateY(5px)";
                }
            });
        });
    });
</script>
';

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);

    // Completely replace existing notes sections. 
    // Usually they are <div class="shopee-form-row... Notes ... </div> </div>
    // Let's use a regex to surgically find the Notes section and replace it.
    
    // Pattern to aggressively find the Notes shopee-form-row
    $content = preg_replace(
        '/<div[^>]*class="[^"]*shopee-form-row[^>]*>[^<]*<label[^>]*>(?:Notes|Special Instructions)?<\/label>.*?<\/textarea>\s*<\/div>\s*<\/div>/is',
        $notes_html,
        $content
    );

    // Also replace `reflNotesSection` specifically if it exists differently
    $content = preg_replace(
        '/<div id="reflNotesSection"[^>]*>.*?<\/textarea>\s*<\/div>\s*<\/div>/is',
        $notes_html,
        $content
    );

    // Append JS logic right before </body> or Footer if not already present
    if (strpos($content, 'notes-textarea-global') !== false && strpos($content, '.notes-textarea-global') === false) {
        $content = str_replace('<?php require_once __DIR__ . \'/../includes/footer.php\'; ?>', $js_logic . "\n" . '<?php require_once __DIR__ . \'/../includes/footer.php\'; ?>', $content);
    }

    file_put_contents($file, $content);
}

echo "Notes sections normalized cleanly.\n";
?>
