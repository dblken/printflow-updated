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
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $size = trim($_POST['size'] ?? ''); $with_stand = trim($_POST['with_stand'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1); $notes = trim($_POST['notes'] ?? '');
    if (empty($size) || empty($needed_date) || $quantity < 1) {
        $error = 'Please fill in Size and Quantity.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    }

    if (empty($error)) {
        // Process file for session
        $tmp_dir = service_order_temp_dir();
        $db_data = file_get_contents($_FILES['design_file']['tmp_name']);
        $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
        $tmp_filename = uniqid('standee_') . '.' . $ext;
        $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
        file_put_contents($tmp_path, $db_data);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
        finfo_close($finfo);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $product_id = 0; // Service order
        $item_key = 'stand_' . time();
        
        $_SESSION['cart'][$item_key] = [
            'product_id'     => $product_id,
            'source_page'    => 'services',
            'branch_id'      => $branch_id,
            'name'           => 'Sintraboard Standees',
            'category'       => 'Sintraboard Standees',
            'price'          => 0, // Determined at review or staff side
            'quantity'       => $quantity,
            'image'          => '🕴️',
            'customization'  => [
                'Size' => $size,
                'With_Stand' => $with_stand ?: 'No',
                'needed_date' => $needed_date
            ],
            'design_notes'   => $notes,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $_FILES['design_file']['name'],
            'reference_tmp_path' => null,
            'reference_mime'     => null,
            'reference_name'     => null
        ];

        if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
            redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
        } else {
            redirect(BASE_URL . '/customer/cart.php');
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
            <span class="font-semibold text-gray-900">Sintraboard Standees</span>
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
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Sintraboard Standees</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_standees');
                $raw_avg = (float)($stats['avg_rating'] ?? 0);
                $review_count = (int)($stats['review_count'] ?? 0);
                $sold_count = (int)($stats['sold_count'] ?? 0);
                $sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
                
                $_s_name = 'PrintFlow Service';
                $_s_row = db_query("SELECT name FROM services WHERE customer_link LIKE '%order_standees%' LIMIT 1");
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

                <form method="POST" enctype="multipart/form-data" id="standeeForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="standeeUpdateOpt(this)"> <span><?php echo htmlspecialchars($b['branch_name']); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Dimensions -->
                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Dimensions *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <button type="button" class="shopee-opt-btn" data-width="2" data-height="5" onclick="selectDimension(2, 5, event)">2×5 FT</button>
                                <button type="button" class="shopee-opt-btn" data-width="2" data-height="6" onclick="selectDimension(2, 6, event)">2×6 FT</button>
                                <button type="button" class="shopee-opt-btn" data-width="2.5" data-height="6" onclick="selectDimension(2.5, 6, event)">2.5×6 FT</button>
                                <button type="button" class="shopee-opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                            </div>
                            <input type="hidden" name="width" id="width_hidden">
                            <input type="hidden" name="height" id="height_hidden">
                            <input type="hidden" name="unit" value="ft">
                            <!-- We still pass 'size' for backend compatibility if needed, or if it expects width/height -->
                            <input type="hidden" name="size" id="size_hidden">
                            
                                                    <div id="dim-others-inputs" class="shopee-form-row-flat" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);margin-top:1.5rem;padding-top:1.5rem;width:100%">
                            <div style="width:100%;max-width:440px">
                                <div style="display:flex;gap:8px;margin-bottom:4px">
                                    <div style="flex:1"><label class="dim-label">Width (ft)</label></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><label class="dim-label">Height (ft)</label></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1">
                                        <input type="number" step="0.01" id="custom_width" class="input-field" placeholder="e.g. 2" min="1" max="100">
                                        <div id="width-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                    <div style="flex:1">
                                        <input type="number" step="0.01" id="custom_height" class="input-field" placeholder="e.g. 5" min="1" max="100">
                                        <div id="height-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                </div>
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
                        <label class="shopee-form-label">Upload Design *</label>
                        <div class="shopee-form-field">
                            <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-date-souvenir">
                        <label class="shopee-form-label">Needed date *</label>
                        <div class="shopee-form-field">
                            <input type="date" name="needed_date" id="standee_needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" style="max-width: 200px;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-qty-souvenir">
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

function standeeQtyUp() { const i = document.getElementById('standee-qty'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function standeeQtyDown() { const i = document.getElementById('standee-qty'); i.value = Math.max(1, (parseInt(i.value) || 1) - 1); }
function standeeQtyClamp() { const i = document.getElementById('standee-qty'); let v = parseInt(i.value) || 1; i.value = Math.max(1, Math.min(999, v)); }

function standeeUpdateOpt(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(function(r) {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
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
    document.getElementById('width_hidden').value = w;
    document.getElementById('height_hidden').value = h;
    document.getElementById('size_hidden').value = w + 'x' + h + ' ft';
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
    document.getElementById('dim-others-inputs').style.display = 'flex';
    syncOthersDimensions();
}

function syncOthersDimensions() {
    let w = parseFloat(document.getElementById('custom_width').value) || 0;
    let h = parseFloat(document.getElementById('custom_height').value) || 0;
    
    document.getElementById('width_hidden').value = w;
    document.getElementById('height_hidden').value = h;
    document.getElementById('size_hidden').value = w + 'x' + h + ' ft';
}

document.getElementById('custom_width').addEventListener('input', syncOthersDimensions);
document.getElementById('custom_height').addEventListener('input', syncOthersDimensions);

document.getElementById('standeeForm').addEventListener('submit', function(e) {
    let hasError = false;
    let firstErr = null;

    // Reset errors
    document.getElementById('width-error').style.display = 'none';
    document.getElementById('height-error').style.display = 'none';

    const branch = this.querySelector('input[name="branch_id"]:checked');
    const stand = this.querySelector('input[name="with_stand"]:checked');
    const file = this.querySelector('input[name="design_file"]');
    const nd = document.getElementById('standee_needed_date');

    if (!branch) { hasError = true; if(!firstErr) firstErr = this.querySelector('.shopee-opt-group'); }
    
    if (dimensionMode === 'preset') {
        const active = this.querySelector('.shopee-opt-btn.active');
        if (!active && (this.querySelector('button[data-width]') || document.getElementById('dim-others-btn'))) {
            alert('Please select dimensions.');
            hasError = true;
            if(!firstErr) firstErr = document.getElementById('dim-others-btn').closest('.shopee-form-row');
        }
    } else {
        const w = parseFloat(document.getElementById('custom_width').value) || 0;
        const h = parseFloat(document.getElementById('custom_height').value) || 0;
        if (w <= 0 || h <= 0) {
            alert('Please enter dimensions.');
            hasError = true;
            if(!firstErr) firstErr = document.getElementById('custom_width');
        } else {
            if (w > 100) {
                document.getElementById('width-error').style.display = 'block';
                hasError = true;
                if (!firstErr) firstErr = document.getElementById('custom_width');
            }
            if (h > 100) {
                document.getElementById('height-error').style.display = 'block';
                hasError = true;
                if (!firstErr) firstErr = document.getElementById('custom_height');
            }
        }
    }

    if (!stand) { hasError = true; if(!firstErr) firstErr = this.querySelector('input[name="with_stand"]')?.closest('.shopee-form-row'); }
    if (!file || (!file.files || file.files.length === 0)) { 
        if (!file.placeholderColor) { // Check if we should ignore for edit mode (custom logic usually handles it but I'll stick to basic)
             hasError = true; if(!firstErr) firstErr = file; 
        }
    }
    if (!nd || !nd.value) { hasError = true; if(!firstErr) firstErr = nd; }

    const notes = document.getElementById('notes-textarea').value;
    if (notes.length > 500) {
        hasError = true; if(!firstErr) firstErr = document.getElementById('notes-textarea');
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

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#standeeForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<style>
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.dim-sep { height: 44px; display: flex; align-items: center; color: #cbd5e1; font-weight: bold; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
