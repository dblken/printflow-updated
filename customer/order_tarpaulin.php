<?php
/**
 * Tarpaulin Printing - Service Order Form
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

// Pricelist image - check both possible locations
$base_path = __DIR__ . '/../public/';
$pricelist_url = null;
foreach (['images/tarp price range/', 'assets/images/tarp price range/'] as $subpath) {
    foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
        if (file_exists($base_path . $subpath . 'pricelist' . $ext)) {
            $pricelist_url = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/' . $subpath . 'pricelist' . $ext;
            break 2;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $unit = trim($_POST['unit'] ?? 'ft');
    $lamination = trim($_POST['lamination'] ?? '');
    $finish = trim($_POST['finish'] ?? '');
    $with_eyelets = trim($_POST['with_eyelets'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($width) || empty($height) || $quantity < 1) {
        $error = 'Please fill in Dimensions (Width, Height) and Quantity.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif (empty($needed_date)) {
        $error = 'Please select when you need the order.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'tarp_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $w = (float)$width;
                $h = (float)$height;
                $area = $w * $h;
                if ($unit === 'in') {
                    $area = $area / 144;
                }
                $unit_price = 20.00;
                
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Tarpaulin Printing',
                    'price' => $area * $unit_price * $quantity,
                    'quantity' => $quantity,
                    'category' => 'Tarpaulin',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'unit' => $unit,
                        'lamination' => $lamination,
                        'finish' => $finish,
                        'with_eyelets' => $with_eyelets,
                        'layout' => $layout,
                        'needed_date' => $needed_date,
                        'notes' => $notes
                    ]
                ];
                
                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect("order_review.php?item=" . urlencode($item_key));
                } else {
                    redirect("cart.php");
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Tarpaulin - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_tarpaulin%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }

$stats = service_order_get_page_stats('order_tarpaulin');

$avg_rating = number_format((float)($stats['avg_rating'] ?? 0), 1);
$review_count = (int)($stats['review_count'] ?? 0);
$sold_count = (int)($stats['sold_count'] ?? 0);

// Format large sold numbers
$sold_display = $sold_count;
if ($sold_count >= 1000) {
    $sold_display = number_format($sold_count / 1000, 1) . 'k';
}
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Tarpaulin</span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: '/printflow/public/assets/images/services/default.png'); ?>" alt="Tarpaulin" class="shopee-main-image" onerror="this.src='/printflow/public/assets/images/services/default.png'">
                </div>
            </div>

            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <div class="flex items-start justify-between mb-2">
                    <h1 class="mr-2 flex-1">Tarpaulin Printing</h1>
                    <button type="button" onclick="openPricelistModal()" style="border: 1px solid #0a2530; color: #0a2530; background: rgba(10, 37, 48, 0.05); padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all 0.2s;" onmouseover="this.style.background='rgba(10, 37, 48, 0.12)'" onmouseout="this.style.background='rgba(10, 37, 48, 0.05)'">
                        View Price List
                    </button>
                </div>
                
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center gap-1">
                        <?php 
                        $raw_avg = (float)($stats['avg_rating'] ?? 0);
                        for($i=1; $i<=5; $i++): 
                        ?>
                            <svg class="w-4 h-4" style="fill: <?php echo ($i <= round($raw_avg)) ? '#FBBF24' : '#E2E8F0'; ?>;" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <?php endfor; ?>
                        
                        <?php if ($review_count > 0): ?>
                            <a href="reviews.php?service_id=<?php echo $stats['service_id']; ?>" class="text-sm text-gray-500 hover:text-blue-500 hover:underline ml-1 cursor-pointer">(<?php echo number_format($review_count); ?> Reviews)</a>
                        <?php endif; ?>
                    </div>
                    <div class="h-4 w-px bg-gray-200"></div>
                    <div class="text-sm text-gray-500"><?php echo $sold_display; ?> Sold</div>
                </div>


                <form action="" method="POST" enctype="multipart/form-data" id="tarpForm" novalidate>
                    <?php echo csrf_field(); ?>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Branch *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <?php foreach($branches as $b): ?>
                                <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="updateOpt(this)"> <span><?php echo htmlspecialchars($b['branch_name']); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-dimensions">
                    <label class="shopee-form-label">Size (ft) *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <button type="button" class="shopee-opt-btn" data-width="3" data-height="4" onclick="selectDimension(3, 4, event)">3×4 FT</button>
                            <button type="button" class="shopee-opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6 FT</button>
                            <button type="button" class="shopee-opt-btn" data-width="5" data-height="8" onclick="selectDimension(5, 8, event)">5×8 FT</button>
                            <button type="button" class="shopee-opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                        </div>
                        
                                                <div id="dim-others-inputs" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);margin-top:1.5rem;padding-top:1.5rem;width:100%">
                            <div style="width:100%;max-width:440px">
                                <div style="display:flex;gap:8px;margin-bottom:4px">
                                    <div style="flex:1"><label class="dim-label">Width (ft)</label></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><label class="dim-label">Height (ft)</label></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1">
                                        <input type="number" step="0.01" id="custom_width" class="input-field" placeholder="0.0" min="1" max="100">
                                        <div id="width-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">x</div>
                                    <div style="flex:1">
                                        <input type="number" step="0.01" id="custom_height" class="input-field" placeholder="0.0" min="1" max="100">
                                        <div id="height-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:8px;margin-top:6px">
                                    <div style="flex:1"><span style="font-size:0.75rem;color:var(--lp-muted)">Standard tarpaulin width. Larger sizes may be printed in parts.</span></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><span style="font-size:0.75rem;color:var(--lp-muted)">Length can be extended. Larger sizes will be printed in parts and joined.</span></div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="width" id="width_hidden">
                        <input type="hidden" name="height" id="height_hidden">
                        <input type="hidden" name="unit" value="ft">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Finish *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="finish" value="Matte" style="display:none;" required onchange="updateOpt(this)"> <span>Matte</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="finish" value="Glossy" style="display:none;" required onchange="updateOpt(this)"> <span>Glossy</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Laminate *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="lamination" value="With Laminate" style="display:none;" required onchange="updateOpt(this)"> <span>With Laminate</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="lamination" value="Without Laminate" style="display:none;" required onchange="updateOpt(this)"> <span>Without Laminate</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Eyelets *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="with_eyelets" value="Yes" style="display:none;" required onchange="updateOpt(this)"> <span>Yes</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="with_eyelets" value="No" style="display:none;" required onchange="updateOpt(this)"> <span>No</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-upload">
                    <label class="shopee-form-label">Upload Design *</label>
                    <div class="shopee-form-field">
                        <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-layout">
                    <label class="shopee-form-label">Layout *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn"><input type="radio" name="layout" value="With Layout" style="display:none;" required onchange="updateOpt(this)"> <span>With Layout</span></label>
                        <label class="shopee-opt-btn"><input type="radio" name="layout" value="Without Layout" style="display:none;" required onchange="updateOpt(this)"> <span>Without Layout</span></label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-date">
                    <label class="shopee-form-label">Needed date *</label>
                    <div class="shopee-form-field">
                        <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-qty">
                    <label class="shopee-form-label">Quantity *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-qty-control">
                            <button type="button" class="shopee-qty-btn" onclick="decreaseQty()">−</button>
                            <input type="number" id="quantity-input" name="quantity" class="shopee-qty-input" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                            <button type="button" class="shopee-qty-btn" onclick="increaseQty()">+</button>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                    <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                    <div class="shopee-form-field">
                        <div style="display:flex; justify-content:flex-end; margin-bottom: 0.25rem; ">
                            <span id="notes-counter" style="font-size: 0.7rem; color: var(--lp-muted); font-weight: 700;">0 / 500</span>
                        </div>
                        <textarea name="notes" id="notes-textarea" rows="3" class="input-field" placeholder="Any special instructions..." maxlength="500" oninput="updateNotesCounter(this)"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        <div id="notes-warn" class="text-xs font-bold mt-1" style="display:none; color: #ef4444;">Maximum characters reached.</div>
                    </div>
                </div>

                <div class="shopee-form-row pt-8">
                    <div style="width: 160px;" class="hidden md:block"></div>
                    <div class="flex gap-4 flex-1">
                        <button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; border-color: var(--lp-accent); color: var(--lp-accent); font-weight: 700;">
                            <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add To Cart
                        </button>
                        <button type="submit" name="action" value="buy_now" id="buyNowBtn" class="shopee-btn-primary" style="flex: 1.5; height: 3.5rem; font-size: 1.1rem; font-weight: 800;">Buy Now</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>



<!-- Pricelist Modal -->
<div id="pricelist-modal" style="display: none; position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(0,0,0,0.5);">
    <div onclick="closePricelistModal()" style="position: absolute; inset: 0;"></div>
    <div style="position: relative; background: #0a2530; border: 1px solid rgba(83, 197, 224, 0.28); border-radius: 1rem; max-width: 72vw; max-height: 90vh; overflow: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.45);">
        <button onclick="closePricelistModal()" style="position: absolute; top: 0.75rem; right: 0.75rem; z-index: 10; width: 36px; height: 36px; border: 1px solid rgba(83, 197, 224, 0.28); background: rgba(15, 53, 68, 0.95); border-radius: 50%; cursor: pointer; font-size: 1.25rem; line-height: 1; color: #d8edf5;">×</button>
        <?php if ($pricelist_url): ?>
        <img src="<?php echo htmlspecialchars($pricelist_url); ?>" alt="Tarpaulin Pricelist" style="width: 100%; max-width: 640px; display: block;">
        <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: #6b7280;">Pricelist image not found.</div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Service Specific Tweaks */
