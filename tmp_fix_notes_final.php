<?php
$dir = 'c:/xampp/htdocs/printflow/customer/';
$files = glob($dir . 'order_*.php');

$notes_content = '
                <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                    <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                    <div class="shopee-form-field">
                        <div style="display:flex; justify-content:flex-end; align-items:center; gap: 10px; margin-bottom: 0.5rem; width: 100%;">
                            <span class="notes-warn" style="font-size: 0.75rem; color: #ef4444; font-weight: 800; opacity: 0; transform: translateY(5px); transition: all 0.3s ease; pointer-events: none;">Max 500 characters</span>
                            <span class="notes-counter" style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; transition: color 0.3s ease;">0 / 500</span>
                        </div>
                        <textarea name="notes" id="notes-textarea" rows="3" class="input-field notes-textarea-global" placeholder="Any special requests or instructions (e.g., preferred layout, color adjustments, or specific details)." maxlength="500" style="resize: vertical; max-height: 250px; min-height: 100px; width: 100%; border-radius: 0; padding: 0.75rem; font-size: 1rem; border: 1px solid #e2e8f0;"></textarea>
                    </div>
                </div>
';

$js_logic = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const textareas = document.querySelectorAll(".notes-textarea-global");
        textareas.forEach(textarea => {
            const wrap = textarea.closest(".shopee-form-field");
            const counter = wrap.querySelector(".notes-counter");
            const warn = wrap.querySelector(".notes-warn");

            const update = () => {
                counter.textContent = textarea.value.length + " / 500";
                if (textarea.value.length >= 500) {
                    counter.style.color = "#ef4444";
                    warn.style.opacity = "1";
                    warn.style.transform = "translateY(0)";
                } else {
                    counter.style.color = "#94a3b8";
                    warn.style.opacity = "0";
                    warn.style.transform = "translateY(5px)";
                }
            };

            textarea.addEventListener("input", update);
            update(); // initial
        });
    });
</script>
';

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);

    // 1. Remove any existing Notes row (including the one I just added if it was messy)
    // We target the shopee-form-row containing "Notes"
    $content = preg_replace(
        '/<div[^>]*class="[^"]*shopee-form-row[^"]*"[^>]*style="[^"]*align-items:\s*flex-start;[^"]*"[^>]*>\s*<label[^>]*>Notes<\/label>.*?<\/div>\s*<\/div>/is',
        $notes_content,
        $content
    );

    // Also handle cases where it might not have the align-items style yet or has different ID
    $content = preg_replace(
        '/<div[^>]*id="(?:reflNotesSection|card-notes)"[^>]*>.*?<\/div>\s*<\/div>/is',
        $notes_content,
        $content
    );

    // 2. Remove old updateNotesCounter functions to avoid conflicts or double-execution
    $content = preg_replace('/function\s+updateNotesCounter\s*\([^)]*\)\s*\{[^}]*(\}[^}]*)?\}/is', '', $content);
    $content = preg_replace('/function\s+reflUpdateNotesCounter\s*\([^)]*\)\s*\{[^}]*(\}[^}]*)?\}/is', '', $content);

    // 3. Remove the oninput attribute from any textarea if it remains
    $content = preg_replace('/oninput="(?:reflU|u)pdateNotesCounter\(this\)"/i', '', $content);

    // 4. Inject the new JS logic before the footer
    if (strpos($content, 'notes-textarea-global') !== false && strpos($content, 'const textareas = document.querySelectorAll(".notes-textarea-global");') === false) {
        $footer_tag = '<?php require_once __DIR__ . \'/../includes/footer.php\'; ?>';
        if (strpos($content, $footer_tag) !== false) {
            $content = str_replace($footer_tag, $js_logic . "\n" . $footer_tag, $content);
        } else {
            $content .= $js_logic;
        }
    }

    file_put_contents($file, $content);
}

echo "Notes normalization completed with JS logic injection.\n";
?>
