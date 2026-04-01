<?php
/**
 * Reflectorized (Subdivision Stickers / Signages) - Service Order Form
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Reflectorized Signage - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$_svc = db_query("SELECT hero_image FROM services WHERE customer_link LIKE '%order_reflectorized%' LIMIT 1");
$display_img = (!empty($_svc) && !empty($_svc[0]['hero_image'])) ? $_svc[0]['hero_image'] : '';
if ($display_img !== '' && strpos($display_img, 'http') === false && $display_img[0] !== '/') { $display_img = '/' . ltrim($display_img, '/'); }

// Standard reflectorized sizes (inches)
$dimension_presets = [
    '12 x 18' => ['w' => 12, 'h' => 18],
    '18 x 24' => ['w' => 18, 'h' => 24],
    '24 x 36' => ['w' => 24, 'h' => 36],
];
?>

<div class="min-h-screen py-8">
    <div class="shopee-layout-container">
        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mb-6 flex items-center gap-2">
            <a href="services.php" class="hover:text-blue-600">Services</a>
            <span>/</span>
            <span class="font-semibold text-gray-900">Reflectorized Signage</span>
        </div>

        <div class="shopee-card">
            <!-- Left Side: Image -->
            <div class="shopee-image-section">
                <div class="shopee-main-image-wrap">
                        <img src="<?php echo htmlspecialchars($display_img ?: 'https://placehold.co/600x600/f8fafc/0f172a?text=Reflectorized'); ?>" alt="Reflectorized Signage" class="shopee-main-image" onerror="this.src='https://placehold.co/600x600/f8fafc/0f172a?text=Reflectorized'">
                    </div>
                </div>
            <!-- Right Side: Form -->
            <div class="shopee-form-section">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Reflectorized Signage</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_reflectorized');
                $raw_avg = (float)($stats['avg_rating'] ?? 0);
                $review_count = (int)($stats['review_count'] ?? 0);
                $sold_count = (int)($stats['sold_count'] ?? 0);
                $sold_display = $sold_count >= 1000 ? number_format($sold_count / 1000, 1) . 'k' : $sold_count;
                
                $_s_name = 'PrintFlow Service';
                $_s_row = db_query("SELECT name FROM services WHERE customer_link LIKE '%order_reflectorized%' LIMIT 1");
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

                <form id="reflectorizedForm" enctype="multipart/form-data" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_type" value="Reflectorized Signage">

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="updateOpt(this)"> <span><?php echo htmlspecialchars($b['branch_name']); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Product type *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group">
                                <label class="shopee-opt-btn" ><input type="radio" name="product_type" value="Subdivision / Gate Pass (Vehicle Sticker)" style="display:none;" onchange="document.getElementById('refl_product_type').value=this.value; reflToggleProductFields(); reflUpdateOptionVisuals(this)"> <span>Gate Pass / Sticker</span></label>
                                <label class="shopee-opt-btn" ><input type="radio" name="product_type" value="Plate Number / Temporary Plate" style="display:none;" onchange="document.getElementById('refl_product_type').value=this.value; reflToggleProductFields(); reflUpdateOptionVisuals(this)"> <span>Temporary Plate</span></label>
                                <label class="shopee-opt-btn" ><input type="radio" name="product_type" value="Custom Reflectorized Sign" style="display:none;" onchange="document.getElementById('refl_product_type').value=this.value; reflToggleProductFields(); reflUpdateOptionVisuals(this)"> <span>Custom Signage</span></label>
                            </div>
                            <input type="hidden" id="refl_product_type" name="product_type_hidden">
                        </div>
                    </div>

                    <!-- Temporary Plate Fields -->
                    <div class="refl-expand refl-tempPlateFields" style="display: none;">
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Material *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-opt-group">
                                    <label class="shopee-opt-btn"><input type="radio" name="temp_plate_material" value="Acrylic" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Acrylic</span></label>
                                    <label class="shopee-opt-btn"><input type="radio" name="temp_plate_material" value="Aluminum Sheet" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Aluminum Sheet</span></label>
                                    <label class="shopee-opt-btn" ><input type="radio" name="temp_plate_material" value="Aluminum Coated (Steel Plate)" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Aluminum Coated (Steel Plate)</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Plate info *</label>
                            <div class="shopee-form-field">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" style="">
                                    <div><label class="dim-label">Plate Number</label><input type="text" name="temp_plate_number" id="temp_plate_number" class="input-field" placeholder="Must match OR/CR"></div>
                                    <div><label class="dim-label">Label</label><input type="text" name="temp_plate_text" class="input-field bg-gray-50 bg-opacity-10" value="TEMPORARY PLATE" readonly></div>
                                    <div><label class="dim-label">MV File No.</label><input type="text" name="mv_file_number" class="input-field" placeholder="Optional"></div>
                                    <div><label class="dim-label">Dealer Name</label><input type="text" name="dealer_name" class="input-field" placeholder="Optional"></div>
                                </div>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Needed date *</label>
                            <div class="shopee-form-field">
                                <input type="date" id="needed_date_temp" class="input-field" min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Quantity *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-qty-control">
                                    <button type="button" onclick="reflQtyDownTemp()" class="shopee-qty-btn">−</button>
                                    <input type="number" id="quantity_temp" class="shopee-qty-input" min="1" max="999" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" oninput="reflQtyClampTemp()">
                                    <button type="button" onclick="reflQtyUpTemp()" class="shopee-qty-btn">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gate Pass Fields -->
                    <div class="refl-expand refl-gatePassFields" style="display: none;">
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Subdivision *</label>
                            <div class="shopee-form-field">
                                <input type="text" name="gate_pass_subdivision" id="gate_pass_subdivision" class="input-field" placeholder="e.g. GREEN VALLEY SUBDIVISION" style="">
                            </div>
                        </div>
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Gate pass *</label>
                            <div class="shopee-form-field">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" style="">
                                    <div><label class="dim-label">Pass Number</label><input type="text" name="gate_pass_number" id="gate_pass_number" class="input-field" placeholder="e.g. GP-0215"></div>
                                    <div><label class="dim-label">Plate Number</label><input type="text" name="gate_pass_plate" id="gate_pass_plate" class="input-field" placeholder="e.g. ABC 1234"></div>
                                    <div><label class="dim-label">Validity Year</label><input type="text" name="gate_pass_year" id="gate_pass_year" class="input-field" placeholder="e.g. 2026"></div>
                                    <div><label class="dim-label">Vehicle Type</label><select name="gate_pass_vehicle_type" class="input-field"><option value="">Select</option><option value="Car">Car</option><option value="Motorcycle">Motorcycle</option></select></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Dimensions (in) *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-opt-group mb-4">
                                    <button type="button" class="shopee-opt-btn" data-width="3" data-height="3" onclick="reflGatePassSelectDimension(3, 3, event)">3×3 in</button>
                                    <button type="button" class="shopee-opt-btn" data-width="4" data-height="4" onclick="reflGatePassSelectDimension(4, 4, event)">4×4 in</button>
                                    <button type="button" class="shopee-opt-btn" data-width="5" data-height="5" onclick="reflGatePassSelectDimension(5, 5, event)">5×5 in</button>
                                    <button type="button" class="shopee-opt-btn" id="gatepass-dim-others-btn" onclick="reflGatePassSelectDimensionOthers(event)">Others</button>
                                </div>
                                <input type="hidden" name="gatepass_width" id="gatepass_width_hidden">
                                <input type="hidden" name="gatepass_height" id="gatepass_height_hidden">
                                
                                <div id="gatepass-dim-others-inputs" style="display:none;border-top:1px dashed #eee;padding-top:1rem;margin-top:1rem">
                                    <div style="width:100%;max-width:440px">
                                        <div style="display:flex;gap:8px;margin-bottom:4px">
                                            <div style="flex:1"><label class="dim-label">Width</label></div>
                                            <div style="width:32px"></div>
                                            <div style="flex:1"><label class="dim-label">Height</label></div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div style="flex:1"><input type="text" id="dimensions_gatepass_w" class="input-field" placeholder="e.g. 10" oninput="reflGatePassSyncOthers()"></div>
                                            <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                            <div style="flex:1"><input type="text" id="dimensions_gatepass_h" class="input-field" placeholder="e.g. 12" oninput="reflGatePassSyncOthers()"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Upload Design *</label>
                            <div class="shopee-form-field">
                                <input type="file" name="gate_pass_logo" id="gate_pass_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field" style="max-width: 300px; padding: 0.5rem;">
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Needed date *</label>
                            <div class="shopee-form-field">
                                <input type="date" id="needed_date" class="input-field" min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Quantity *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-qty-control">
                                    <button type="button" onclick="reflQtyDown()" class="shopee-qty-btn">−</button>
                                    <input type="number" id="quantity" class="shopee-qty-input" min="1" max="999" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" oninput="reflQtyClamp()">
                                    <button type="button" onclick="reflQtyUp()" class="shopee-qty-btn">+</button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Custom Reflectorized Sign Section -->
                    <div id="reflCustomSection" class="refl-expand" style="display: none;">
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Dimensions *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-opt-group mb-3">
                                    <?php foreach($dimension_presets as $label => $d): ?>
                                    <label class="shopee-opt-btn refl-dim-btn" data-w="<?php echo $d['w']; ?>" data-h="<?php echo $d['h']; ?>">
                                        <input type="radio" name="dimension_preset" value="<?php echo $label; ?>" style="display:none;" onchange="reflSelectDimension('<?php echo $label; ?>', <?php echo $d['w']; ?>, <?php echo $d['h']; ?>)">
                                        <span><?php echo $label; ?> in</span>
                                    </label>
                                    <?php endforeach; ?>
                                    <label class="shopee-opt-btn refl-dim-btn" data-others="1">
                                        <input type="radio" name="dimension_preset" value="Others" style="display:none;" onchange="reflSelectDimensionOthers()">
                                        <span>Others</span>
                                    </label>
                                </div>
                                <input type="hidden" id="reflDimensionsHidden">
                                <div id="reflDimOthersWrap" style="display:none;border-top:1px dashed #eee;padding-top:1rem;margin-top:1rem">
                                    <div style="width:100%;max-width:440px">
                                        <div style="display:flex;gap:8px;margin-bottom:4px">
                                            <div style="flex:1"><label class="dim-label">Width</label></div>
                                            <div style="width:32px"></div>
                                            <div style="flex:1"><label class="dim-label">Height</label></div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div style="flex:1"><input type="text" id="reflDimOthersW" class="input-field" placeholder="e.g. 10" oninput="reflSyncDimOthers()"></div>
                                            <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                            <div style="flex:1"><input type="text" id="reflDimOthersH" class="input-field" placeholder="e.g. 14" oninput="reflSyncDimOthers()"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Laminate *</label>
                            <div class="shopee-opt-group shopee-form-field">
                                <label class="shopee-opt-btn"><input type="radio" name="laminate_option" value="With Laminate" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>With Lamination</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="laminate_option" value="Without Laminate" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Without Lamination</span></label>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Layout *</label>
                            <div class="shopee-opt-group shopee-form-field">
                                <label class="shopee-opt-btn"><input type="radio" name="layout" value="With Layout" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>With Layout</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="layout" value="Without Layout" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Without Layout</span></label>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Brand *</label>
                            <div class="shopee-opt-group shopee-form-field">
                                <label class="shopee-opt-btn" ><input type="radio" name="material_type" value="Kiwalite (Japan Brand)" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Kiwalite (Japan Brand)</span></label>
                                <label class="shopee-opt-btn" ><input type="radio" name="material_type" value="3M Brand" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>3M Brand</span></label>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Upload Design *</label>
                            <div class="shopee-form-field">
                                <input type="file" name="signage_logo" id="signage_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field" style="max-width: 300px; padding: 0.5rem;">
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Needed date *</label>
                            <div class="shopee-form-field">
                                <input type="date" id="needed_date_custom" class="input-field" min="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Quantity *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-qty-control">
                                    <button type="button" onclick="reflQtyDownCustom()" class="shopee-qty-btn">−</button>
                                    <input type="number" id="quantity_custom" class="shopee-qty-input" min="1" max="999" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" oninput="reflQtyClampCustom()">
                                    <button type="button" onclick="reflQtyUpCustom()" class="shopee-qty-btn">+</button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div id="reflNotesSection" class="shopee-form-row shopee-form-row-flat" style="align-items: flex-start;">
                        <label class="shopee-form-label" style="padding-top: 0.75rem;">Notes</label>
                        <div class="shopee-form-field">
                            <div style="display:flex; justify-content:flex-end; margin-bottom: 0.25rem;">
                                <span id="refl-notes-counter" style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;">0 / 500</span>
                            </div>
                            <textarea id="notes_global" rows="3" class="input-field" placeholder="Any special requests or instructions..." maxlength="500" oninput="reflUpdateNotesCounter(this)"></textarea>
                        </div>
                    </div>

                    <input type="hidden" name="quantity_gatepass" id="quantity_gatepass">
                    <input type="hidden" name="quantity_signage" id="quantity_signage">
                    <input type="hidden" name="quantity" id="quantity_hidden">
                    <input type="hidden" name="needed_date" id="needed_date_hidden">
                    <input type="hidden" name="other_instructions" id="notes_hidden">
                    <input type="hidden" name="dimensions" id="dimensions_submit">
                    <input type="hidden" name="unit" id="unit_submit" value="in">

                    <div class="shopee-form-row pt-8">
                        <div style="width: 160px;" class="hidden md:block"></div>
                        <div class="flex gap-4 flex-1">
                            <a href="<?php echo BASE_URL; ?>/customer/services.php" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">Back</a>
                            <button type="button" onclick="reflSubmitOrder('add_to_cart')" class="shopee-btn-outline" style="flex: 1; height: 3.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-color: var(--lp-accent); background: rgba(83, 197, 224, 0.05); color: var(--lp-accent); font-weight: 700;" title="Add to Cart">
                                <svg style="width: 1.1rem; height: 1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Add To Cart
                            </button>
                            <button type="button" onclick="reflSubmitOrder('buy_now')" class="shopee-btn-primary" style="flex: 1.5; height: 3.5rem; font-size: 1.1rem; font-weight: 800;">Buy Now</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let gatepassDimMode = 'preset';

function reflGatePassSelectDimension(w, h, e) {
    e.preventDefault();
    gatepassDimMode = 'preset';
    const btnGroup = e.target.closest('.shopee-opt-group');
    btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    const b = e.target.closest('.shopee-opt-btn');
    if(b) b.classList.add('active');
    document.getElementById('gatepass-dim-others-inputs').style.display = 'none';
    document.getElementById('gatepass_width_hidden').value = w;
    document.getElementById('gatepass_height_hidden').value = h;
    document.getElementById('dimensions_gatepass_w').value = w;
    document.getElementById('dimensions_gatepass_h').value = h;
}

function reflGatePassSelectDimensionOthers(e) {
    e.preventDefault();
    gatepassDimMode = 'others';
    const btnGroup = e.target.closest('.shopee-opt-group');
    btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('gatepass-dim-others-btn').classList.add('active');
    document.getElementById('gatepass-dim-others-inputs').style.display = 'block';
    reflGatePassSyncOthers();
}

function reflGatePassSyncOthers() {
    let w = document.getElementById('dimensions_gatepass_w').value.trim();
    let h = document.getElementById('dimensions_gatepass_h').value.trim();
    document.getElementById('gatepass_width_hidden').value = w;
    document.getElementById('gatepass_height_hidden').value = h;
}
let reflLastSelectedType = '';
window.__reflValidationTriggered = false;

function reflUpdateOptionVisuals(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflSelectDimension(label, w, h) {
    document.getElementById('reflDimensionsHidden').value = label;
    document.getElementById('reflDimOthersWrap').style.display = 'none';
    document.querySelectorAll('#reflCustomSection .shopee-opt-btn').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector('#reflCustomSection .refl-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflSelectDimensionOthers() {
    document.getElementById('reflDimOthersWrap').style.display = 'flex';
    document.querySelectorAll('#reflCustomSection .shopee-opt-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('#reflCustomSection .refl-dim-btn[data-others="1"]')?.classList.add('active');
    reflSyncDimOthers();
}

function reflSyncDimOthers() {
    const w = (document.getElementById('reflDimOthersW')?.value || '').trim();
    const h = (document.getElementById('reflDimOthersH')?.value || '').trim();
    document.getElementById('reflDimensionsHidden').value = (w && h) ? (w + ' x ' + h) : '';
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflClearFieldError(container) {
    if (!container) return;
    container.classList.remove('is-invalid');
    const err = container.querySelector('.field-error');
    if (err) { err.textContent = ''; err.style.display = 'none'; }
}

function reflSetFieldError(container, message) {
    if (!container) return;
    let err = container.querySelector('.field-error');
    if (message) {
        if (!err) { err = document.createElement('div'); err.className = 'field-error'; container.appendChild(err); }
        container.classList.add('is-invalid');
        err.textContent = message;
        err.style.display = 'block';
    } else {
        container.classList.remove('is-invalid');
        if (err) { err.textContent = ''; err.style.display = 'none'; }
    }
}

function reflCheckFormValid() {
    const show = window.__reflValidationTriggered === true;
    const form = document.getElementById('reflectorizedForm');
    const branch = form.querySelector('select[name="branch_id"]');
    const type = document.getElementById('refl_product_type').value;

    const cBranch = branch.closest('.shopee-form-row');
    const cType = document.getElementById('refl_product_type').closest('.shopee-form-row');

    let ok = !!(branch.value && type);

    if (show) {
        reflSetFieldError(cBranch, !branch.value ? 'This field is required' : '');
        reflSetFieldError(cType, !type ? 'This field is required' : '');
    }

    // Reset section error states
    reflClearFieldError(document.querySelector('.refl-tempPlateFields'));
    reflClearFieldError(document.querySelector('.refl-gatePassFields'));
    reflClearFieldError(document.getElementById('reflCustomSection'));

    if (type === 'Plate Number / Temporary Plate') {
        const mat = document.querySelector('input[name="temp_plate_material"]:checked');
        const plate = document.getElementById('temp_plate_number').value.trim();
        const nd = document.getElementById('needed_date_temp').value;
        const cFields = document.querySelector('.refl-tempPlateFields');
        const okTemp = !!mat && !!plate && !!nd;
        if (show && !okTemp) {
            let msg = 'Please complete all required fields';
            if (!mat) msg = 'Please select a material';
            else if (!plate) msg = 'Please enter the plate number';
            else if (!nd) msg = 'Please select a needed date';
            reflSetFieldError(cFields, msg);
        }
        ok = ok && okTemp;
    } else if (type === 'Subdivision / Gate Pass (Vehicle Sticker)') {
        const sub = document.getElementById('gate_pass_subdivision').value.trim();
        const num = document.getElementById('gate_pass_number').value.trim();
        const gplate = document.getElementById('gate_pass_plate').value.trim();
        const year = document.getElementById('gate_pass_year').value.trim();
        const w = document.getElementById('gatepass_width_hidden').value.trim();
        const h = document.getElementById('gatepass_height_hidden').value.trim();
        const file = document.getElementById('gate_pass_logo').files.length > 0;
        const nd = document.getElementById('needed_date').value;
        const cFields = document.querySelector('.refl-gatePassFields');
        const okGate = !!sub && !!num && !!gplate && !!year && !!w && !!h && file && !!nd;
        if (show && !okGate) {
            let msg = 'Please complete all required fields';
            if (!sub) msg = 'Please enter subdivision name';
            else if (!num) msg = 'Please enter gate pass number';
            else if (!gplate) msg = 'Please enter plate number';
            else if (!year) msg = 'Please enter year/validity';
            else if (!w || !h) msg = 'Please select or enter dimensions';
            else if (!file) msg = 'Please upload a design file';
            else if (!nd) msg = 'Please select a needed date';
            reflSetFieldError(cFields, msg);
        }
        ok = ok && okGate;
    } else if (type === 'Custom Reflectorized Sign') {
        const dim = document.getElementById('reflDimensionsHidden').value.trim();
        const lam = document.querySelector('input[name="laminate_option"]:checked');
        const layout = document.querySelector('input[name="layout"]:checked');
        const mat = document.querySelector('input[name="material_type"]:checked');
        const file = document.getElementById('signage_logo').files.length > 0;
        const nd = document.getElementById('needed_date_custom').value;
        const cFields = document.getElementById('reflCustomSection');
        const okCustom = !!dim && !!lam && !!layout && !!mat && file && !!nd;
        if (show && !okCustom) {
            let msg = 'Please complete all required fields';
            if (!dim) msg = 'Please select or enter dimensions';
            else if (!lam) msg = 'Please select a lamination option';
            else if (!layout) msg = 'Please select a layout option';
            else if (!mat) msg = 'Please select a material brand';
            else if (!file) msg = 'Please upload a design file';
            else if (!nd) msg = 'Please select a needed date';
            reflSetFieldError(cFields, msg);
        }
        ok = ok && okCustom;
    }

    return ok;
}

function reflToggleProductFields() {
    const type = document.getElementById('refl_product_type').value;
    document.querySelectorAll('.refl-expand').forEach(el => el.style.display = 'none');
    // Global notes section is always visible now as it moved to the bottom

    // Reset required attributes first
    document.querySelectorAll('.refl-form input, .refl-form select, .refl-form textarea').forEach(el => {
        if (el.name !== 'branch_id' && el.name !== 'product_type') {
            el.removeAttribute('required');
        }
    });

    if (type === 'Plate Number / Temporary Plate') {
        document.querySelector('.refl-tempPlateFields').style.display = 'block';
        document.getElementById('temp_plate_number').setAttribute('required', 'required');
        document.getElementById('needed_date_temp').setAttribute('required', 'required');
    } else if (type === 'Subdivision / Gate Pass (Vehicle Sticker)') {
        document.querySelector('.refl-gatePassFields').style.display = 'block';
        document.getElementById('gate_pass_subdivision').setAttribute('required', 'required');
        document.getElementById('gate_pass_number').setAttribute('required', 'required');
        document.getElementById('gate_pass_plate').setAttribute('required', 'required');
        document.getElementById('gate_pass_year').setAttribute('required', 'required');
        document.getElementById('dimensions_gatepass_w').setAttribute('required', 'required');
        document.getElementById('dimensions_gatepass_h').setAttribute('required', 'required');
        document.getElementById('gate_pass_logo').setAttribute('required', 'required');
        document.getElementById('needed_date').setAttribute('required', 'required');
    } else if (type === 'Custom Reflectorized Sign') {
        document.getElementById('reflCustomSection').style.display = 'block';
        document.getElementById('signage_logo').setAttribute('required', 'required');
        document.getElementById('needed_date_custom').setAttribute('required', 'required');
    }
    if (window.__reflValidationTriggered) reflCheckFormValid();
}

function reflQtyUp() { const i = document.getElementById('quantity'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function reflQtyDown() { const i = document.getElementById('quantity'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function reflQtyUpTemp() { const i = document.getElementById('quantity_temp'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function reflQtyDownTemp() { const i = document.getElementById('quantity_temp'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }
function reflQtyUpCustom() { const i = document.getElementById('quantity_custom'); i.value = Math.min(999, (parseInt(i.value) || 1) + 1); }
function reflQtyDownCustom() { const i = document.getElementById('quantity_custom'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1; }

function reflQtyClamp() { const i = document.getElementById('quantity'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }
function reflQtyClampTemp() { const i = document.getElementById('quantity_temp'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }
function reflQtyClampCustom() { const i = document.getElementById('quantity_custom'); let v = parseInt(i.value) || 1; i.value = Math.min(999, Math.max(1, v)); }

function reflSubmitOrder(action) {
    window.__reflValidationTriggered = true;
    if (!reflCheckFormValid()) return;

    const form = document.getElementById('reflectorizedForm');
    const type = document.getElementById('refl_product_type').value;

    const globalNotes = document.getElementById('notes_global').value;
    document.getElementById('notes_hidden').value = globalNotes;

    if (type === 'Plate Number / Temporary Plate') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_temp').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date_temp').value;
        document.getElementById('dimensions_submit').value = 'Standard';
    } else if (type === 'Subdivision / Gate Pass (Vehicle Sticker)') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date').value;
        document.getElementById('dimensions_submit').value = document.getElementById('dimensions_gatepass_w').value + ' x ' + document.getElementById('dimensions_gatepass_h').value;
    } else if (type === 'Custom Reflectorized Sign') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_custom').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date_custom').value;
        document.getElementById('dimensions_submit').value = document.getElementById('reflDimensionsHidden').value;
    }

    const formData = new FormData(form);
    formData.append('action', action);

    const buttons = form.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);

    fetch('api_add_to_cart_reflectorized.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = action === 'buy_now' ? 'order_review.php?item=' + data.item_key : 'cart.php';
        } else {
            alert('Error: ' + data.message);
            buttons.forEach(b => b.disabled = false);
        }
    })
    .catch(err => {
        console.error(err);
        alert('An unexpected error occurred.');
        buttons.forEach(b => b.disabled = false);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    reflToggleProductFields();
});
</script>

<style>
.dim-label { font-size: 0.70rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; text-transform: uppercase; }
.dim-sep { height: 44px; display: flex; align-items: center; color: #cbd5e1; font-weight: bold; }
.refl-expand { display: flex; flex-direction: column; gap: 0rem; }
.shopee-form-row.align-top { align-items: flex-start; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