.dim-sep { height: 44px; display: flex; align-items: center; color: #cbd5e1; font-weight: bold; }
.dim-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.need-qty-row { display: flex; gap: 16px; width: 100%; }

@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; }
}
</style>

<script>
let dimensionMode = 'preset';

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
function openPricelistModal() { document.getElementById('pricelist-modal').style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function closePricelistModal() { document.getElementById('pricelist-modal').style.display = 'none'; document.body.style.overflow = ''; }

function syncDimensionToHidden() {
    const wh = document.getElementById('width_hidden');
    const hh = document.getElementById('height_hidden');
    if (dimensionMode === 'preset') {
        const btn = document.querySelector('.shopee-opt-btn.active[data-width]');
        wh.value = btn ? btn.dataset.width : '';
        hh.value = btn ? btn.dataset.height : '';
    } else {
        wh.value = document.getElementById('custom_width').value;
        hh.value = document.getElementById('custom_height').value;
    }
}

function selectDimension(w, h, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    e.currentTarget.classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    syncDimensionToHidden();
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'block';
    syncDimensionToHidden();
}

function increaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function decreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

document.getElementById('tarpForm').addEventListener('submit', function(e) {
    syncDimensionToHidden();
    
    let hasError = false;
    let firstErrorField = null;

    // Reset errors
    document.getElementById('width-error').style.display = 'none';
    document.getElementById('height-error').style.display = 'none';
    document.getElementById('notes-warn').style.display = 'none';

    if (dimensionMode === 'preset') {
        const active = document.querySelector('.shopee-opt-btn.active[data-width]');
        if (!active) {
            alert('Please select a dimension.');
            e.preventDefault();
            return;
        }
    } else {
        const w = parseFloat(document.getElementById('custom_width').value) || 0;
        const h = parseFloat(document.getElementById('custom_height').value) || 0;

        if (w <= 0 || h <= 0) {
            alert('Please enter custom dimensions.');
            e.preventDefault();
            return;
        }

        if (w > 100) {
            document.getElementById('width-error').style.display = 'block';
            hasError = true;
            if (!firstErrorField) firstErrorField = document.getElementById('custom_width');
        }

        if (h > 100) {
            document.getElementById('height-error').style.display = 'block';
            hasError = true;
            if (!firstErrorField) firstErrorField = document.getElementById('custom_height');
        }
    }

    const notes = document.getElementById('notes-textarea').value;
    if (notes.length > 500) {
        document.getElementById('notes-warn').style.display = 'block';
        hasError = true;
        if (!firstErrorField) firstErrorField = document.getElementById('notes-textarea');
    }

    if (hasError) {
        e.preventDefault();
        if (firstErrorField) {
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErrorField.focus();
        }
        return;
    }
});

var finishIcon = document.getElementById('finish-info-icon');
var finishTooltip = document.getElementById('finish-tooltip');
if (finishIcon && finishTooltip) {
    finishIcon.addEventListener('mouseenter', () => finishTooltip.classList.add('visible'));
    finishIcon.addEventListener('mouseleave', () => finishTooltip.classList.remove('visible'));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
