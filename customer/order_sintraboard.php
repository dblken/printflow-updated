<?php
/**
 * Sintraboard & Standees - Service Order Form
 * PrintFlow - Clean flow: Product Type → Dimensions → Options → Upload → Needed Date + Quantity → Notes
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';

// Common Sintraboard dimension presets (inches)
$dimension_presets = [
    '12 × 18' => ['w' => 12, 'h' => 18],
    '18 × 24' => ['w' => 18, 'h' => 24],
    '24 × 36' => ['w' => 24, 'h' => 36],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id_raw = trim($_POST['branch_id'] ?? '');
    $branch_id = $branch_id_raw === '' ? 0 : (int)$branch_id_raw;
    $sintra_type = trim($_POST['sintra_type'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $thickness = trim($_POST['thickness'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $quantity = max(1, min(999, $quantity));

    $valid_types = ['Flat type', '2D type (with frame)', 'Standee (back stand support)'];
    if ($branch_id < 1) {
        $error = 'Please select a branch.';
    } elseif (empty($sintra_type) || !in_array($sintra_type, $valid_types, true)) {
        $error = 'Please select a sintraboard type.';
    } elseif ($dimensions === '') {
        $error = 'Please specify dimensions.';
    } elseif ($unit === '' || !in_array($unit, ['in', 'ft'], true)) {
        $error = 'Please select a unit.';
    } elseif ($lamination === '' || !in_array($lamination, ['With lamination', 'Without lamination'], true)) {
        $error = 'Please select lamination.';
    } elseif ($layout === '' || !in_array($layout, ['With layout', 'Without layout'], true)) {
        $error = 'Please select layout.';
    } elseif ($thickness === '' || !in_array($thickness, ['3mm', '5mm', '10mm'], true)) {
        $error = 'Please select thickness.';
    } elseif ($needed_date === '') {
        $error = 'Please select a needed date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $needed_date)) {
        $error = 'Please enter a valid needed date.';
    } elseif (strtotime($needed_date . ' 00:00:00') < strtotime('today')) {
        $error = 'Needed date cannot be in the past.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $width = '';
            $height = '';
            if (preg_match('/^(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)$/i', $dimensions, $m)) {
                $width = $m[1];
                $height = $m[2];
            } else {
                $parts = preg_split('/[\s,]+/', $dimensions, 2);
                if (count($parts) >= 2) {
                    $width = trim($parts[0]);
                    $height = trim($parts[1]);
                }
            }

            if ($width === '' || $height === '') {
                $error = 'Please enter valid dimensions (e.g. 12 x 18).';
            } else {
                $tmp_dir = service_order_temp_dir();
                $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('sintra_') . '.' . $ext;
                $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
                file_put_contents($tmp_path, file_get_contents($_FILES['design_file']['tmp_name']));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
                finfo_close($finfo);

                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                $product_id = ($sintra_type === 'Flat type') ? 51 : 54;
                $product_name = $sintra_type;
                $sintra_price = ($sintra_type === 'Flat type') ? 150.00 : 800.00;

                $item_key = $product_id . '_' . time();
                $_SESSION['cart'][$item_key] = [
                    'product_id'       => $product_id,
                    'source_page'      => 'services',
                    'branch_id'        => $branch_id,
                    'name'             => $product_name,
                    'category'         => 'Sintraboard standees',
                    'price'            => $sintra_price,
                    'quantity'         => $quantity,
                    'image'            => '📦',
                    'customization'    => [
                        'Sintra_Type' => $sintra_type,
                        'Dimensions'  => $dimensions,
                        'Unit'        => $unit,
                        'Width'       => $width,
                        'Height'      => $height,
                        'Thickness'   => $thickness,
                        'Lamination'  => $lamination,
                        'Layout'      => $layout,
                        'needed_date' => $needed_date,
                        'notes'       => $notes,
                    ],
                    'design_notes'     => $notes,
                    'design_tmp_path'  => $tmp_path,
                    'design_mime'     => $mime,
                    'design_name'     => $_FILES['design_file']['name'],
                    'reference_tmp_path' => null,
                    'reference_mime'  => null,
                    'reference_name'  => null,
                ];

                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            }
        }
    }
}

$page_title = 'Order Sintraboard Standees - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_sintraboard%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }
$qty_default = (int)($_GET['qty'] ?? 1);
$qty_default = max(1, min(999, $qty_default));
$sel_type = $_POST['sintra_type'] ?? $_GET['sintra_type'] ?? '';
$sel_unit = $_POST['unit'] ?? '';
$sel_lamination = $_POST['lamination'] ?? '';
$sel_layout = $_POST['layout'] ?? '';
$sel_thickness = $_POST['thickness'] ?? '';
$other_w = '';
$other_h = '';
if (!empty($_POST['dimensions']) && preg_match('/^(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)$/i', trim((string)($_POST['dimensions'] ?? '')), $sintra_dim_m)) {
    $other_w = $sintra_dim_m[1];
    $other_h = $sintra_dim_m[2];
}
?>
<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Sintraboard & standees</span>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                    <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Sintraboard'); ?>" alt="Sintraboard & Standees" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Sintraboard'">
                </div>
            </div>
            
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Sintraboard & standees</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_sintraboard');
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

                <form method="POST" enctype="multipart/form-data" id="sintraForm" novalidate>
                    <?php echo csrf_field(); ?>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Branch *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group opt-grid-3">
                            <?php foreach ($branches as $b): ?>
                                <label class="shopee-opt-btn" >
                                    <input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                                    <span><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Type *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group">
                            <?php
                            $types = [
                                'Flat type' => 'Flat type',
                                '2D type (with frame)' => '2D type (with frame)',
                                'Standee (back stand support)' => 'Standee (back stand support)'
                            ];
                            $type_info = [
                                'Flat type' => 'A standard flat sintraboard panel ideal for wall signs, labels, and simple display boards.',
                                '2D type (with frame)' => 'A framed 2D board with cleaner edge presentation, great for more premium display use.',
                                'Standee (back stand support)' => 'A freestanding board with rear support so it can stand on floors or counters without wall mounting.',
                            ];
                            foreach ($types as $val => $label):
                                $checked = ($sel_type === $val || (strtolower($sel_type) === strtolower($val))) ? 'checked' : '';
                            ?>
                            <label class="shopee-opt-btn sintra-type-row" data-info-title="<?php echo htmlspecialchars($label); ?>" data-info-body="<?php echo htmlspecialchars($type_info[$val] ?? ''); ?>" style="min-width: 250px;">
                                <input type="radio" name="sintra_type" value="<?php echo htmlspecialchars($val); ?>" style="display:none;" <?php echo $checked; ?> onchange="sintraUpdateOptionVisuals(this)">
                                <span><?php echo htmlspecialchars($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Dimensions *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-opt-group mb-3">
                            <?php foreach ($dimension_presets as $label => $d): ?>
                            <label class="shopee-opt-btn sintra-dim-btn" data-w="<?php echo $d['w']; ?>" data-h="<?php echo $d['h']; ?>">
                                <input type="radio" name="dimension_preset" value="<?php echo htmlspecialchars($label); ?>" style="display:none;" onchange="sintraSelectDimension('<?php echo htmlspecialchars($label); ?>', <?php echo $d['w']; ?>, <?php echo $d['h']; ?>)">
                                <span><?php echo htmlspecialchars($label); ?> in</span>
                            </label>
                            <?php endforeach; ?>
                            <label class="shopee-opt-btn sintra-dim-btn" data-others="1">
                                <input type="radio" name="dimension_preset" value="Others" style="display:none;" onchange="sintraSelectDimensionOthers()">
                                <span>Others</span>
                            </label>
                        </div>
                        <input type="hidden" name="dimensions" id="sintra_dimensions" value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>">
                        
                        <div id="sintraDimOthersWrap" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);padding-top:1rem;margin-top:1rem">
                            <div style="width:100%;max-width:440px">
                                <div style="display:flex;gap:8px;margin-bottom:4px">
                                    <div style="flex:1"><label class="dim-label">Width <span class="sintra-dim-others-unit"></span></label></div>
                                    <div style="width:32px"></div>
                                    <div style="flex:1"><label class="dim-label">Height <span class="sintra-dim-others-unit"></span></label></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                        <div style="flex:1">
                                            <input type="number" id="sintra_dim_other_w" class="input-field" placeholder="0" value="<?php echo htmlspecialchars($other_w); ?>" oninput="sintraSyncDimOthers()" data-dimension min="1" max="100">
                                        </div>
                                        <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                        <div style="flex:1">
                                            <input type="number" id="sintra_dim_other_h" class="input-field" placeholder="0" value="<?php echo htmlspecialchars($other_h); ?>" oninput="sintraSyncDimOthers()" data-dimension min="1" max="100">
                                        </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="dim-label mb-2">Select unit</label>
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn">
                                    <input type="radio" name="unit" value="in" required <?php echo $sel_unit === 'in' ? 'checked' : ''; ?> style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                                    <span>Inches (in)</span>
                                </label>
                                <label class="shopee-opt-btn">
                                    <input type="radio" name="unit" value="ft" <?php echo $sel_unit === 'ft' ? 'checked' : ''; ?> style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                                    <span>Feet (ft)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Laminate *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn">
                            <input type="radio" name="lamination" value="With lamination" required <?php echo ($sel_lamination === 'With lamination' || $sel_lamination === 'With Lamination') ? 'checked' : ''; ?> style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                            <span>With lamination</span>
                        </label>
                        <label class="shopee-opt-btn">
                            <input type="radio" name="lamination" value="Without lamination" <?php echo ($sel_lamination === 'Without lamination' || $sel_lamination === 'Without Lamination') ? 'checked' : ''; ?> style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                            <span>Without lamination</span>
                        </label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Layout *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <label class="shopee-opt-btn">
                            <input type="radio" name="layout" value="With layout" required <?php echo ($sel_layout === 'With layout' || $sel_layout === 'With Layout') ? 'checked' : ''; ?> style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                            <span>With layout</span>
                        </label>
                        <label class="shopee-opt-btn">
                            <input type="radio" name="layout" value="Without layout" <?php echo ($sel_layout === 'Without layout' || $sel_layout === 'Without Layout') ? 'checked' : ''; ?> style="display:none;" onchange="sintraUpdateOptionVisuals(this)">
                            <span>Without layout</span>
                        </label>
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat">
                    <label class="shopee-form-label">Thickness *</label>
                    <div class="shopee-opt-group shopee-form-field">
                        <?php foreach (['3mm', '5mm', '10mm'] as $ti => $th): ?>
                        <label class="shopee-opt-btn">
                            <input type="radio" name="thickness" value="<?php echo htmlspecialchars($th); ?>" style="display:none;" <?php echo $ti === 0 ? 'required' : ''; ?> <?php echo $sel_thickness === $th ? 'checked' : ''; ?> onchange="sintraUpdateOptionVisuals(this)">
                            <span><?php echo htmlspecialchars($th); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Upload design *</label>
                        <div class="shopee-form-field">
                            <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="max-width: 300px; padding: 0.5rem;">
                        </div>
                    </div>

                <div class="shopee-form-row shopee-form-row-flat" id="sintra-date-card">
                    <label class="shopee-form-label">Needed date *</label>
                    <div class="shopee-form-field">
                        <input type="date" name="needed_date" id="sintra_needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                    </div>
                </div>

                <div class="shopee-form-row shopee-form-row-flat" id="sintra-qty-card">
                    <label class="shopee-form-label">Quantity *</label>
                    <div class="shopee-form-field">
                        <div class="shopee-qty-control">
                            <button type="button" onclick="sintraQtyDown()" class="shopee-qty-btn">−</button>
                            <input type="number" name="quantity" id="sintra_quantity" class="shopee-qty-input" min="1" max="999" value="<?php echo (int)($_POST['quantity'] ?? $qty_default); ?>" oninput="sintraQtyClamp()">
                            <button type="button" onclick="sintraQtyUp()" class="shopee-qty-btn">+</button>
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


<div id="sintraTypeInfoModal" class="sintra-info-modal" style="display:none;">
    <div class="sintra-info-modal-backdrop" onclick="sintraCloseInfoModal()"></div>
    <div class="sintra-info-modal-card" role="dialog" aria-modal="true" aria-labelledby="sintraInfoTitle">
        <button type="button" class="sintra-info-modal-close" onclick="sintraCloseInfoModal()" aria-label="Close">×</button>
        <h3 id="sintraInfoTitle" class="sintra-info-modal-title"></h3>
        <p id="sintraInfoBody" class="sintra-info-modal-body"></p>
    </div>
</div>

<script>
var sintraDimensionMode = 'preset';

function sintraOpenInfoModal(title, body) {
    var modal = document.getElementById('sintraTypeInfoModal');
    var titleEl = document.getElementById('sintraInfoTitle');
    var bodyEl = document.getElementById('sintraInfoBody');
    if (!modal || !titleEl || !bodyEl) return;
    titleEl.textContent = title || 'Sintraboard type';
    bodyEl.textContent = body || '';
    modal.style.display = 'flex';
}

function sintraCloseInfoModal() {
    var modal = document.getElementById('sintraTypeInfoModal');
    if (modal) modal.style.display = 'none';
}

function sintraUpdateOthersUnitLabels() {
    var u = document.querySelector('#sintraForm input[name="unit"]:checked');
    var t = '';
    if (u && u.value === 'ft') t = '(FT)';
    else if (u && u.value === 'in') t = '(IN)';
    document.querySelectorAll('#sintraDimOthersWrap .sintra-dim-others-unit').forEach(function(el) {
        el.textContent = t;
    });
}

function sintraUpdateOptionVisuals(input) {
    const group = input.closest('.shopee-opt-group');
    if (group) {
        group.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
    }
    input.closest('.shopee-opt-btn')?.classList.add('active');
    
    if (input && input.name === 'unit') sintraUpdateOthersUnitLabels();
}

function sintraSelectDimension(label, w, h) {
    sintraDimensionMode = 'preset';
    document.getElementById('sintra_dimensions').value = w + ' x ' + h;
    document.getElementById('sintraDimOthersWrap').style.display = 'none';
    var ow = document.getElementById('sintra_dim_other_w');
    var oh = document.getElementById('sintra_dim_other_h');
    if (ow) ow.value = '';
    if (oh) oh.value = '';
    document.querySelectorAll('.sintra-dim-btn').forEach(function(b) { b.classList.remove('active'); });
    var btn = document.querySelector('.sintra-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
}

function sintraSelectDimensionOthers() {
    sintraDimensionMode = 'others';
    document.getElementById('sintraDimOthersWrap').style.display = 'block';
    document.getElementById('sintra_dimensions').value = '';
    document.querySelectorAll('.sintra-dim-btn').forEach(function(b) { b.classList.remove('active'); });
    var others = document.querySelector('.sintra-dim-btn[data-others="1"]');
    if (others) others.classList.add('active');
}

function sintraSyncDimOthers() {
    var w = document.getElementById('sintra_dim_other_w');
    var h = document.getElementById('sintra_dim_other_h');
    var wv = w ? w.value.trim() : '';
    var hv = h ? h.value.trim() : '';
    document.getElementById('sintra_dimensions').value = (wv && hv) ? (wv + ' x ' + hv) : '';
}

function sintraQtyUp() {
    var q = document.getElementById('sintra_quantity');
    q.value = Math.min(999, (parseInt(q.value, 10) || 1) + 1);
}

function sintraQtyDown() {
    var q = document.getElementById('sintra_quantity');
    q.value = Math.max(1, (parseInt(q.value, 10) || 1) - 1);
}

function sintraQtyClamp() {
    var q = document.getElementById('sintra_quantity');
    var v = parseInt(q.value, 10) || 1;
    q.value = Math.max(1, Math.min(999, v));
}

function reflUpdateNotesCounter(textarea) {
    const count = textarea.value.length;
    document.querySelector('.notes-counter').textContent = `${count} / 500`;
}

document.getElementById('sintraForm').addEventListener('submit', function(e) {
    let hasError = false;
    let firstErr = null;

    const branch = this.querySelector('input[name="branch_id"]:checked');
    const type = this.querySelector('input[name="sintra_type"]:checked');
    const dim = document.getElementById('sintra_dimensions').value.trim();
    const lam = this.querySelector('input[name="lamination"]:checked');
    const lay = this.querySelector('input[name="layout"]:checked');
    const thick = this.querySelector('input[name="thickness"]:checked');
    const file = document.getElementById('design_file');
    const nd = document.getElementById('sintra_needed_date');

    if (!branch) { hasError = true; if (!firstErr) firstErr = this.querySelector('input[name="branch_id"]')?.closest('.shopee-form-row'); }
    if (!type) { hasError = true; if (!firstErr) firstErr = this.querySelector('input[name="sintra_type"]')?.closest('.shopee-form-row'); }
    
    if (sintraDimensionMode === 'preset') {
        const active = document.querySelector('input[name="dimension_preset"]:checked');
        if (!active || active.value === 'Others') {
             if (!active || (active.value === 'Others' && !document.getElementById('sintra_dimensions').value)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (window.showOrderValidationError) {
                    window.showOrderValidationError(document.getElementById('sintra_dimensions').closest('.shopee-form-field').querySelector('.shopee-opt-group'), 'Please select a dimension.');
                } else {
                    alert('Please select a dimension.');
                }
                return;
             }
        }
    } else {
        const w = parseInt(document.getElementById('sintra_dim_other_w').value, 10) || 0;
        const h = parseInt(document.getElementById('sintra_dim_other_h').value, 10) || 0;

        if (w <= 0 || h <= 0 || w > 100 || h > 100) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.showOrderValidationError) {
                if (w <= 0) window.showOrderValidationError(document.getElementById('sintra_dim_other_w'), 'Please enter width.');
                else if (w > 100) window.showOrderValidationError(document.getElementById('sintra_dim_other_w'), 'Maximum allowed is 100 only.');
                
                if (h <= 0) window.showOrderValidationError(document.getElementById('sintra_dim_other_h'), 'Please enter height.');
                else if (h > 100) window.showOrderValidationError(document.getElementById('sintra_dim_other_h'), 'Maximum allowed is 100 only.');
            } else {
                alert('Please enter valid custom dimensions (Max 100).');
            }
            return;
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#sintraForm .shopee-opt-btn input:checked').forEach(inp => {
        inp.closest('.shopee-opt-btn').classList.add('active');
    });
    document.querySelectorAll('#sintraForm .sintra-type-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT' && !e.target.closest('label')) {
                sintraOpenInfoModal(row.getAttribute('data-info-title') || '', row.getAttribute('data-info-body') || '');
            }
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') sintraCloseInfoModal();
    });
    var dimVal = document.getElementById('sintra_dimensions').value.trim();
    if (dimVal) {
        var matched = false;
        document.querySelectorAll('.sintra-dim-btn[data-w]').forEach(function(btn) {
            var w = btn.getAttribute('data-w');
            var h = btn.getAttribute('data-h');
            if (dimVal === w + ' x ' + h) {
                btn.classList.add('active');
                var inp = btn.querySelector('input[type="radio"]');
                if (inp) inp.checked = true;
                matched = true;
            }
        });
        if (!matched && dimVal) {
            var others = document.querySelector('.sintra-dim-btn[data-others="1"]');
            if (others) {
                others.classList.add('active');
                var or = others.querySelector('input[type="radio"]');
                if (or) or.checked = true;
            }
            document.getElementById('sintraDimOthersWrap').style.display = 'block';
        }
    }
    sintraUpdateOthersUnitLabels();
});
</script>

<style>
.shopee-form-row-flat { margin-bottom: 1.5rem; display: flex; align-items: center; }
.dim-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block;  }
.shopee-qty-control { display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.1); width: fit-content; background: rgba(15, 23, 42, 0.6); }
.shopee-qty-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: #f1f5f9; cursor: pointer; font-size: 1.25rem; transition: background 0.2s; }
.shopee-qty-btn:hover { background: rgba(255,255,255,0.05); }
.shopee-qty-input { width: 50px; height: 32px; border: none; border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1); background: none; color: #f1f5f9; text-align: center; -moz-appearance: textfield; font-weight: 600; }
.sintra-info-modal { position: fixed; inset: 0; z-index: 100000; display:none; align-items: center; justify-content: center; padding: 1rem; }
.sintra-info-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.4); }
.sintra-info-modal-card { position: relative; width: 100%; max-width: 520px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.5rem; box-shadow: 0 18px 40px rgba(0,0,0,0.12); }
.sintra-info-modal-close { position: absolute; top: 0.75rem; right: 0.75rem; width: 30px; height: 30px; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 1.1rem; cursor: pointer; display:flex; align-items:center; justify-content:center; }
.sintra-info-modal-close:hover { background: #f1f5f9; }
.sintra-info-modal-title { margin: 0 2rem 0.6rem 0; color: #0f172a; font-size: 1.05rem; font-weight: 800; }
.sintra-info-modal-body { margin: 0; color: #475569; line-height: 1.6; font-size: 0.92rem; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
