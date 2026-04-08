<?php
/**
 * Transparent Stickers - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $surface_application = trim($_POST['surface_application'] ?? '');
    $surface_other = trim($_POST['surface_other'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    $surface_display = ($surface_application === 'Others' && $surface_other) ? $surface_other : $surface_application;

    if (empty($surface_display) || empty($dimensions) || empty($layout) || empty($lamination) || $quantity < 1 || empty($needed_date)) {
        $error = 'Please fill in branch, surface, dimensions, layout, lamination, quantity, and needed date.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($additional_notes) : strlen($additional_notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif ($surface_application === 'Others' && empty($surface_other)) {
        $error = 'Please specify your surface type when others is selected.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design file.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'trans_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Transparent sticker printing',
                    'price' => 0, // Calculated at checkout or review
                    'quantity' => $quantity,
                    'category' => 'Sticker printing',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'surface_application' => $surface_display,
                        'dimensions' => $dimensions,
                        'layout' => $layout,
                        'lamination' => $lamination,
                        'needed_date' => $needed_date,
                        'additional_notes' => $additional_notes
                    ]
                ];

                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Transparent Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_transparent%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Transparent stickers</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Transparent+Stickers'); ?>" alt="Transparent Stickers" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Transparent+Stickers'">
                </div>
            </div>
            
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Transparent sticker printing</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_transparent');
                $raw_avg = (float)($stats['avg_rating'] ?? 0);
                $review_count = (int)($stats['review_count'] ?? 0);
                $sold_count = (int)($stats['sold_count'] ?? 0);
                $sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
                ?>
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center gap-1">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <svg class="w-4 h-4" style="fill: <?php echo ($i <= round($raw_avg)) ? '#FBBF24' : '#E2E8F0'; ?>;" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <?php endfor; ?>
                        
                        <?php if ($review_count > 0): ?>
                            <a href="reviews.php?service_id=<?php echo $stats['service_id']; ?>" class="text-sm text-gray-500 hover:text-blue-500 hover:underline ml-1 cursor-pointer">(<?php echo number_format($review_count); ?> Reviews)</a>
                        <?php endif; ?>
                    </div>
                    <div class="h-4 w-px bg-gray-200"></div>
                    <div class="text-sm text-gray-500"><?php echo $sold_display; ?> Sold</div>
                </div>

                <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="transForm" novalidate>
                    <?php echo csrf_field(); ?>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Branch *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group opt-grid-3">
                            <?php foreach ($branches as $b): ?>
                                <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="transUpdateOpt(this)"> <span><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Application *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Glass (window/door/storefront)" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Glass</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Plastic/acrylic" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Plastic/acrylic</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Metal" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Metal</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Smooth painted wall" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Painted wall</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Mirror" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Mirror</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Others" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Others</span></label>
                        </div>
                        <div id="surface-other-wrap" style="display: none; margin-top: 1rem; ">
                            <input type="text" name="surface_other" id="surface_other" class="input-field" placeholder="Specify surface type">
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Dimensions *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn shopee-dim-btn"><input type="radio" name="dim_preset" value="2x2" style="display:none;" onchange="selectDimPreset('2x2', event); transUpdateOpt(this)"> <span>2×2 ft</span></label>
                            <label class="shopee-opt-btn shopee-dim-btn"><input type="radio" name="dim_preset" value="3x3" style="display:none;" onchange="selectDimPreset('3x3', event); transUpdateOpt(this)"> <span>3×3 ft</span></label>
                            <label class="shopee-opt-btn shopee-dim-btn"><input type="radio" name="dim_preset" value="4x4" style="display:none;" onchange="selectDimPreset('4x4', event); transUpdateOpt(this)"> <span>4×4 ft</span></label>
                            <label class="shopee-opt-btn" id="dim-others-btn"><input type="radio" name="dim_preset" value="Others" style="display:none;" onchange="selectDimOthers(event); transUpdateOpt(this)"> <span>Others</span></label>
                        </div>
                        <input type="hidden" name="dimensions" id="dimensions_hidden">
                        <div id="dim-others-inputs" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);margin-top:1.5rem;padding-top:1.5rem;width:100%">
                            <div style="width:100%;max-width:440px">
                                <div style="display:flex;gap:8px;margin-bottom:4px">
                                    <div style="flex:1"><label class="dim-label">Width (ft)</label></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><label class="dim-label">Height (ft)</label></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1">
                                        <input type="number" id="custom_width" class="input-field" placeholder="0" min="1" max="100" data-dimension>
                                    </div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                    <div style="flex:1">
                                        <input type="number" id="custom_height" class="input-field" placeholder="0" min="1" max="100" data-dimension>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Layout *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="layout" value="With layout" required style="display:none;" onchange="transUpdateOpt(this)"> <span>With layout</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="layout" value="Without layout" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Without layout</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Lamination *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="lamination" value="With lamination" required style="display:none;" onchange="transUpdateOpt(this)"> <span>With lamination</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="lamination" value="Without lamination" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Without lamination</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Upload design *</label>
                    <div class="shopee-form-field">
                        <input type="file" id="design_file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Needed date *</label>
                    <div class="shopee-form-field">
                        <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Quantity *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-qty-control">
                            <button type="button" class="shopee-qty-btn" onclick="transDecreaseQty()">−</button>
                            <input type="number" id="quantity-input" name="quantity" class="shopee-qty-input" min="1" max="999" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                            <button type="button" class="shopee-qty-btn" onclick="transIncreaseQty()">+</button>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                    <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                    <div class="shopee-form-field">
                        <div style="display:flex; justify-content:flex-end; align-items:center; gap: 10px; margin-bottom: 0.5rem; width: 100%;">
                            <span class="notes-warn">Max 500 characters</span>
                            <span class="notes-counter">0 / 500</span>
                        </div>
                        <textarea id="notes_global" name="additional_notes" rows="3" class="input-field notes-textarea-global" 
                            placeholder="Any special requests or instructions (e.g., preferred layout, color adjustments, or specific details)." 
                            maxlength="500" 
                            oninput="reflUpdateNotesCounter(this)"><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="shopee-form-row pt-8">
                    <div style="width: 160px;" class="hidden md:block"></div>
                    <div class="flex gap-4 flex-1 justify-end">
                        <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="width: 90px; height: 2.25rem; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; white-space: nowrap;">Back</a>
                        <button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="width: 140px; height: 2.25rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700; font-size: 0.85rem; white-space: nowrap;" title="Add to Cart">
                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add to cart
                        </button>
                        <button type="submit" name="action" value="buy_now" class="shopee-btn-primary" style="width: 160px; height: 2.25rem; font-size: 0.95rem; font-weight: 800; white-space: nowrap;">Buy now</button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<style>
.shopee-form-row-flat { margin-bottom: 1.5rem; display: flex; align-items: center; }
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block;  }
.shopee-qty-control { display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.1); width: fit-content; background: rgba(15, 23, 42, 0.6); }
.shopee-qty-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: #f1f5f9; cursor: pointer; font-size: 1.25rem; transition: background 0.2s; }
.shopee-qty-btn:hover { background: rgba(255,255,255,0.05); }
.shopee-qty-input { width: 50px; height: 32px; border: none; border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1); background: none; color: #f1f5f9; text-align: center; -moz-appearance: textfield; font-weight: 600; }
</style>

<script>
function transUpdateOpt(input) {
    const group = input.closest('.shopee-opt-group');
    if (group) {
        group.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
        input.closest('.shopee-opt-btn').classList.add('active');
    }
    if (input.name === 'surface_application') {
        document.getElementById('surface-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
}

function reflUpdateNotesCounter(textarea) {
    const count = textarea.value.length;
    document.querySelector('.notes-counter').textContent = `${count} / 500`;
}

var dimensionMode = 'preset';

function transIncreaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function transDecreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

function syncDimensions() {
    const h = document.getElementById('dimensions_hidden');
    if (dimensionMode === 'preset') {
        const btnInput = document.querySelector('input[name="dim_preset"]:checked');
        if (btnInput && btnInput.value !== 'Others') {
            h.value = btnInput.value + ' ft';
        } else {
            h.value = '';
        }
    } else {
        const w = document.getElementById('custom_width').value.trim();
        const g = document.getElementById('custom_height').value.trim();
        h.value = (w && g) ? w + 'x' + g + ' ft' : '';
    }
}

function selectDimPreset(dim, e) {
    dimensionMode = 'preset';
    document.getElementById('dim-others-inputs').style.display = 'none';
    syncDimensions();
}

function selectDimOthers(e) {
    dimensionMode = 'others';
    document.getElementById('dim-others-inputs').style.display = 'flex';
    syncDimensions();
}

document.getElementById('transForm').addEventListener('submit', function(e) {
    syncDimensions();
    let hasError = false;
    let firstErr = null;

    const branch = this.querySelector('input[name="branch_id"]:checked');
    const surface = this.querySelector('input[name="surface_application"]:checked');
    const dimVal = document.getElementById('dimensions_hidden').value;
    const layout = this.querySelector('input[name="layout"]:checked');
    const lam = this.querySelector('input[name="lamination"]:checked');
    const file = document.getElementById('design_file');
    const nd = document.getElementById('needed_date');

    if (!branch) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="branch_id"]')?.closest('.shopee-form-row'); }
    if (!surface) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="surface_application"]')?.closest('.shopee-form-row'); }
    if (!layout) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="layout"]')?.closest('.shopee-form-row'); }
    if (!lam) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="lamination"]')?.closest('.shopee-form-row'); }
    if (!file || !file.files || file.files.length === 0) { hasError = true; if(!firstErr) firstErr = file.closest('.shopee-form-row'); }
    if (!nd || !nd.value) { hasError = true; if(!firstErr) firstErr = nd; }

    const notes = document.getElementById('notes_global').value;
    if (notes.length > 500) {
        hasError = true;
        if (!firstErr) firstErr = document.getElementById('notes_global');
    }

    if (hasError) {
        e.preventDefault();
        if (firstErr) {
            firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErr.focus();
        }
        return;
    }
});

document.getElementById('transForm').addEventListener('submit', function(e) {
    if (dimensionMode === 'preset') {
        const active = document.querySelector('input[name="dim_preset"]:checked');
        if (!active) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.showOrderValidationError) {
                window.showOrderValidationError(document.getElementById('dim-others-btn').closest('.shopee-opt-group'), 'Please select a dimension.');
            } else {
                alert('Please select a dimension.');
            }
            return;
        }
    } else {
        const w = parseInt(document.getElementById('custom_width').value, 10) || 0;
        const h = parseInt(document.getElementById('custom_height').value, 10) || 0;

        if (w <= 0 || h <= 0 || w > 100 || h > 100) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.showOrderValidationError) {
                if (w <= 0) window.showOrderValidationError(document.getElementById('custom_width'), 'Please enter width.');
                else if (w > 100) window.showOrderValidationError(document.getElementById('custom_width'), 'Maximum allowed is 100 only.');
                
                if (h <= 0) window.showOrderValidationError(document.getElementById('custom_height'), 'Please enter height.');
                else if (h > 100) window.showOrderValidationError(document.getElementById('custom_height'), 'Maximum allowed is 100 only.');
            } else {
                alert('Please enter valid dimensions (Max 100).');
            }
            return;
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#transForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
