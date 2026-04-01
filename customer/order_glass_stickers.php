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
    } elseif ($installation === 'With Installation' && (empty($province) || empty($city) || empty($barangay) || empty($street))) {
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
                $installation_fee = ($installation === 'With Installation') ? (500 + ($area * 15)) : 0;

                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Glass & Wall Sticker Printing',
                    'price' => $base_price + $installation_fee,
                    'quantity' => $quantity,
                    'category' => 'Glass & Wall Sticker Printing',
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
            <span class="font-semibold text-gray-900">Glass & Wall Sticker</span>
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
                </div>
            <!-- Right: Order Details & Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Glass & Wall Sticker Printing</h1>
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
                        <div class="shopee-opt-group">
                            <?php foreach($branches as $b): ?>
                                <label class="shopee-opt-btn" >
                                    <input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="updateOpt(this)">
                                    <span><?php echo htmlspecialchars($b['branch_name']); ?></span>
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
                            <button type="button" class="shopee-opt-btn" data-width="2" data-height="3" onclick="selectDimension(2, 3, event)">2×3 FT</button>
                            <button type="button" class="shopee-opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6 FT</button>
                            <button type="button" class="shopee-opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8 FT</button>
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
                                        <input type="number" step="0.01" id="custom_width" class="input-field" placeholder="0.0" min="1" max="100">
                                        <div id="width-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                    <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                    <div style="flex:1">
                                        <input type="number" step="0.01" id="custom_height" class="input-field" placeholder="0.0" min="1" max="100">
                                        <div id="height-error" style="display:none;color:#ef4444;font-size:0.75rem;font-weight:700;margin-top:4px">Maximum size is 100 ft.</div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:8px;margin-top:6px">
                                            <div style="flex:1"><span style="font-size:0.75rem;color:var(--lp-muted)">Standard width is up to 6 ft. Larger sizes will be printed in parts and joined.</span></div>
                                            <div style="width:32px"></div>
                                            <div style="flex:1"><span style="font-size:0.75rem;color:var(--lp-muted)">Length can be extended. Larger sizes will be printed in parts and joined.</span></div>
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
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Glass (Window/Door/Storefront)" required style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Glass</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Wall (Painted/Concrete)" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Wall</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Frosted Glass" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Frosted</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Mirror" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Mirror</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="surface_type" value="Acrylic/Panel" style="display:none;" onchange="updateOpt(this); toggleSurfaceOther();"> <span>Acrylic</span></label>
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
                            <label class="shopee-opt-btn" ><input type="radio" name="lamination" value="With Laminate" required style="display:none;" onchange="updateOpt(this)"> <span>With Laminate</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="lamination" value="Without Laminate" style="display:none;" onchange="updateOpt(this)"> <span>Without Laminate</span></label>
                        </div>
                    </div>
                </div>

                <!-- Installation -->
                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Installation *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <label class="shopee-opt-btn" ><input type="radio" name="installation" value="With Installation" required style="display:none;" onchange="updateOpt(this); toggleInstallationAddress(true)"> <span>With Installation</span></label>
                            <label class="shopee-opt-btn" ><input type="radio" name="installation" value="Without Installation" style="display:none;" onchange="updateOpt(this); toggleInstallationAddress(false)"> <span>No Installation</span></label>
                        </div>
                    </div>
                </div>

                <!-- Installation Address (Conditional) -->
                <div id="install-address-section" style="display: none; margin-bottom: 24px; max-width: 600px;">
                    <div class="bg-blue-50 border border-blue-100 text-blue-700 p-4 rounded-lg mb-6 text-sm flex items-start gap-3">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                        <span><strong>Fee Adjustment:</strong> Final installation fee will be calculated based on your exact location.</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border border-dashed border-gray-200 p-6 rounded-xl bg-gray-50/50">
                        <div class="address-field">
                            <label class="dim-label">Province</label>
                            <select name="install_province" id="install_province" class="input-field">
                                <option value="">Select Province</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label class="dim-label">City</label>
                            <select name="install_city" id="install_city" class="input-field" disabled>
                                <option value="">Select City</option>
                            </select>
                        </div>
                        <div class="address-field">
                            <label class="dim-label">Barangay</label>
                            <select name="install_barangay" id="install_barangay" class="input-field" disabled>
                                <option value="">Select Barangay</option>
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
                    <label class="shopee-form-label">Upload Design *</label>
                    <div class="shopee-form-field">
                        <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                    </div>
                </div>

                <!-- Notes -->
                <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                    <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                    <div class="shopee-form-field">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem; ">
                            <span id="notes-warn" style="font-size: 0.75rem; color: #ef4444; font-weight: 800; opacity: 0; transform: translateY(5px); transition: all 0.3s ease; pointer-events: none;">Maximum characters reached</span>
                            <span id="notes-counter" style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; transition: color 0.3s ease;">0 / 500</span>
                        </div>
                        <textarea name="notes" id="notes-textarea" rows="3" class="input-field" placeholder="Provide extra details for your order..." maxlength="500" oninput="updateNotesCounter(this)" style="min-height: 100px; max-height: 180px; resize: vertical;"></textarea>

                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="shopee-form-row pt-10">
                    <div style="width: 160px;" class="hidden md:block"></div>
                    <div class="flex gap-4 flex-1">
                        <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="flex: 1; display: flex; align-items: center; justify-content: center; font-weight: 700;">Back</a>
                        <button type="submit" name="action" value="add_to_cart" class="shopee-btn-outline" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700;" title="Add to Cart">
                            <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Add To Cart
                        </button>
                        <button type="submit" name="action" value="buy_now" class="shopee-btn-primary" style="flex: 1.5; font-weight: 800;">Buy Now</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<style>
/* Override specifics for this form */
.dim-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.dim-sep { height: 44px; display: flex; align-items: center; color: #cbd5e1; font-weight: bold; }
.dim-helper-text { font-size: 0.75rem; color: #94a3b8; font-weight: 500; margin-top: 6px; display: block; line-height: 1.5; }
.dim-warning-box { margin-top: 1.5rem; padding: 1.25rem; background: rgba(245, 158, 11, 0.08); border-left: 4px solid #f59e0b; border-radius: 0.75rem; color: #fbbf24; font-size: 0.85rem; line-height: 1.6; font-weight: 600; display: none; opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.dim-warning-box.show { display: block; opacity: 1; transform: translateY(0); }
.dim-estimation-box { margin-top: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.08); border-left: 4px solid #3b82f6; border-radius: 0.75rem; color: #93c5fd; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: none; opacity: 0; transform: translateY(10px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.dim-estimation-box.show { display: block; opacity: 1; transform: translateY(0); }
.notes-limit-reached { color: #ef4444 !important; }
#notes-warn.show { opacity: 1; transform: translateY(0); }

#glassForm .input-field {
    background-color: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}
#glassForm .input-field:focus {
    border-color: #3b82f6;
    background-color: rgba(15, 23, 42, 0.8);
}

/* ── Layout fix: force vertical stacking inside the form section ── */
.shopee-form-section {
    display: flex !important;
    flex-direction: column !important;
    min-width: 0;
    overflow: hidden;
}
#glassForm {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
}
#glassForm > .shopee-form-row {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    margin-bottom: 1.75rem;
}
@media (min-width: 768px) {
    #glassForm > .shopee-form-row.shopee-form-row-flat {
        flex-direction: row !important;
        align-items: flex-start !important;
    }
    #glassForm > .shopee-form-row.shopee-form-row-flat .shopee-form-label {
        width: 160px !important;
        flex-shrink: 0 !important;
        margin-top: 10px !important;
        margin-bottom: 0 !important;
    }
}
/* ──────────────────────────────────────────────────────────────── */

