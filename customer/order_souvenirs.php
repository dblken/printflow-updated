<?php
/**
 * Souvenirs - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Souvenirs - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_souvenirs%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
$souvenir_type_options = ['Mug', 'Keychain', 'Tote Bag', 'Pen', 'Tumbler', 'T-Shirt'];
?>
<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Souvenirs</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                        <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Souvenirs'); ?>" alt="Souvenirs" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Souvenirs'">
                    </div>
                </div>
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Souvenirs</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_souvenirs');
                $raw_avg = (float)($stats['avg_rating'] ?? 0);
                $review_count = (int)($stats['review_count'] ?? 0);
                $sold_count = (int)($stats['sold_count'] ?? 0);
                $sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
                
                $_s_name = 'PrintFlow Service';
                $_s_row = db_query("SELECT name FROM services WHERE customer_link LIKE '%order_souvenirs%' LIMIT 1");
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

                <form id="souvenirForm" method="POST" enctype="multipart/form-data" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_type" value="Souvenirs">

                    <div class="shopee-form-row shopee-form-row-flat" id="card-branch-souvenir">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="souvenirUpdateOpt(this)"> <span><?php echo htmlspecialchars($b['branch_name']); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="souvenir-type-section">
                        <label class="shopee-form-label">Type *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <?php foreach ($souvenir_type_options as $st): ?>
                                <label class="shopee-opt-btn"><input type="radio" name="souvenir_type" value="<?php echo htmlspecialchars($st); ?>" required style="display:none;" onchange="souvenirUpdateOpt(this)"> <span><?php echo htmlspecialchars($st); ?></span></label>
                                <?php endforeach; ?>
                                <label class="shopee-opt-btn"><input type="radio" name="souvenir_type" value="Others" style="display:none;" onchange="souvenirUpdateOpt(this)"> <span>Others</span></label>
                            </div>
                            <div id="souvenir-type-other-wrap" style="display: none; margin-top: 1rem; ">
                                <input type="text" name="souvenir_type_other" id="souvenir_type_other" class="input-field" placeholder="Specify other type">
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-custom-print-souvenir">
                        <label class="shopee-form-label">Custom print? *</label>
                        <div class="shopee-opt-group shopee-form-field">
                            <label class="shopee-opt-btn"><input type="radio" name="custom_print" value="No" required style="display:none;" onchange="toggleDesignUpload(); souvenirUpdateOpt(this)"> <span>No</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="custom_print" value="Yes" required style="display:none;" onchange="toggleDesignUpload(); souvenirUpdateOpt(this)"> <span>Yes (With Own Design)</span></label>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-upload-souvenir">
                        <label class="shopee-form-label">Upload Design <span id="upload-asterisk" class="text-red-500" style="display: none;">*</span></label>
                        <div class="shopee-form-field">
                            <div style="display:flex; justify-content:flex-end; margin-bottom: 0.25rem; ">
                                <span id="upload-hint" style="font-size: 0.7rem; color: var(--lp-muted); font-weight: 700;">(Optional)</span>
                            </div>
                            <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" style="max-width: 300px; padding: 0.5rem;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-lamination-souvenir">
                        <label class="shopee-form-label">Lamination *</label>
                        <div class="shopee-opt-group shopee-form-field">
                            <label class="shopee-opt-btn"><input type="radio" name="lamination" value="With Lamination" required style="display:none;" onchange="souvenirUpdateOpt(this)"> <span>With Lamination</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="lamination" value="Without Lamination" required style="display:none;" onchange="souvenirUpdateOpt(this)"> <span>Without Lamination</span></label>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-date-souvenir">
                        <label class="shopee-form-label">Needed date *</label>
                        <div class="shopee-form-field">
                            <input type="date" name="needed_date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" id="card-qty-souvenir">
                        <label class="shopee-form-label">Quantity *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-qty-control">
                                <button type="button" class="shopee-qty-btn" onclick="souvenirQtyDown()">−</button>
                                <input type="number" id="souvenir-qty" name="quantity" class="shopee-qty-input" min="1" max="999" required value="<?php echo max(1, (int)($_GET['qty'] ?? 1)); ?>" oninput="souvenirQtyClamp()">
                                <button type="button" class="shopee-qty-btn" onclick="souvenirQtyUp()">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                        <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                        <div class="shopee-form-field">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.25rem;">
                                <span id="notes-warn" style="font-size: 0.7rem; color: #ef4444; font-weight: 800; display:none;">Maximum characters reached</span>
                                <span id="notes-counter" style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;">0 / 500</span>
                            </div>
                            <textarea name="notes" id="notes-textarea" rows="3" class="input-field" placeholder="Preferred colors, text to print, placement..." maxlength="500" oninput="updateNotesCounter(this)" style="min-height: 100px; max-height: 180px; resize: none;"></textarea>
                        </div>
                    </div>


                    <div class="shopee-form-row pt-8">
                        <div style="width: 160px;" class="hidden md:block"></div>
                        <div class="flex gap-4 flex-1">
                            <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">Back</a>
                            <button type="button" onclick="submitSouvenirOrder('add_to_cart')" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700;">
                                <svg style="width: 1.1rem; height: 1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Add To Cart
                            </button>
                            <button type="button" onclick="submitSouvenirOrder('buy_now')" class="shopee-btn-primary" style="flex: 1.5; height: 3.5rem; font-size: 1.1rem; font-weight: 800;">Buy Now</button>
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
        warn.style.display = 'inline';
    } else {
        counter.style.color = '#94a3b8';
        warn.style.display = 'none';
    }
}

function souvenirUpdateOpt(input) {
    const name = input.name;
    document.querySelectorAll('input[name="' + name + '"]').forEach(function(r) {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) { wrap.classList.toggle('active', r.checked); }
    });
    if (input.name === 'souvenir_type') {
        toggleSouvenirTypeOther();
    }
}

function toggleSouvenirTypeOther() {
    var checked = document.querySelector('#souvenirForm input[name="souvenir_type"]:checked');
    var wrap = document.getElementById('souvenir-type-other-wrap');
    var inp = document.getElementById('souvenir_type_other');
    var show = checked && checked.value === 'Others';
    if (wrap) wrap.style.display = show ? 'block' : 'none';
}

function toggleDesignUpload() {
    const checked = document.querySelector('input[name="custom_print"]:checked');
    const isYes = checked && checked.value === 'Yes';
    const hint = document.getElementById('upload-hint');
    const fileInput = document.getElementById('design_file');
    const asterisk = document.getElementById('upload-asterisk');

    if (isYes) {
        hint.textContent = '(Required)';
        hint.style.color = '#ef4444';
        hint.style.fontWeight = '700';
        fileInput.required = true;
        if (asterisk) { asterisk.style.display = 'inline'; }
    } else {
        hint.textContent = '(Optional)';
        hint.style.color = '#9ca3af';
        hint.style.fontWeight = 'normal';
        fileInput.required = false;
        if (asterisk) { asterisk.style.display = 'none'; }
    }
}

function souvenirQtyClamp() {
    const input = document.getElementById('souvenir-qty');
    if (!input) return;
    let v = parseInt(input.value, 10);
    if (!v || v < 1) v = 1;
    if (v > 999) v = 999;
    input.value = v;
}

function souvenirQtyUp() {
    const input = document.getElementById('souvenir-qty');
    if (!input) return;
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.min(v + 1, 999);
}

function souvenirQtyDown() {
    const input = document.getElementById('souvenir-qty');
    if (!input) return;
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.max(v - 1, 1);
}

function submitSouvenirOrder(action) {
    const form = document.getElementById('souvenirForm');
    
    let hasError = false;
    let firstErrorField = null;

    // Basic validity checks
    const branch = form.querySelector('input[name="branch_id"]:checked');
    const type = form.querySelector('input[name="souvenir_type"]:checked');
    const custom = form.querySelector('input[name="custom_print"]:checked');
    const lam = form.querySelector('input[name="lamination"]:checked');
    const nd = document.getElementById('needed_date');
    const file = document.getElementById('design_file');

    if (!branch) { hasError = true; if(!firstErrorField) firstErrorField = document.getElementById('card-branch-souvenir'); }
    if (!type) { hasError = true; if(!firstErrorField) firstErrorField = document.getElementById('souvenir-type-section'); }
    if (type && type.value === 'Others' && !document.getElementById('souvenir_type_other').value.trim()) {
        hasError = true; if(!firstErrorField) firstErrorField = document.getElementById('souvenir_type_other');
    }
    if (!custom) { hasError = true; if(!firstErrorField) firstErrorField = document.getElementById('card-custom-print-souvenir'); }
    if (custom && custom.value === 'Yes' && (!file.files || file.files.length === 0)) {
        hasError = true; if(!firstErrorField) firstErrorField = file;
    }
    if (!lam) { hasError = true; if(!firstErrorField) firstErrorField = document.getElementById('card-lamination-souvenir'); }
    if (!nd.value) { hasError = true; if(!firstErrorField) firstErrorField = nd; }
    
    const notes = document.getElementById('notes-textarea').value;
    if (notes.length > 500) {
        hasError = true; if(!firstErrorField) firstErrorField = document.getElementById('notes-textarea');
    }

    if (hasError) {
        if (firstErrorField) {
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErrorField.focus();
        }
        return;
    }

    const buttons = form.querySelectorAll('button');
    buttons.forEach(btn => btn.disabled = true);

    const formData = new FormData(form);
    formData.append('action', action);

    fetch('api_add_to_cart_souvenirs.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = action === 'buy_now' ? 'order_review.php?item=' + data.item_key : 'cart.php';
        } else {
            alert(data.message);
            buttons.forEach(btn => btn.disabled = false);
        }
    })
    .catch(err => {
        alert('An error occurred. Please try again.');
        buttons.forEach(btn => btn.disabled = false);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#souvenirForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
    toggleSouvenirTypeOther();
    toggleDesignUpload();
});
</script>

<style>
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
