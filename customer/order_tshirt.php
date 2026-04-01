<?php
/**
 * T-Shirt Printing - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

// Print placement options with image mapping
$placement_options = [
    'Front Center Print' => 'Front Center Print.webp',
    'Back Upper Print' => 'Back Upper Print.webp',
    'Left/Right Chest Print' => 'Left Right Chest Print.webp',
    'Bottom Hem Print' => 'Buttom Hem Print.webp',
    'Sleeve Print' => 'Sleeve Print.webp',
    'Long Sleeve Arm Print' => 'Long Sleeve Arm Print.webp',
];

$img_base = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/images/tshirt_replacement/';
$edit_item_key = trim((string)($_GET['edit_item'] ?? $_POST['edit_item'] ?? ''));
$is_edit_mode = false;
$edit_existing_item = null;

if ($edit_item_key !== '' && isset($_SESSION['cart'][$edit_item_key]) && is_array($_SESSION['cart'][$edit_item_key])) {
    $candidate = $_SESSION['cart'][$edit_item_key];
    $cat_name = strtolower(((string)($candidate['category'] ?? '')) . ' ' . ((string)($candidate['name'] ?? '')));
    if (strpos($cat_name, 't-shirt') !== false || strpos($cat_name, 'shirt') !== false) {
        $is_edit_mode = true;
        $edit_existing_item = $candidate;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $cust = (array)($candidate['customization'] ?? []);
            $_POST['branch_id'] = (string)($candidate['branch_id'] ?? '');
            $_POST['shirt_source'] = (string)($cust['shirt_source'] ?? '');
            $_POST['shirt_type'] = (string)($cust['shirt_type'] ?? '');
            $_POST['shirt_color'] = (string)($cust['shirt_color'] ?? '');
            $_POST['sizes'] = (string)($cust['size'] ?? '');
            $_POST['print_placement'] = (string)($cust['print_placement'] ?? '');
            $_POST['lamination'] = (string)($cust['lamination'] ?? '');
            $_POST['quantity'] = (string)($candidate['quantity'] ?? 1);
            $_POST['needed_date'] = (string)($cust['needed_date'] ?? '');
            $_POST['notes'] = (string)($cust['notes'] ?? '');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $shirt_source = trim($_POST['shirt_source'] ?? '');
    $shirt_type = trim($_POST['shirt_type'] ?? '');
    $shirt_type_other = trim($_POST['shirt_type_other'] ?? '');
    $shirt_color = trim($_POST['shirt_color'] ?? '');
    $color_other = trim($_POST['color_other'] ?? '');
    $sizes = trim($_POST['sizes'] ?? '');
    $sizes_other = trim($_POST['sizes_other'] ?? '');
    $print_placement = trim($_POST['print_placement'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $design_type = trim($_POST['design_type'] ?? '');
    $print_color = trim($_POST['print_color'] ?? '');
    $text_content = trim($_POST['text_content'] ?? '');
    $font_style = trim($_POST['font_style'] ?? '');
    $font_size = trim($_POST['font_size'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $color_display = ($shirt_color === 'Other') ? $color_other : $shirt_color;
    $shirt_type_display = ($shirt_type === 'Others') ? $shirt_type_other : $shirt_type;
    $size_display = ($sizes === 'Others') ? $sizes_other : $sizes;

    $shop_provides = ($shirt_source === 'Shop will provide the shirt');
    $is_text_only = ($design_type === 'Text Only');
    $is_logo_only = ($design_type === 'Logo Only');
    $proceed = false;
    
    if ($branch_id <= 0) {
        $error = 'Please select a branch.';
    } elseif (empty($shirt_source)) {
        $error = 'Please select whether the shop or customer will provide the shirt.';
    } elseif (empty($design_type)) {
        $error = 'Please select a design type.';
    } elseif ($is_text_only && (empty($text_content) || empty($print_color))) {
        $error = 'Please provide text content and print color for text designs.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif ($shop_provides) {
        if (empty($shirt_type_display) || empty($color_display) || empty($size_display) || empty($print_placement) || empty($lamination) || empty($needed_date) || $quantity < 1) {
            $error = 'Please fill in required fields: Shirt Type, Color, Sizes, Print Placement, Lamination, Needed Date, and Quantity.';
        } else {
            $proceed = true;
        }
    } else {
        if (empty($shirt_type_display) || empty($color_display) || empty($print_placement) || empty($lamination) || empty($needed_date) || $quantity < 1) {
            $error = 'Please fill in required fields: Shirt Type, Color, Print Placement, Lamination, Needed Date, and Quantity.';
        } else {
            $proceed = true;
        }
    }

    $has_new_upload = isset($_FILES['design_file']) && ($_FILES['design_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($proceed && $is_logo_only && !$has_new_upload && !$is_edit_mode) {
        $error = 'Please upload your design file for logo printing.';
        $proceed = false;
    }

    if ($proceed) {
        $tmp_dest = '';
        $original_name = '';
        $mime = '';
        if ($has_new_upload) {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
                $proceed = false;
            } else {
                $original_name = $_FILES['design_file']['name'];
                $mime = $valid['mime'];
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_name = uniqid('tmp_') . '.' . $ext;
                $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;
                move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest);
            }
        } elseif ($is_edit_mode && is_array($edit_existing_item)) {
            $tmp_dest = (string)($edit_existing_item['design_tmp_path'] ?? '');
            $original_name = (string)($edit_existing_item['design_name'] ?? '');
            $mime = (string)($edit_existing_item['design_mime'] ?? '');
        }

        if ($proceed) {
            $item_key = ($is_edit_mode && $edit_item_key !== '' && isset($_SESSION['cart'][$edit_item_key])) ? $edit_item_key : ('tshirt_' . time() . '_' . rand(100, 999));
            $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'T-Shirt Printing',
                    'price' => 350.00,
                    'quantity' => $quantity,
                    'category' => 'T-Shirts',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'shirt_source' => $shirt_source,
                        'design_type' => $design_type,
                        'print_color' => $print_color,
                        'text_content' => $text_content,
                        'font_style' => $font_style,
                        'font_size' => $font_size,
                        'shirt_type' => $shirt_type_display,
                        'shirt_color' => $color_display,
                        'size' => $size_display,
                        'print_placement' => $print_placement,
                        'lamination' => $lamination,
                        'quantity' => $quantity,
                        'needed_date' => $needed_date,
                        'notes' => $notes
                    ]
            ];
            
            if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                redirect("order_review.php?item=" . urlencode($item_key));
            } else {
                redirect("cart.php");
            }
        }
    }
}

$page_title = 'Order T-Shirt - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_tshirt%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">T-Shirt Printing</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=T-Shirt'); ?>" alt="T-Shirt Printing" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=T-Shirt'">
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded-lg">
                    <h4 class="text-xs font-bold text-blue-800 uppercase mb-2">Service Note</h4>
                    <p class="text-xs text-blue-700 leading-relaxed">
                        Please choose whether the shirt will be provided by the shop or by the customer. It is recommended that customers provide their own shirt so they can ensure the correct size and preferred quality for their use.
                    </p>
                </div>
            </div>

            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">T-Shirt Printing</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_tshirt');
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

                <form id="tshirtForm" method="POST" enctype="multipart/form-data" novalidate>
                    <?php echo csrf_field(); ?>
                    <?php if ($is_edit_mode): ?>
                        <input type="hidden" name="edit_item" value="<?php echo htmlspecialchars($edit_item_key); ?>">
                    <?php endif; ?>

                    <!-- Branch Selection -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-branch">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" <?php echo ((string)($b['id']) === (string)($_POST['branch_id'] ?? '')) ? 'checked' : ''; ?> required style="display:none;" onchange="tshirtUpdateOpt(this)"> <span><?php echo htmlspecialchars($b['branch_name']); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Source Selection -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-source">
                        <label class="shopee-form-label">Source *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_source" value="Shop will provide the shirt" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Shop Provides Shirt</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_source" value="Customer will provide the shirt" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Customer Provides Shirt</span></label>
                            </div>
                            <div id="shop-provides-note" class="text-xs text-blue-500 mt-2 italic" style="display:none;">Note: Basic shirt cost will be added to the total.</div>
                        </div>
                    </div>

                    <!-- Design Type -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-design-type">
                        <label class="shopee-form-label">Design *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="design_type" value="Logo Only" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Logo Only</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="design_type" value="Text Only" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Text Only</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Section -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-upload">
                        <label class="shopee-form-label" id="upload-label">Upload Design *</label>
                        <div class="shopee-form-field">
                            <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" style="max-width: 300px; padding: 0.5rem;">
                            <?php if ($is_edit_mode): ?>
                                <p class="text-xs text-blue-500 mt-1 italic">Keep empty to retain current design.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Text Design Section -->
                    <div id="text-design-section" style="display: none;">
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Text *</label>
                            <input type="text" name="text_content" id="text_content" class="input-field shopee-form-field" placeholder="Content to be printed">
                        </div>
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Print color *</label>
                            <input type="text" name="print_color" id="print_color" class="input-field shopee-form-field" placeholder="e.g. White, Gold">
                        </div>
                    </div>

                    <!-- Shirt Type -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-shirt-type">
                        <label class="shopee-form-label">Shirt type *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="Crew Neck" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Crew Neck</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="V-Neck" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>V-Neck</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="Polo" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Polo</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="Others" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Others</span></label>
                            </div>
                            <div id="shirt-type-other-wrap" style="display: none; margin-top: 1rem; ">
                                <input type="text" name="shirt_type_other" id="shirt_type_other" class="input-field" placeholder="Custom shirt type">
                            </div>
                        </div>
                    </div>

                    <!-- Shirt Color -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-shirt-color">
                        <label class="shopee-form-label">Shirt color *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="Black" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Black</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="White" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>White</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="Other" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Others</span></label>
                            </div>
                            <div id="color-other-wrap" style="display: none; margin-top: 1rem; ">
                                <input type="text" name="color_other" id="color_other" class="input-field" placeholder="Custom color">
                            </div>
                        </div>
                    </div>

                    <!-- Size Section -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-size" style="display: none;">
                        <label class="shopee-form-label">Dimensions *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="S" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>S</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="M" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>M</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="L" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>L</span></label>
                                <label class="shopee-opt-btn" id="tshirt-others-btn"><input type="radio" name="sizes" value="Others" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Others</span></label>
                            </div>
                            <div id="sizes-other-wrap" style="display: none; margin-top: 1rem; ">
                                <input type="text" name="sizes_other" id="sizes_other" class="input-field" placeholder="Custom size">
                            </div>
                        </div>
                    </div>

                    <!-- Placement Grid -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-placement">
                        <label class="shopee-form-label">Placement *</label>
                        <div class="shopee-form-field">
                            <div class="placement-grid">
                                <?php foreach ($placement_options as $name => $img_file): 
                                    $img_url = $img_base . rawurlencode($img_file);
                                ?>
                                <label class="placement-card">
                                    <input type="radio" name="print_placement" value="<?php echo htmlspecialchars($name); ?>" style="display:none;" required onchange="tshirtUpdateOpt(this)">
                                    <div class="placement-img-wrap">
                                        <img src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($name); ?>" onerror="this.src='https://placehold.co/100x100?text=Placement'">
                                    </div>
                                    <span class="placement-label"><?php echo htmlspecialchars($name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Lamination -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-lamination">
                        <label class="shopee-form-label">Laminate *</label>
                        <div class="shopee-opt-group shopee-form-field">
                            <label class="shopee-opt-btn"><input type="radio" name="lamination" value="With Laminate" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>With Laminate</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="lamination" value="Without Laminate" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Without Laminate</span></label>
                        </div>
                    </div>

                    <!-- Needed Date -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-date">
                        <label class="shopee-form-label">Needed date *</label>
                        <div class="shopee-form-field">
                            <input type="date" name="needed_date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-qty">
                        <label class="shopee-form-label">Quantity *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-qty-control">
                                <button type="button" onclick="tshirtDecreaseQty()" class="shopee-qty-btn">−</button>
                                <input type="number" id="quantity-input" name="quantity" class="shopee-qty-input" min="1" max="999" value="1">
                                <button type="button" onclick="tshirtIncreaseQty()" class="shopee-qty-btn">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                        <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                        <div class="shopee-form-field">
                            <div style="display:flex; justify-content:flex-end; margin-bottom: 0.25rem; ">
                                <span id="notes-counter" style="font-size: 0.7rem; color: var(--lp-muted); font-weight: 700;">0 / 500</span>
                            </div>
                            <textarea name="notes" id="notes-textarea" rows="3" class="input-field" placeholder="Any special instructions..." maxlength="500" oninput="updateNotesCounter(this)"></textarea>
                            <div id="notes-warn" class="text-xs font-bold mt-1" style="display:none; color: #ef4444;">Maximum characters reached.</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="shopee-form-row pt-8">
                        <div style="width: 160px;" class="hidden md:block"></div>
                        <div class="flex gap-4 flex-1">
                            <a href="services.php" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">Back</a>
                            <button type="button" onclick="submitTshirtOrder('add_to_cart')" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; border-color: var(--lp-accent); color: var(--lp-accent); font-weight: 700;">
                                <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Add To Cart
                            </button>
                            <button type="button" onclick="submitTshirtOrder('buy_now')" class="shopee-btn-primary" style="flex: 1.5; height: 3.5rem; font-size: 1.1rem; font-weight: 800;">Buy Now</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.dim-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.need-qty-row { display: flex; gap: 16px; width: 100%; }
.placement-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.placement-card { display: flex; flex-direction: column; align-items: center; cursor: pointer; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem; background: #fff; transition: all 0.2s ease; }
.placement-card:hover { border-color: #0a2530; background: #f8fafc; }
.placement-card:has(input:checked) { border-color: #0a2530; background: #f0f9ff; box-shadow: 0 0 0 1px #0a2530; }
.placement-img-wrap { width: 100%; aspect-ratio: 1; border-radius: 6px; overflow: hidden; background: #f3f4f6; position: relative; }
.placement-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.placement-label { font-size: 0.65rem; font-weight: 600; text-align: center; margin-top: 0.4rem; line-height: 1.2; color: #475569; text-transform: uppercase; }
@media (max-width: 640px) {
    .need-qty-row { flex-direction: column; }
    .placement-grid { grid-template-columns: repeat(2, 1fr); }
}
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

function tshirtUpdateOpt(input) {
    const name = input.name;
    document.querySelectorAll('input[name="' + name + '"]').forEach(r => {
        r.closest('.shopee-opt-btn')?.classList.toggle('active', r.checked);
    });
    
    // Toggle conditional sections
    if (name === 'shirt_source') {
        const shopProvides = input.value === 'Shop will provide the shirt';
        document.getElementById('shop-provides-note').style.display = shopProvides ? 'block' : 'none';
        document.getElementById('card-size').style.display = shopProvides ? 'flex' : 'none';
    }
    if (name === 'design_type') {
        const isLogo = input.value === 'Logo Only';
        document.getElementById('text-design-section').style.display = isLogo ? 'none' : 'block';
        document.getElementById('upload-label').textContent = isLogo ? 'Upload design *' : 'Upload design (Optional)';
    }
    if (name === 'shirt_type') {
        document.getElementById('shirt-type-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
    if (name === 'shirt_color') {
        document.getElementById('color-other-wrap').style.display = input.value === 'Other' ? 'block' : 'none';
    }
    if (name === 'sizes') {
        document.getElementById('sizes-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
}

function tshirtIncreaseQty() {
    const i = document.getElementById('quantity-input');
    i.value = Math.min(999, (parseInt(i.value) || 1) + 1);
}
function tshirtDecreaseQty() {
    const i = document.getElementById('quantity-input');
    const v = parseInt(i.value) || 1;
    if (v > 1) i.value = v - 1;
}

function submitTshirtOrder(action) {
    const form = document.getElementById('tshirtForm');
    let hasError = false;
    let firstErr = null;

    const branch = form.querySelector('input[name="branch_id"]:checked');
    const source = form.querySelector('input[name="shirt_source"]:checked');
    const design = form.querySelector('input[name="design_type"]:checked');
    const type = form.querySelector('input[name="shirt_type"]:checked');
    const color = form.querySelector('input[name="shirt_color"]:checked');
    const placement = form.querySelector('input[name="print_placement"]:checked');
    const lamination = form.querySelector('input[name="lamination"]:checked');
    const nd = document.getElementById('needed_date');

    if (!branch) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-branch'); }
    if (!source) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-source'); }
    if (!design) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-design-type'); }
    if (!type) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-shirt-type'); }
    if (!color) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-shirt-color'); }
    if (!placement) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-placement'); }
    if (!lamination) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-lamination'); }
    if (!nd.value) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-needed-date'); }

    if (hasError) {
        if (firstErr) { firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return;
    }

    const formData = new FormData(form);
    formData.append('action', action);

    // Normally fetch here, but ensuring this script is complete
    form.submit(); // Simple submit for now, or match fetch pattern from others
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
