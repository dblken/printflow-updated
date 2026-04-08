<?php
/**
 * Decals / Stickers - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $shape = trim($_POST['shape'] ?? 'Custom'); 
    $w_in = trim((string)($_POST['width_in'] ?? ''));
    $h_in = trim((string)($_POST['height_in'] ?? ''));
    $size = ($w_in !== '' && $h_in !== '') ? ($w_in . 'x' . $h_in) : '';
    $finish = trim($_POST['finish'] ?? '');
    $laminate_option = trim($_POST['laminate_option'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    if (!in_array($finish, ['Glossy', 'Matte'], true)) {
        $finish = 'Glossy';
    }
    if (!in_array($laminate_option, ['With lamination', 'Without lamination'], true)) {
        $laminate_option = 'Without lamination';
    }
    if (!in_array($layout, ['With layout', 'Without layout'], true)) {
        $layout = '';
    }
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
    $notes = trim($_POST['notes'] ?? '');

    if ($w_in === '' || $h_in === '' || empty($needed_date) || $quantity < 1 || empty($layout)) {
        $error = 'Please fill in all required fields including layout.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'stickers_' . time() . '_' . rand(100, 999);
            
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Decals / Stickers',
                    'price' => 50.00, 
                    'quantity' => $quantity,
                    'category' => 'Decals & Stickers',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'shape' => $shape,
                        'size' => $size,
                        'finish' => $finish,
                        'laminate_option' => $laminate_option,
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

$page_title = 'Order Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_stickers%' AND customer_link NOT LIKE '%order_glass_stickers%' AND customer_link NOT LIKE '%order_transparent%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
$stickers_finish_val = $_POST['finish'] ?? '';
if ($stickers_finish_val !== '' && !in_array($stickers_finish_val, ['Glossy', 'Matte'], true)) {
    $stickers_finish_val = 'Glossy';
}
$stickers_lam_val = $_POST['laminate_option'] ?? '';
if ($stickers_lam_val !== '' && !in_array($stickers_lam_val, ['With lamination', 'Without lamination'], true)) {
    $stickers_lam_val = 'Without lamination';
}
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Stickers</span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Stickers'); ?>" alt="Stickers" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Stickers'">
                </div>
            </div>
            
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Decals / stickers printing</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_stickers');
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

                <form action="" method="POST" enctype="multipart/form-data" id="stickersForm" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="shape" value="Custom">

                <div class="shopee-form-row shopee-form-row-flat" id="card-branch-stickers">
                    <label class="shopee-form-label">Branch *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group opt-grid-3">
                            <?php foreach($branches as $b): ?>
                                <label class="shopee-opt-btn" >
                                    <input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="updateOpt(this)">
                                    <span><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-dim-stickers">
                    <label class="shopee-form-label">Dimensions (in) *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group mb-4">
                            <button type="button" class="shopee-opt-btn" data-width="2" data-height="2" onclick="stickerSelectDimension(2, 2, event)">2×2 in</button>
                            <button type="button" class="shopee-opt-btn" data-width="3" data-height="3" onclick="stickerSelectDimension(3, 3, event)">3×3 in</button>
                            <button type="button" class="shopee-opt-btn" data-width="4" data-height="4" onclick="stickerSelectDimension(4, 4, event)">4×4 in</button>
                            <button type="button" class="shopee-opt-btn" id="dim-others-btn" onclick="stickerSelectDimensionOthers(event)">Others</button>
                        </div>
                        <div id="dim-others-inputs" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);margin-top:1.5rem;padding-top:1.5rem;width:100%">
                            <div style="width:100%;max-width:440px">
                                <div style="display:flex;gap:8px;margin-bottom:4px">
                                    <div style="flex:1"><label class="dim-label">Width (in)</label></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><label class="dim-label">Height (in)</label></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1"><input type="number" id="stickers_width_in" name="width_in" class="input-field" placeholder="e.g. 2" data-dimension value="<?php echo htmlspecialchars($_POST['width_in'] ?? ''); ?>" min="1" max="100"></div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                    <div style="flex:1"><input type="number" id="stickers_height_in" name="height_in" class="input-field" placeholder="e.g. 3" data-dimension value="<?php echo htmlspecialchars($_POST['height_in'] ?? ''); ?>" min="1" max="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-finish-stickers">
                    <label class="shopee-form-label">Finish *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="finish" value="Glossy" style="display:none;" required <?php echo $stickers_finish_val === 'Glossy' ? 'checked' : ''; ?> onchange="updateOpt(this)"> <span>Glossy</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="finish" value="Matte" style="display:none;" <?php echo $stickers_finish_val === 'Matte' ? 'checked' : ''; ?> onchange="updateOpt(this)"> <span>Matte</span></label>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-laminate-stickers">
                    <label class="shopee-form-label">Laminate *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="laminate_option" value="With lamination" style="display:none;" required <?php echo $stickers_lam_val === 'With lamination' ? 'checked' : ''; ?> onchange="updateOpt(this)"> <span>With lamination</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="laminate_option" value="Without lamination" style="display:none;" <?php echo $stickers_lam_val === 'Without lamination' ? 'checked' : ''; ?> onchange="updateOpt(this)"> <span>Without lamination</span></label>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-layout-stickers">
                    <label class="shopee-form-label">Layout *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="layout" value="With layout" style="display:none;" required onchange="updateOpt(this)"> <span>With layout</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="layout" value="Without layout" style="display:none;" onchange="updateOpt(this)"> <span>Without layout</span></label>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-upload-stickers">
                    <label class="shopee-form-label">Upload design *</label>
                    <div class="shopee-form-field">
                        <input type="file" name="design_file" id="stickers_design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-date-stickers">
                    <label class="shopee-form-label">Needed date *</label>
                    <div class="shopee-form-field">
                        <input type="date" name="needed_date" id="stickers_needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" style="max-width: 200px;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="card-qty-stickers">
                    <label class="shopee-form-label">Quantity *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-qty-control">
                            <button type="button" class="shopee-qty-btn" onclick="stickerQtyDown()">−</button>
                            <input type="number" id="sticker-qty" name="quantity" class="shopee-qty-input" min="1" max="999" required value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>" oninput="stickerQtyClamp()">
                            <button type="button" class="shopee-qty-btn" onclick="stickerQtyUp()">+</button>
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
                        <textarea id="notes_global" name="notes" rows="3" class="input-field notes-textarea-global" 
                            placeholder="Any special requests or instructions (e.g., preferred layout, color adjustments, or specific details)." 
                            maxlength="500" 
                            oninput="reflUpdateNotesCounter(this)"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
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

<style>
/* Service Specific Tweaks */
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block;  }
.dim-sep { height: 44px; display: flex; align-items: center; color: #cbd5e1; font-weight: bold; }

#sticker-qty::-webkit-outer-spin-button,
#sticker-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#sticker-qty { -moz-appearance: textfield; appearance: textfield; }

@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; }
    .stickers-dim-row { flex-direction: column; align-items: stretch !important; }
    .dim-sep { display: none !important; }
}
.shopee-qty-control { display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.1); width: fit-content; background: rgba(15, 23, 42, 0.6); }
.shopee-qty-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: #f1f5f9; cursor: pointer; font-size: 1.25rem; transition: background 0.2s; }
.shopee-qty-btn:hover { background: rgba(255,255,255,0.05); }
.shopee-qty-input { width: 50px; height: 32px; border: none; border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1); background: none; color: #f1f5f9; text-align: center; -moz-appearance: textfield; font-weight: 600; }
</style>