#install-address-section {
    background: transparent;
    padding: 0;
    margin-bottom: 32px;
}

#quantity-input::-webkit-outer-spin-button,
#quantity-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#quantity-input { -moz-appearance: textfield; appearance: textfield; }

@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; gap: 16px; }
}
</style>

<script>
function updateNotesCounter(el) {
    const count = el.value.length;
    const counter = document.getElementById('notes-counter');
    const warn = document.getElementById('notes-warn');
    counter.textContent = count + ' / 500';
    if(count >= 500) {
        counter.classList.add('notes-limit-reached');
        warn.classList.add('show');
    } else {
        counter.classList.remove('notes-limit-reached');
        warn.classList.remove('show');
    }
}

const ADDR_API = '<?php echo $addr_api; ?>';
let dimensionMode = 'preset';

function updateOpt(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(function(r) {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
}

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

    const warn = document.getElementById('dim-warning');

    if(w > 0 && h > 0) {
        // Show warning if large size detected (Requirement: Width > 6 or Length > 30)
        if(w > 6 || h > 30) {
            warn.classList.add('show');
        } else {
            warn.classList.remove('show');
        }
    } else {
        warn.classList.remove('show');
    }
}

document.getElementById('custom_width').addEventListener('input', syncOthersDimensions);
document.getElementById('custom_height').addEventListener('input', syncOthersDimensions);


function toggleSurfaceOther() {
    const r = document.querySelector('input[name="surface_type"]:checked');
    document.getElementById('surface-other-wrap').style.display = (r && r.value === 'Others') ? 'block' : 'none';
}

function toggleInstallationAddress(show) {
    const sec = document.getElementById('install-address-section');
    sec.style.display = show ? 'block' : 'none';
    const fields = sec.querySelectorAll('.input-field');
    fields.forEach(f => {
        if (f.id !== 'install_province') f.disabled = !show;
        if (show) f.setAttribute('required', '');
        else f.removeAttribute('required');
    });
    if (show && document.getElementById('install_province').options.length <= 1) {
        loadProvinces();
    }
}

async function loadProvinces() {
    const p = document.getElementById('install_province');
    try {
        const r = await fetch(ADDR_API + '?address_action=provinces');
        const d = await r.json();
        if (d.success && d.data) {
            p.innerHTML = '<option value="">— Select Province —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
        }
    } catch(e) { console.error(e); }
}

document.getElementById('install_province').addEventListener('change', async function() {
    const city = document.getElementById('install_city');
    city.innerHTML = '<option value="">— Select City / Municipality —</option>';
    city.disabled = true;
    if (!this.value) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=cities&province_code=' + this.value);
        const d = await r.json();
        if (d.success && d.data) {
            city.innerHTML = '<option value="">— Select City / Municipality —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
            city.disabled = false;
        }
    } catch(e) { console.error(e); }
});

