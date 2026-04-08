<?php
/**
 * Glass & Wall Sticker Printing - Service Order Form
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';
$addr_api = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/api_address_public.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $unit = trim($_POST['unit'] ?? 'ft');
    $surface_type = trim($_POST['surface_type'] ?? '');
    $surface_other = trim($_POST['surface_type_other'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $installation = trim($_POST['installation'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $province = trim($_POST['install_province'] ?? '');
    $city = trim($_POST['install_city'] ?? '');
    $barangay = trim($_POST['install_barangay'] ?? '');
    $street = trim($_POST['install_street'] ?? '');

    $surface_display = ($surface_type === 'Others' && $surface_other) ? $surface_other : $surface_type;

    if (empty($width) || empty($height) || $quantity < 1 || empty($needed_date) || empty($surface_type) || empty($lamination) || empty($installation)) {
        $error = 'Please fill in all required fields marked with *.';
    } elseif ($installation === 'With installation' && (empty($province) || empty($city) || empty($barangay) || empty($street))) {
        $error = 'Please complete the installation address.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'glass_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $w = (float)$width;
                $h = (float)$height;
                $area = $w * $h;
                if ($unit === 'in') $area = $area / 144;
                
                $unit_price = 45.00;
                $base_price = $area * $unit_price * $quantity;
                $installation_fee = ($installation === 'With installation') ? (500 + ($area * 15)) : 0;

                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Glass & wall sticker printing',
                    'price' => $base_price + $installation_fee,
                    'quantity' => $quantity,
                    'category' => 'Glass & wall sticker printing',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'unit' => $unit,
                        'surface_type' => $surface_display,
                        'lamination' => $lamination,
                        'installation' => $installation,
                        'installation_fee' => $installation_fee,
                        'install_province' => $province,
                        'install_city' => $city,
                        'install_barangay' => $barangay,
                        'install_street' => $street,
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

$page_title = 'Order Glass & Wall Sticker - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");

// Fetch actual service image
$service_info = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_glass_stickers%' LIMIT 1");
$display_img = (!empty($service_info) && !empty($service_info[0]['hero_image'])) ? $service_info[0]['hero_image'] : '/printflow/public/assets/images/services/default.png';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') {
    $display_img = '/' . ltrim($display_img, '/');
}
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb title -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Glass & wall sticker</span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="shopee-card">
            <!-- Left: Product Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img); ?>" alt="Service Image" class="shopee-main-image" onerror="this.src='/printflow/public/assets/images/services/default.png'">
                </div>
                
                <div class="mt-6 p-4 bg-blue-50" style="border-radius: 0;">
                    <h4 class="text-xs font-bold text-blue-800 mb-2">Service note</h4>
                    <p class="text-xs text-blue-700 leading-relaxed">
                        Final installation fee will be calculated based on your exact location. For large stickers, our team will provide a layout proof before printing.
                    </p>
                </div>
            </div>
            
            <!-- Right: Order Details & Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Glass & wall sticker printing</h1>
                <?php
                $stats = service_order_get_page_stats('order_glass_stickers');
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
                            <span class="text-sm text-gray-500 ml-1 cursor-pointer hover:underline">(<?php echo number_format($review_count); ?> Reviews)</span>
                        <?php endif; ?>
                    </div>
                    <div class="h-4 w-px bg-gray-200"></div>
                    <div class="text-sm text-gray-500"><?php echo $sold_display; ?> Sold</div>
                </div>

                <div class="price-container mb-8">
                    <span class="text-3xl font-bold text-blue-600">₱45.00 - ₱1,500.00</span>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" id="glassForm" novalidate>
                    <?php echo csrf_field(); ?>

                <!-- Branch -->
                <div class="shopee-form-row shopee-form-row-flat">
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

                <!-- Dimensions -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Dimensions *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <button type="button" class="shopee-opt-btn" data-width="2" data-height="3" onclick="selectDimension(2, 3, event)">2×3 ft</button>
                            <button type="button" class="shopee-opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6 ft</button>
                            <button type="button" class="shopee-opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8 ft</button>
                            <button type="button" class="shopee-opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                        </div>
                        <input type="hidden" name="width" id="width_hidden">
                        <input type="hidden" name="height" id="height_hidden">
                        <input type="hidden" name="unit" value="ft">
                        <div id="dim-others-inputs" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);margin-top:1.5rem;padding-top:1.5rem;width:100%">
                            <div style="width:100%;max-width:440px">
                                <div style="display:flex;gap:8px;margin-bottom:4px">
                                    <div style="flex:1"><label class="dim-label">Width (ft)</label></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><label class="dim-label">Length (ft)</label></div>
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

                <!-- Surface Type -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Surface type *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Glass (window/door/storefront)" required style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Glass</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Wall (painted/concrete)" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Wall</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Frosted glass" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Frosted</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Mirror" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Mirror</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Acrylic/panel" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Acrylic</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Others" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Others</span></label>
                        </div>
                        
                        <div id="surface-other-wrap" class="pt-3" style="display: none; ">
                            <input type="text" name="surface_type_other" id="surface_type_other" class="input-field" placeholder="Specify surface type...">
                        </div>
                    </div>
                </div>

                <!-- Lamination -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Laminate *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="lamination" value="With lamination" required style="display:none;" onchange="updateOpt(this)"> <span>With lamination</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="lamination" value="Without lamination" style="display:none;" onchange="updateOpt(this)"> <span>Without lamination</span></label>
                        </div>
                    </div>
                </div>

                <!-- Installation -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Installation *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="installation" value="With installation" required style="display:none;" onchange="updateOpt(this); toggleInstallationAddress(true)"> <span>With installation</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="installation" value="Without installation" style="display:none;" onchange="updateOpt(this); toggleInstallationAddress(false)"> <span>Without installation</span></label>
                        </div>
                    </div>
                </div>

                <!-- Installation Address (Conditional) -->
                <div id="install-address-section" style="display: none; margin-bottom: 24px; max-width: 600px;">
                    <div class="bg-blue-50 border border-blue-100 text-blue-700 p-4 rounded-lg mb-6 text-sm flex items-start gap-3">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                        <span><strong>Fee adjustment:</strong> Final installation fee will be calculated based on your exact location.</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border border-dashed border-gray-200 p-6 rounded-xl bg-gray-50/50">
                        <div class="address-field">
                            <label class="dim-label">Province</label>
                            <select name="install_province" id="install_province" class="input-field">
                                <option value="">Select province</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label class="dim-label">City</label>
                            <select name="install_city" id="install_city" class="input-field" disabled>
                                <option value="">Select city</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label class="dim-label">Barangay</label>
                            <select name="install_barangay" id="install_barangay" class="input-field" disabled>
                                <option value="">Select barangay</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label class="dim-label">Street</label>
                            <input type="text" name="install_street" id="install_street" class="input-field" placeholder="Street/Purok/House No.">
                        </div>
                    </div>
                </div>

                <!-- Needed Date -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Needed date *</label>
                    <div class="shopee-form-field">
                        <input type="date" name="needed_date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                    </div>
                </div>

                <!-- Quantity -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Quantity *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-qty-control">
                            <button type="button" class="shopee-qty-btn" onclick="decreaseQty()">−</button>
                            <input type="number" id="quantity-input" name="quantity" class="shopee-qty-input" min="1" max="999" value="1" oninput="clampQty()">
                            <button type="button" class="shopee-qty-btn" onclick="increaseQty()">+</button>
                        </div>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Upload design *</label>
                    <div class="shopee-form-field">
                        <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                    </div>
                </div>

                <!-- Notes -->
                
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
.shopee-form-row-flat { margin-bottom: 1.5rem; display: flex; align-items: center; }
.dim-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block;  }
.shopee-qty-control { display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.1); width: fit-content; background: rgba(15, 23, 42, 0.6); }
.shopee-qty-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: #f1f5f9; cursor: pointer; font-size: 1.25rem; transition: background 0.2s; }
.shopee-qty-btn:hover { background: rgba(255,255,255,0.05); }
.shopee-qty-input { width: 50px; height: 32px; border: none; border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1); background: none; color: #f1f5f9; text-align: center; -moz-appearance: textfield; font-weight: 600; }
</style>

<script>
var ADDR_API = '<?php echo $addr_api; ?>';
var dimensionMode = 'preset';

function selectDimension(w, h, e) {
    dimensionMode = 'preset';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    document.getElementById('width_hidden').value = w;
    document.getElementById('height_hidden').value = h;
    document.getElementById('dim-others-inputs').style.display = 'none';
}

function selectDimensionOthers(e) {
    dimensionMode = 'others';
    document.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'block';
    syncOthersDimensions();
}

document.getElementById('custom_width').addEventListener('input', syncOthersDimensions);
document.getElementById('custom_height').addEventListener('input', syncOthersDimensions);

function syncOthersDimensions() {
    const w = parseFloat(document.getElementById('custom_width').value) || 0;
    const h = parseFloat(document.getElementById('custom_height').value) || 0;
    document.getElementById('width_hidden').value = w;
    document.getElementById('height_hidden').value = h;
}

function toggleSurfaceOther() {
    const r = document.querySelector('input[name="surface_type"]:checked');
    document.getElementById('surface-other-wrap').style.display = (r && r.value === 'Others') ? 'block' : 'none';
}

function toggleInstallationAddress(show) {
    const sec = document.getElementById('install-address-section');
    sec.style.display = show ? 'block' : 'none';
    if(show && document.getElementById('install_province').options.length <= 1) {
        loadProvinces();
    }
}

async function loadProvinces() {
    const p = document.getElementById('install_province');
    try {
        const r = await fetch(ADDR_API + '?address_action=provinces');
        const d = await r.json();
        if (d.success && d.data) {
            p.innerHTML = '<option value="">— Select province —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
        }
    } catch(e) { console.error(e); }
}

document.getElementById('install_province').addEventListener('change', async function() {
    const city = document.getElementById('install_city');
    city.innerHTML = '<option value="">— Select city / municipality —</option>';
    city.disabled = true;
    if (!this.value) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=cities&province_code=' + this.value);
        const d = await r.json();
        if (d.success && d.data) {
            city.innerHTML = '<option value="">— Select city / municipality —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
            city.disabled = false;
        }
    } catch(e) { console.error(e); }
});

document.getElementById('install_city').addEventListener('change', async function() {
    const b = document.getElementById('install_barangay');
    b.innerHTML = '<option value="">— Select barangay —</option>';
    b.disabled = true;
    if (!this.value) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=barangays&city_code=' + this.value);
        const d = await r.json();
        if (d.success && d.data) {
            b.innerHTML = '<option value="">— Select barangay —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
            b.disabled = false;
        }
    } catch(e) { console.error(e); }
});

function decreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function increaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function clampQty() { const i = document.getElementById('quantity-input'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }

function reflUpdateNotesCounter(textarea) {
    const count = textarea.value.length;
    document.querySelector('.notes-counter').textContent = `${count} / 500`;
}

function updateOpt(input) {
    const group = input.closest('.shopee-opt-group');
    if (group) {
        group.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
    }
    input.closest('.shopee-opt-btn')?.classList.add('active');
}

document.getElementById('glassForm').addEventListener('submit', function(e) {
    syncDimensionToHidden();
    
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
    } else {
        const w = parseFloat(document.getElementById('custom_width').value) || 0;
        const h = parseFloat(document.getElementById('custom_height').value) || 0;

        if (w <= 0 || h <= 0) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.showOrderValidationError) {
                if (w <= 0) window.showOrderValidationError(document.getElementById('custom_width'), 'Please enter width.');
                if (h <= 0) window.showOrderValidationError(document.getElementById('custom_height'), 'Please enter height.');
            } else {
                alert('Please enter custom dimensions.');
            }
            return;
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#glassForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
