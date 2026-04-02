<?php
/**
 * Sintraboard Standees - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $size = trim($_POST['size'] ?? ''); 
    $with_stand = trim($_POST['with_stand'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1); 
    $notes = trim($_POST['notes'] ?? '');

    if (empty($size) || empty($needed_date) || $quantity < 1) {
        $error = 'Please fill in size, needed date, and quantity.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'stand_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Sintraboard standees',
                    'category' => 'Sintraboard standees',
                    'price' => 0, 
                    'quantity' => $quantity,
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'size' => $size,
                        'with_stand' => $with_stand ?: 'No',
                        'needed_date' => $needed_date,
                        'notes' => $notes
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
$page_title = 'Order Sintraboard Standees - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_standees%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
$qty_default = max(1, min(999, (int)($_GET['qty'] ?? 1)));
?>
<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Sintraboard standees</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Standees'); ?>" alt="Sintraboard Standees" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Standees'">
                </div>
            </div>
            
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Sintraboard standees</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_standees');
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

                <form method="POST" enctype="multipart/form-data" id="standeeForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-3">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="standeeUpdateOpt(this)"> <span><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Dimensions -->
                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Dimensions *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <button type="button" class="shopee-opt-btn" data-width="2" data-height="5" onclick="selectDimension(2, 5, event)">2×5 ft</button>
                                <button type="button" class="shopee-opt-btn" data-width="2" data-height="6" onclick="selectDimension(2, 6, event)">2×6 ft</button>
                                <button type="button" class="shopee-opt-btn" data-width="2.5" data-height="6" onclick="selectDimension(2.5, 6, event)">2.5×6 ft</button>
                                <button type="button" class="shopee-opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                            </div>
                            <input type="hidden" name="size" id="size_hidden">
                            
                            <div id="dim-others-inputs" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);margin-top:1.5rem;padding-top:1.5rem;width:100%">
                                <div style="width:100%;max-width:440px">
                                    <div style="display:flex;gap:8px;margin-bottom:4px">
                                        <div style="flex:1"><label class="dim-label">Width (ft)</label></div>
                                        <div style="width:32px"></div>
                                        <div style="flex:1"><label class="dim-label">Height (ft)</label></div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div style="flex:1">
                                        <input type="number" id="custom_width" class="input-field" placeholder="0" min="1" max="100" step="1" data-dimension>
                                    </div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                    <div style="flex:1">
                                        <input type="number" id="custom_height" class="input-field" placeholder="0" min="1" max="100" step="1" data-dimension>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">With stand? *</label>
                        <div class="shopee-opt-group shopee-form-field">
                            <label class="shopee-opt-btn"><input type="radio" name="with_stand" value="Yes" required style="display:none;" onchange="standeeUpdateOpt(this)"> <span>Yes</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="with_stand" value="No" style="display:none;" onchange="standeeUpdateOpt(this)"> <span>No</span></label>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Upload design *</label>
                        <div class="shopee-form-field">
                            <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Needed date *</label>
                        <div class="shopee-form-field">
                            <input type="date" name="needed_date" id="standee_needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" style="max-width: 200px;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Quantity *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-qty-control">
                                <button type="button" class="shopee-qty-btn" onclick="standeeQtyDown()">−</button>
                                <input type="number" id="standee-qty" name="quantity" class="shopee-qty-input" min="1" max="999" required value="<?php echo $qty_default; ?>" oninput="standeeQtyClamp()">
                                <button type="button" class="shopee-qty-btn" onclick="standeeQtyUp()">+</button>
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

<script>
function standeeQtyUp() { const i = document.getElementById('standee-qty'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function standeeQtyDown() { const i = document.getElementById('standee-qty'); i.value = Math.max(1, (parseInt(i.value) || 1) - 1); }
function standeeQtyClamp() { const i = document.getElementById('standee-qty'); let v = parseInt(i.value) || 1; i.value = Math.max(1, Math.min(999, v)); }

function standeeUpdateOpt(input) {
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

let dimensionMode = 'preset';

function selectDimension(w, h, e) {
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
    document.getElementById('size_hidden').value = w + '×' + h + ' ft';
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => {
        if(b.hasAttribute('data-width') || b.id === 'dim-others-btn') {
            b.classList.remove('active');
        }
    });
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'block';
    syncOthersDimensions();
}

function syncOthersDimensions() {
    let w = parseFloat(document.getElementById('custom_width').value) || 0;
    let h = parseFloat(document.getElementById('custom_height').value) || 0;
    document.getElementById('size_hidden').value = (w && h) ? w + '×' + h + ' ft' : '';
}

document.getElementById('custom_width').addEventListener('input', syncOthersDimensions);
document.getElementById('custom_height').addEventListener('input', syncOthersDimensions);

document.getElementById('standeeForm').addEventListener('submit', function(e) {
    let hasError = false;
    let firstErr = null;

    document.getElementById('width-error').style.display = 'none';
    document.getElementById('height-error').style.display = 'none';

    const branch = this.querySelector('input[name="branch_id"]:checked');
    const stand = this.querySelector('input[name="with_stand"]:checked');
    const file = document.getElementById('design_file');
    const nd = document.getElementById('standee_needed_date');

    if (!branch) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="branch_id"]')?.closest('.shopee-form-row'); }
    
    if (dimensionMode === 'preset') {
        const active = document.querySelector('.shopee-opt-btn.active[data-width]');
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
        document.getElementById('size_hidden').value = active.dataset.width + 'x' + active.dataset.height + ' ft';
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
        document.getElementById('size_hidden').value = w + 'x' + h + ' ft (Custom)';
    }

    if (!stand) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="with_stand"]')?.closest('.shopee-form-row'); }
    if (!file || (!file.files || file.files.length === 0)) { hasError = true; if(!firstErr) firstErr = file.closest('.shopee-form-row'); }
    if (!nd || !nd.value) { hasError = true; if(!firstErr) firstErr = nd; }

    const notes = document.getElementById('notes_global').value;
    if (notes.length > 500) { hasError = true; if(!firstErr) firstErr = document.getElementById('notes_global'); }

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
    document.querySelectorAll('#standeeForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<style>
.shopee-form-row-flat { margin-bottom: 1.5rem; display: flex; align-items: center; }
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block;  }
.shopee-qty-control { display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.1); width: fit-content; background: rgba(15, 23, 42, 0.6); }
.shopee-qty-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: #f1f5f9; cursor: pointer; font-size: 1.25rem; transition: background 0.2s; }
.shopee-qty-btn:hover { background: rgba(255,255,255,0.05); }
.shopee-qty-input { width: 50px; height: 32px; border: none; border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1); background: none; color: #f1f5f9; text-align: center; -moz-appearance: textfield; font-weight: 600; }
.shopee-qty-input::-webkit-outer-spin-button, .shopee-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
