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
    'Front center print' => 'Front Center Print.webp',
    'Back upper print' => 'Back Upper Print.webp',
    'Left/right chest print' => 'Left Right Chest Print.webp',
    'Bottom hem print' => 'Buttom Hem Print.webp',
    'Sleeve print' => 'Sleeve Print.webp',
    'Long sleeve arm print' => 'Long sleeve arm print.webp',
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

    $color_display = ($shirt_color === 'Others') ? $color_other : $shirt_color;
    $shirt_type_display = ($shirt_type === 'Others') ? $shirt_type_other : $shirt_type;
    $size_display = ($sizes === 'Others') ? $sizes_other : $sizes;

    $shop_provides = ($shirt_source === 'Shop will provide the shirt');
    $is_text_only = ($design_type === 'Text only');
    $is_logo_only = ($design_type === 'Logo only');
    $proceed = false;
    
    if ($branch_id <= 0) {
        $error = 'Please select a branch.';
    } elseif (empty($shirt_source)) {
        $error = 'Please select whether the shop or customer will provide the shirt.';
    } elseif (empty($design_type)) {
        $error = 'Please select a design type.';
    } elseif ($is_text_only && (empty($text_content) || empty($print_color))) {
        $error = 'Please provide text content and print color for text designs.';
    } elseif ($shop_provides) {
        if (empty($shirt_type_display) || empty($color_display) || empty($size_display) || empty($print_placement) || empty($lamination) || empty($needed_date) || $quantity < 1) {
            $error = 'Please fill in required fields: Shirt type, Color, Sizes, Print placement, Lamination, Needed date, and Quantity.';
        } else {
            $proceed = true;
        }
    } else {
        if (empty($shirt_type_display) || empty($color_display) || empty($print_placement) || empty($lamination) || empty($needed_date) || $quantity < 1) {
            $error = 'Please fill in required fields: Shirt type, Color, Print placement, Lamination, Needed date, and Quantity.';
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
                    'name' => 'T-shirt printing',
                    'price' => 350.00,
                    'quantity' => $quantity,
                    'category' => 'T-shirts',
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
            <span class="font-semibold text-gray-900">T-shirt printing</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div style="position: sticky; top: 100px;">
                    <div class="shopee-main-image-wrap" style="position: relative; top: 0;">
                        <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=T-Shirt'); ?>" alt="T-Shirt Printing" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=T-Shirt'">
                    </div>
                    <div class="mt-6 p-4 bg-blue-50" style="border-radius: 0;">
                        <h4 class="text-xs font-bold text-blue-800 mb-2">Service note</h4>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            Please choose whether the shirt will be provided by the shop or by the customer. It is recommended that customers provide their own shirt so they can ensure the correct size and preferred quality for their use.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">T-shirt printing</h1>
                
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
                            <div class="shopee-opt-group opt-grid-3">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" <?php echo ((string)($b['id']) === (string)($_POST['branch_id'] ?? '')) ? 'checked' : ''; ?> required style="display:none;" onchange="tshirtUpdateOpt(this)"> <span><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Source Selection -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-source">
                        <label class="shopee-form-label">Source *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-2">
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_source" value="Shop will provide the shirt" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Shop provides shirt</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_source" value="Customer will provide the shirt" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Customer provides shirt</span></label>
                            </div>
                            <div id="shop-provides-note" class="text-xs text-blue-500 mt-2 italic" style="display:none;">Note: Basic shirt cost will be added to the total.</div>
                        </div>
                    </div>

                    <!-- Design Type -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-design-type">
                        <label class="shopee-form-label">Design *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="design_type" value="Logo only" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Logo only</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="design_type" value="Text only" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>Text only</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Section -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-upload">
                        <label class="shopee-form-label" id="upload-label">Upload design *</label>
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
                            <div class="shopee-form-field">
                                <input type="text" name="text_content" id="text_content" class="input-field" placeholder="Content to be printed">
                            </div>
                        </div>
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Print color *</label>
                            <div class="shopee-form-field">
                                <input type="text" name="print_color" id="print_color" class="input-field" placeholder="e.g. White, Gold">
                            </div>
                        </div>
                    </div>

                    <!-- Shirt Type -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-shirt-type">
                        <label class="shopee-form-label">Shirt type *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-4">
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="Crew neck" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Crew neck</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="V-neck" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>V-neck</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="Polo" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Polo</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_type" value="Others" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Others</span></label>
                            </div>
                            <div id="shirt-type-other-wrap" style="display: none; margin-top: 1rem; ">
                                <input type="text" name="shirt_type_other" id="shirt_type_other" class="input-field" placeholder="Custom shirt type">
                            </div>
                        </div>
                    </div>

                    <!-- Shirt Color -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-shirt-color" style="display: none;">
                        <label class="shopee-form-label">Shirt color *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-5">
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="Black" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Black</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="White" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>White</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="Navy blue" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Navy blue</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="Maroon" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Maroon</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="shirt_color" value="Others" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Others</span></label>
                            </div>
                            <div id="color-other-wrap" style="display: none; margin-top: 1rem; ">
                                <input type="text" name="color_other" id="color_other" class="input-field" placeholder="Custom color">
                            </div>
                        </div>
                    </div>

                    <!-- Size Section -->
                    <div class="shopee-form-row shopee-form-row-flat" id="card-size" style="display: none;">
                        <label class="shopee-form-label">Shirt size *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-4">
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="XS (Extra small)" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>XS (Extra small)</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="S (Small)" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>S (Small)</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="M (Medium)" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>M (Medium)</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="L (Large)" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>L (Large)</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="XL (Extra large)" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>XL (Extra large)</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="2XL / XXL" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>2XL / XXL</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="sizes" value="3XL / XXXL" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>3XL / XXXL</span></label>
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
                            <label class="shopee-opt-btn"><input type="radio" name="lamination" value="With laminate" style="display:none;" required onchange="tshirtUpdateOpt(this)"> <span>With laminate</span></label>
                            <label class="shopee-opt-btn"><input type="radio" name="lamination" value="Without laminate" style="display:none;" onchange="tshirtUpdateOpt(this)"> <span>Without laminate</span></label>
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

                    <!-- Action Buttons -->
                    <div class="shopee-form-row pt-8">
                        <div style="width: 160px;" class="hidden md:block"></div>
                        <div class="flex gap-4 flex-1 justify-end">
                            <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="width: 90px; height: 2.25rem; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; white-space: nowrap;">Back</a>
                            <button type="button" onclick="submitTshirtOrder('add_to_cart')" class="shopee-btn-outline" style="width: 140px; height: 2.25rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700; font-size: 0.85rem; white-space: nowrap;" title="Add to Cart">
                                <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Add to cart
                            </button>
                            <button type="button" onclick="submitTshirtOrder('buy_now')" class="shopee-btn-primary" style="width: 160px; height: 2.25rem; font-size: 0.95rem; font-weight: 800; white-space: nowrap;">Buy now</button>
                        </div>
                    </div>
                </form>
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
.shopee-qty-input::-webkit-outer-spin-button, .shopee-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.placement-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; width: 100%; }
.placement-card { display: flex; flex-direction: column; align-items: center; cursor: pointer; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 0; padding: 0.5rem; transition: all 0.2s ease; overflow: hidden; }
.placement-card:hover { border-color: rgba(255,255,255,0.3); background: rgba(30, 41, 59, 0.8); }
.placement-card:has(input:checked) { border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.1); box-shadow: 0 0 0 1px var(--lp-accent); }
.placement-img-wrap { width: 100%; aspect-ratio: 1; border-radius: 0; overflow: hidden; background: rgba(0,0,0,0.2); position: relative; margin-bottom: 0.4rem; border: 1px dashed rgba(255,255,255,0.1); }
.placement-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.placement-label { font-size: 0.7rem; font-weight: 700; text-align: center; line-height: 1.2; color: #f8fafc; }
@media (max-width: 640px) {
    .placement-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
function tshirtUpdateOpt(input) {
    const group = input.closest('.shopee-opt-group') || input.closest('.placement-grid');
    if (group) {
        if (input.closest('.shopee-opt-btn')) {
            group.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
            input.closest('.shopee-opt-btn').classList.add('active');
        }
    }
    
    // Toggle conditional sections
    const name = input.name;
    if (name === 'shirt_source') {
        const shopProvides = input.value === 'Shop will provide the shirt';
        document.getElementById('shop-provides-note').style.display = shopProvides ? 'block' : 'none';
        document.getElementById('card-size').style.display = shopProvides ? 'flex' : 'none';
        document.getElementById('card-shirt-color').style.display = shopProvides ? 'flex' : 'none';
    }
    if (name === 'design_type') {
        const isLogo = input.value === 'Logo only';
        document.getElementById('text-design-section').style.display = isLogo ? 'none' : 'block';
        document.getElementById('upload-label').textContent = 'Upload design *';
    }
    if (name === 'shirt_type') {
        document.getElementById('shirt-type-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
    if (name === 'shirt_color') {
        document.getElementById('color-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
    if (name === 'sizes') {
        document.getElementById('sizes-other-wrap').style.display = input.value === 'Others' ? 'block' : 'none';
    }
}

function reflUpdateNotesCounter(textarea) {
    const count = textarea.value.length;
    document.querySelector('.notes-counter').textContent = `${count} / 500`;
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
    const placement = form.querySelector('input[name="print_placement"]:checked');
    const lamination = form.querySelector('input[name="lamination"]:checked');
    const nd = document.getElementById('needed_date');

    if (!branch) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-branch'); }
    if (!source) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-source'); }
    if (!design) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-design-type'); }
    if (!type) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-shirt-type'); }
    if (!placement) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-placement'); }
    if (!lamination) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-lamination'); }
    if (!nd.value) { hasError = true; if(!firstErr) firstErr = document.getElementById('card-date'); }

    if (hasError) {
        if (firstErr) { firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return;
    }

    const formData = new FormData(form);
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    // Use requestSubmit to trigger validation and submit listeners
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        // Fallback for older browsers
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.style.display = 'none';
        form.appendChild(submitBtn);
        submitBtn.click();
        form.removeChild(submitBtn);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
