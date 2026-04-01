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
        $error = 'Please fill in Branch, Surface, Dimensions, Layout, Lamination, Quantity, and Needed Date.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($additional_notes) : strlen($additional_notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif ($surface_application === 'Others' && empty($surface_other)) {
        $error = 'Please specify your surface type when Others is selected.';
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
                    'name' => 'Transparent Sticker Printing',
                    'price' => 0, // Calculated at checkout or review
                    'quantity' => $quantity,
                    'category' => 'Sticker Printing',
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
            <span class="font-semibold text-gray-900">Transparent Stickers</span>
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
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Transparent Stickers</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_transparent');
                $raw_avg = (float)($stats['avg_rating'] ?? 0);
                $review_count = (int)($stats['review_count'] ?? 0);
                $sold_count = (int)($stats['sold_count'] ?? 0);
                $sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
                
                $_s_name = 'PrintFlow Service';
                $_s_row = db_query("SELECT name FROM services WHERE customer_link LIKE '%order_transparent%' LIMIT 1");
                if(!empty($_s_row)) { $_s_name = $_s_row[0]['name']; }
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
                        <div class="shopee-opt-group">
                            <?php foreach ($branches as $b): ?>
                                <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="transUpdateOpt(this)"> <span><?php echo htmlspecialchars($b['branch_name']); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Application *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Glass (Window/Door/Storefront)" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Glass</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Plastic / Acrylic" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Plastic/Acrylic</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Metal" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Metal</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="surface_application" value="Smooth Painted Wall" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Painted Wall</span></label>
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
                                        <input type="number" step="0.01" id="custom_width" class="input-field" placeholder="0.0" min="0.1" max="100">
                                        <div id="width-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                    <div style="flex:1">
                                        <input type="number" step="0.01" id="custom_height" class="input-field" placeholder="0.0" min="0.1" max="100">
                                        <div id="height-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Layout *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="layout" value="With Layout" required style="display:none;" onchange="transUpdateOpt(this)"> <span>With Layout</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="layout" value="Without Layout" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Without Layout</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Lamination *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="lamination" value="With Laminate" required style="display:none;" onchange="transUpdateOpt(this)"> <span>With Laminate</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="lamination" value="Without Laminate" required style="display:none;" onchange="transUpdateOpt(this)"> <span>Without Laminate</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Upload Design *</label>
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
                        <div style="display:flex; justify-content:flex-end; margin-bottom: 0.25rem; ">
                            <span id="notes-counter" style="font-size: 0.7rem; color: var(--lp-muted); font-weight: 700;">0 / 500</span>
                        </div>
                        <textarea name="additional_notes" id="notes-textarea" rows="3" class="input-field" placeholder="Any special instructions..." maxlength="500" oninput="updateNotesCounter(this)"><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                        <div id="notes-warn" class="text-xs font-bold mt-1" style="display:none; color: #ef4444;">Maximum characters reached.</div>
                    </div>
                </div>

                <div class="shopee-form-row pt-8">
                    <div style="width: 160px;" class="hidden md:block"></div>
                    <div class="flex gap-4 flex-1">
                        <a href="services.php" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">Back</a>
                        <button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; border-color: var(--lp-accent); color: var(--lp-accent); font-weight: 700;">
                            <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add To Cart
                        </button>
                        <button type="submit" name="action" value="buy_now" class="shopee-btn-primary" style="flex: 1.5; height: 3.5rem; font-size: 1.1rem; font-weight: 800;">Buy Now</button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.shopee-dim-custom-row { display: flex; align-items: center; gap: 8px; }
.field-error { color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem; font-weight: 600; }
</style>

<script>
function updateNotesCounter(el) {
    const counter = document.getElementById('notes-counter');
    const warn = document.getElementById('notes-warn');
    counter.textContent = el.value.length + ' / 500';
    if(el.value.length >= 500) {
        counter.style.color = '#ef4444';
        warn.style.display = 'block';
    } else {
        counter.style.color = 'var(--lp-muted)';
        warn.style.display = 'none';
    }
}

function transUpdateOpt(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(function(r) {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
    if (name === 'surface_application') {
        document.getElementById('surface-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
}

let dimensionMode = 'preset';

function transIncreaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function transDecreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

function syncDimensions() {
    const h = document.getElementById('dimensions_hidden');
    if (dimensionMode === 'preset') {
        const btnInput = document.querySelector('input[name="dim_preset"][value!="Others"]:checked');
        if (btnInput) {
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

    const othersChecked = document.querySelector('input[name="dim_preset"][value="Others"]:checked');
    const cw = document.getElementById('custom_width').value;
    const ch = document.getElementById('custom_height').value;

    if (!branch) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="branch_id"]')?.closest('.shopee-form-row'); }
    if (!surface) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="surface_application"]')?.closest('.shopee-form-row'); }
    if (!dimVal) { hasError = true; if(!firstErr) firstErr = document.getElementById('dim-others-btn')?.closest('.shopee-form-row'); }

    if (othersChecked) {
        if (parseFloat(cw) > 100 || parseFloat(ch) > 100) {
            hasError = true;
            if(!firstErr) firstErr = document.getElementById('custom_width');
            alert('Maximum size is 100 ft.');
        }
    }

    if (!layout) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="layout"]')?.closest('.shopee-form-row'); }
    if (!lam) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="lamination"]')?.closest('.shopee-form-row'); }
    if (!file || !file.files || file.files.length === 0) { hasError = true; if(!firstErr) firstErr = file.closest('.shopee-form-row'); }
    if (!nd || !nd.value) { hasError = true; if(!firstErr) firstErr = nd; }

    if (hasError) {
        e.preventDefault();
        if (firstErr) {
            firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErr.focus();
        }
        return;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#transForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>
<style>
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.dim-sep { height: 44px; display: flex; align-items: center; color: #cbd5e1; font-weight: bold; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