<script>
var dimensionMode = 'others'; // Default to others since inputs are visible and required usually

function stickerSelectDimension(w, h, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => {
        if(b.hasAttribute('data-width') || b.id === 'dim-others-btn') {
            b.classList.remove('active');
        }
    });
    const b = e.target.closest('.shopee-opt-btn');
    if(b) b.classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    document.getElementById('stickers_width_in').value = w;
    document.getElementById('stickers_height_in').value = h;
}

function stickerSelectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => {
        if(b.hasAttribute('data-width') || b.id === 'dim-others-btn') {
            b.classList.remove('active');
        }
    });
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'flex';
    document.getElementById('stickers_width_in').value = '';
    document.getElementById('stickers_height_in').value = '';
}

function updateOpt(input) {
    const group = input.closest('.shopee-opt-group');
    if (group) {
        group.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
        input.closest('.shopee-opt-btn').classList.add('active');
    }
}

function reflUpdateNotesCounter(textarea) {
    const count = textarea.value.length;
    document.querySelector('.notes-counter').textContent = `${count} / 500`;
}

['stickers_width_in', 'stickers_height_in'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8);
        });
    }
});

document.getElementById('stickersForm').addEventListener('submit', function(e) {
    if (dimensionMode === 'preset') {
        const active = document.querySelector('.shopee-opt-btn.active[data-width]');
        if (!active) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.showOrderValidationError) {
                window.showOrderValidationError(document.getElementById('card-dim-stickers'), 'Please select a dimension.');
            } else {
                alert('Please select a dimension.');
            }
            return;
        }
    } else {
        const w = parseInt(document.getElementById('stickers_width_in').value, 10) || 0;
        const h = parseInt(document.getElementById('stickers_height_in').value, 10) || 0;

        if (w <= 0 || h <= 0 || w > 100 || h > 100) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.showOrderValidationError) {
                if (w <= 0) window.showOrderValidationError(document.getElementById('stickers_width_in'), 'Please enter width.');
                else if (w > 100) window.showOrderValidationError(document.getElementById('stickers_width_in'), 'Maximum allowed is 100 only.');
                
                if (h <= 0) window.showOrderValidationError(document.getElementById('stickers_height_in'), 'Please enter height.');
                else if (h > 100) window.showOrderValidationError(document.getElementById('stickers_height_in'), 'Maximum allowed is 100 only.');
            } else {
                alert('Please enter valid dimensions (Max 100).');
            }
            return;
        }
    }
});

function stickerQtyClamp() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10);
    if (!v || v < 1) v = 1;
    if (v > 999) v = 999;
    input.value = v;
}
function stickerQtyUp() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.min(v + 1, 999);
}
function stickerQtyDown() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.max(v - 1, 1);
}

document.addEventListener('DOMContentLoaded', function() {
    // If no dimension is selected yet, click the first one
    const firstDim = document.querySelector('.shopee-opt-btn[data-width]');
    if (firstDim) firstDim.click();

    document.querySelectorAll('.shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
