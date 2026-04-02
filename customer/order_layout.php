<?php
/**
 * Layout Design Service - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $layout_type = trim($_POST['layout_type'] ?? '');
    $rush = trim($_POST['rush'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($layout_type) || empty($needed_date)) {
        $error = 'Please select type of layout and provide needed date.';
    } else {
        $tmp_dir = service_order_temp_dir();
        $tmp_path = null;
        $mime = null;
        $design_name = null;

        if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['reference_file']);
            if ($valid['ok']) {
                $db_data = file_get_contents($_FILES['reference_file']['tmp_name']);
                $ext = pathinfo($_FILES['reference_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('layout_') . '.' . $ext;
                $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
                file_put_contents($tmp_path, $db_data);
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['reference_file']['tmp_name']);
                finfo_close($finfo);
                $design_name = $_FILES['reference_file']['name'];
            }
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $item_key = 'layout_' . time();
        
        $_SESSION['cart'][$item_key] = [
            'product_id'     => 0,
            'source_page'    => 'services',
            'branch_id'      => $branch_id,
            'name'           => 'Layout Design Service',
            'category'       => 'Graphic Design',
            'price'          => 0, // Determined after review
            'quantity'       => 1,
            'image'          => '🎨',
            'customization'  => [
                'Layout_Type' => $layout_type,
                'Rush_Order'  => $rush ?: 'No',
                'needed_date' => $needed_date
            ],
            'design_notes'   => $description,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $design_name,
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
$page_title = 'Layout Design Service - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_layout%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
$layout_types = ['Logo', 'Banner', 'Invitation', 'Poster', 'Others'];
?>
<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Layout design</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Layout+Design'); ?>" alt="Layout Design" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Layout+Design'">
                </div>
            </div>
            
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Layout & graphic design</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_layout');
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
                    <div class="text-sm text-gray-500"><?php echo $sold_display; ?> Designed</div>
                </div>

                <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="layoutForm" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <select name="branch_id" class="input-field" required>
                                <option value="" selected disabled>Select branch</option>
                                <?php foreach($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Type of layout *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <?php foreach ($layout_types as $lt): ?>
                                <label class="shopee-opt-btn"><input type="radio" name="layout_type" value="<?php echo htmlspecialchars($lt); ?>" required style="display:none;" onchange="layoutUpdateOpt(this)" <?php echo (($_POST['layout_type'] ?? '') === $lt) ? 'checked' : ''; ?>> <span><?php echo htmlspecialchars($lt); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Rush order? *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn"><input type="radio" name="rush" value="No" required style="display:none;" onchange="layoutUpdateOpt(this)" <?php echo (($_POST['rush'] ?? 'No') === 'No') ? 'checked' : ''; ?>> <span>No</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="rush" value="Yes" style="display:none;" onchange="layoutUpdateOpt(this)" <?php echo (($_POST['rush'] ?? '') === 'Yes') ? 'checked' : ''; ?>> <span>Yes (+ fee)</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Needed date *</label>
                        <div class="shopee-form-field">
                            <input type="date" name="needed_date" id="layout_needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" style="max-width: 200px;">
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                        <label class="shopee-form-label" style="padding-top: 0.75rem;">Description</label>
                        <div class="shopee-form-field">
                            <div style="display:flex; justify-content:flex-end; align-items:center; gap: 10px; margin-bottom: 0.5rem; width: 100%;">
                                <span class="notes-warn">Max 500 characters</span>
                                <span class="notes-counter">0 / 500</span>
                            </div>
                            <textarea id="notes_global" name="description" rows="3" class="input-field notes-textarea-global" 
                                placeholder="Any special requests or instructions (e.g., preferred layout, color adjustments, or specific details)." 
                                maxlength="500" 
                                oninput="reflUpdateNotesCounter(this)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Reference</label>
                        <div class="shopee-form-field">
                            <input type="file" name="reference_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" style="max-width: 300px; padding: 0.5rem;">
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
function layoutUpdateOpt(input) {
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

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#layoutForm .shopee-opt-btn').forEach(function(w) {
        if (w.querySelector('input:checked')) w.classList.add('active');
    });
});
</script>

<style>
.shopee-form-row-flat { margin-bottom: 1.5rem; display: flex; align-items: center; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