document.getElementById('install_city').addEventListener('change', async function() {
    const b = document.getElementById('install_barangay');
    b.innerHTML = '<option value="">— Select Barangay —</option>';
    b.disabled = true;
    if (!this.value) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=barangays&city_code=' + this.value);
        const d = await r.json();
        if (d.success && d.data) {
            b.innerHTML = '<option value="">— Select Barangay —</option>' + d.data.map(i => `<option value="${i.code}">${i.name}</option>`).join('');
            b.disabled = false;
        }
    } catch(e) { console.error(e); }
});

function decreaseQty() { const i = document.getElementById('quantity-input'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function increaseQty() { const i = document.getElementById('quantity-input'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function clampQty() { const i = document.getElementById('quantity-input'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }

document.getElementById('glassForm').addEventListener('submit', function(e) {
    let hasError = false;
    let firstErrorField = null;

    // Reset errors
    document.getElementById('width-error').style.display = 'none';
    document.getElementById('height-error').style.display = 'none';
    document.getElementById('notes-warn').classList.remove('show');

    if (dimensionMode === 'preset') {
        const active = document.querySelector('.shopee-opt-btn.active');
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

    // Notes limit check (though prevented by maxlength, we check for safety/manual bypass)
    const notes = document.getElementById('notes-textarea').value;
    if (notes.length > 500) {
        document.getElementById('notes-warn').classList.add('show');
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
