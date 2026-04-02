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
            <span class="font-semibold text-gray-900">Reflectorized signage</span>
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
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Reflectorized signage</h1>
                
                <?php
                $stats = service_order_get_page_stats('order_reflectorized');
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

                <form id="reflectorizedForm" enctype="multipart/form-data" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="service_type" value="Reflectorized signage">

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Branch *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-3">
                                <?php foreach($branches as $b): ?>
                                    <label class="shopee-opt-btn"><input type="radio" name="branch_id" value="<?php echo $b['id']; ?>" required style="display:none;" onchange="updateOpt(this)"> <span><?php echo htmlspecialchars(to_sentence_case($b['branch_name'])); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="shopee-form-row shopee-form-row-flat">
                        <label class="shopee-form-label">Product type *</label>
                        <div class="shopee-form-field">
                            <div class="shopee-opt-group opt-grid-3">
                                <label class="shopee-opt-btn" ><input type="radio" name="product_type" value="Subdivision / Gate pass (Vehicle sticker)" style="display:none;" onchange="document.getElementById('refl_product_type').value=this.value; reflToggleProductFields(); reflUpdateOptionVisuals(this)"> <span>Gate pass / sticker</span></label>
                                <label class="shopee-opt-btn" ><input type="radio" name="product_type" value="Plate number / Temporary plate" style="display:none;" onchange="document.getElementById('refl_product_type').value=this.value; reflToggleProductFields(); reflUpdateOptionVisuals(this)"> <span>Temporary plate</span></label>
                                <label class="shopee-opt-btn" ><input type="radio" name="product_type" value="Custom reflectorized sign" style="display:none;" onchange="document.getElementById('refl_product_type').value=this.value; reflToggleProductFields(); reflUpdateOptionVisuals(this)"> <span>Custom signage</span></label>
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
                                    <label class="shopee-opt-btn"><input type="radio" name="temp_plate_material" value="Aluminum sheet" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Aluminum sheet</span></label>
                                    <label class="shopee-opt-btn" ><input type="radio" name="temp_plate_material" value="Aluminum coated (Steel plate)" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Aluminum coated (Steel plate)</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Plate info *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-opt-group grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div><label class="dim-label">Plate number</label><input type="text" name="temp_plate_number" id="temp_plate_number" class="input-field" placeholder="Must match OR/CR"></div>
                                    <div><label class="dim-label">Label</label><input type="text" name="temp_plate_text" class="input-field bg-gray-50 bg-opacity-10" value="TEMPORARY PLATE" readonly></div>
                                    <div><label class="dim-label">Mv file no.</label><input type="text" name="mv_file_number" class="input-field" placeholder="Optional"></div>
                                    <div><label class="dim-label">Dealer name</label><input type="text" name="dealer_name" class="input-field" placeholder="Optional"></div>
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
                                <input type="text" name="gate_pass_subdivision" id="gate_pass_subdivision" class="input-field" placeholder="e.g. Green Valley Subdivision">
                            </div>
                        </div>
                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Gate pass *</label>
                            <div class="shopee-form-field">
                                <div class="shopee-opt-group grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div><label class="dim-label">Pass number</label><input type="text" name="gate_pass_number" id="gate_pass_number" class="input-field" placeholder="e.g. GP-0215"></div>
                                    <div><label class="dim-label">Plate number</label><input type="text" name="gate_pass_plate" id="gate_pass_plate" class="input-field" placeholder="e.g. ABC 1234"></div>
                                    <div><label class="dim-label">Validity year</label><input type="text" name="gate_pass_year" id="gate_pass_year" class="input-field" placeholder="e.g. 2026"></div>
                                    <div><label class="dim-label">Vehicle type</label><select name="gate_pass_vehicle_type" class="input-field"><option value="">Select</option><option value="Car">Car</option><option value="Motorcycle">Motorcycle</option></select></div>
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
                                
                                <div id="gatepass-dim-others-inputs" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);padding-top:1rem;margin-top:1rem">
                                    <div style="width:100%;max-width:440px">
                                        <div style="display:flex;gap:8px;margin-bottom:4px">
                                            <div style="flex:1"><label class="dim-label">Width (in)</label></div>
                                            <div style="width:32px"></div>
                                            <div style="flex:1"><label class="dim-label">Height (in)</label></div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div style="flex:1"><input type="number" id="dimensions_gatepass_w" class="input-field" placeholder="0" oninput="reflGatePassSyncOthers()" data-dimension min="1" max="100"></div>
                                            <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                            <div style="flex:1"><input type="number" id="dimensions_gatepass_h" class="input-field" placeholder="0" oninput="reflGatePassSyncOthers()" data-dimension min="1" max="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Upload design *</label>
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
                                <div id="reflDimOthersWrap" style="display:none;border-top:1px dashed rgba(255,255,255,0.1);padding-top:1rem;margin-top:1rem">
                                    <div style="width:100%;max-width:440px">
                                        <div style="display:flex;gap:8px;margin-bottom:4px">
                                            <div style="flex:1"><label class="dim-label">Width</label></div>
                                            <div style="width:32px"></div>
                                            <div style="flex:1"><label class="dim-label">Height</label></div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div style="flex:1"><input type="number" id="reflDimOthersW" class="input-field" placeholder="0" oninput="reflSyncDimOthers()" data-dimension min="1" max="100"></div>
                                            <div style="width:32px;text-align:center;color:#cbd5e1;font-weight:bold;font-size:1.1rem;flex-shrink:0">×</div>
                                            <div style="flex:1"><input type="number" id="reflDimOthersH" class="input-field" placeholder="0" oninput="reflSyncDimOthers()" data-dimension min="1" max="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Laminate *</label>
                            <div class="shopee-opt-group shopee-form-field">
                                <label class="shopee-opt-btn"><input type="radio" name="laminate_option" value="With lamination" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>With lamination</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="laminate_option" value="Without lamination" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Without lamination</span></label>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Layout *</label>
                            <div class="shopee-opt-group shopee-form-field">
                                <label class="shopee-opt-btn"><input type="radio" name="layout" value="With layout" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>With layout</span></label>
                                <label class="shopee-opt-btn"><input type="radio" name="layout" value="Without layout" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Without layout</span></label>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Brand *</label>
                            <div class="shopee-opt-group shopee-form-field">
                                <label class="shopee-opt-btn" ><input type="radio" name="material_type" value="Kiwalite (Japan brand)" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>Kiwalite (Japan brand)</span></label>
                                <label class="shopee-opt-btn" ><input type="radio" name="material_type" value="3M brand" style="display:none;" onchange="reflUpdateOptionVisuals(this)"> <span>3M brand</span></label>
                            </div>
                        </div>

                        <div class="shopee-form-row shopee-form-row-flat">
                            <label class="shopee-form-label">Upload design *</label>
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

                    <!-- Hidden fields for submission -->
                    <input type="hidden" name="quantity_hidden" id="quantity_hidden">
                    <input type="hidden" name="needed_date_hidden" id="needed_date_hidden">
                    <input type="hidden" name="notes_hidden" id="notes_hidden">
                    <input type="hidden" name="dimensions_submit" id="dimensions_submit">
                    <input type="hidden" name="unit_submit" id="unit_submit" value="in">

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
let gatepassDimMode = 'preset';

function updateOpt(input) {
    const group = input.closest('.shopee-opt-group');
    if (group) {
        group.querySelectorAll('.shopee-opt-btn').forEach(btn => btn.classList.remove('active'));
    }
    input.closest('.shopee-opt-btn')?.classList.add('active');
}

function reflUpdateOptionVisuals(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        const wrap = r.closest('.shopee-opt-btn');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
}

function reflToggleProductFields() {
    const type = document.getElementById('refl_product_type').value;
    
    document.querySelectorAll('.refl-expand').forEach(section => {
        section.style.display = 'none';
        section.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.type !== 'hidden') {
                input.dataset.wasRequired = input.dataset.wasRequired || (input.hasAttribute('required') ? 'true' : 'false');
                input.removeAttribute('required');
            }
        });
    });

    let activeSection = null;
    if (type === 'Plate number / Temporary plate') {
        activeSection = document.querySelector('.refl-tempPlateFields');
    } else if (type === 'Subdivision / Gate pass (Vehicle sticker)') {
        activeSection = document.querySelector('.refl-gatePassFields');
    } else if (type === 'Custom reflectorized sign') {
        activeSection = document.getElementById('reflCustomSection');
    }

    if (activeSection) {
        activeSection.style.display = 'block';
        // Restore required attributes for this section only
        activeSection.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.type !== 'hidden' && input.dataset.wasRequired === 'true') {
                input.setAttribute('required', 'required');
            }
        });
    }
}

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
}

function reflGatePassSelectDimensionOthers(e) {
    e.preventDefault();
    gatepassDimMode = 'others';
    const btnGroup = e.target.closest('.shopee-opt-group');
    btnGroup.querySelectorAll('.shopee-opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('gatepass-dim-others-btn').classList.add('active');
    document.getElementById('gatepass-dim-others-inputs').style.display = 'block';
}

function reflGatePassSyncOthers() {
    let w = document.getElementById('dimensions_gatepass_w').value;
    let h = document.getElementById('dimensions_gatepass_h').value;
    document.getElementById('gatepass_width_hidden').value = w;
    document.getElementById('gatepass_height_hidden').value = h;
}

function reflSelectDimension(label, w, h) {
    document.getElementById('reflDimensionsHidden').value = label;
    document.getElementById('reflDimOthersWrap').style.display = 'none';
    document.querySelectorAll('#reflCustomSection .shopee-opt-btn.refl-dim-btn').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector('#reflCustomSection .refl-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
}

function reflSelectDimensionOthers() {
    document.getElementById('reflDimOthersWrap').style.display = 'flex';
    document.querySelectorAll('#reflCustomSection .shopee-opt-btn.refl-dim-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('#reflCustomSection .refl-dim-btn[data-others="1"]')?.classList.add('active');
}

function reflSyncDimOthers() {
    const w = document.getElementById('reflDimOthersW').value;
    const h = document.getElementById('reflDimOthersH').value;
    document.getElementById('reflDimensionsHidden').value = (w && h) ? (w + ' x ' + h) : '';
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

function reflUpdateNotesCounter(textarea) {
    const count = textarea.value.length;
    document.querySelector('.notes-counter').textContent = `${count} / 500`;
}

document.getElementById('reflectorizedForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const action = e.submitter ? e.submitter.value : 'add_to_cart';
    const type = document.getElementById('refl_product_type').value;
    
    if (!type) {
        if (window.showOrderValidationError) {
            window.showOrderValidationError(document.querySelector('input[name="product_type"]').closest('.shopee-form-field'), 'Please select a product type.');
        } else {
            pfError('Please select a product type.');
        }
        return;
    }

    const form = e.target;
    document.getElementById('notes_hidden').value = document.getElementById('notes_global').value;

    if (type === 'Plate number / Temporary plate') {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_temp').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date_temp').value;
    } else if (type === 'Subdivision / Gate pass (Vehicle sticker)') {
        if (gatepassDimMode === 'preset') {
            const active = document.querySelector('.shopee-opt-btn.active[data-width]');
            if (!active) {
                if (window.showOrderValidationError) window.showOrderValidationError(document.getElementById('gatepass-dim-others-btn').closest('.shopee-opt-group'), 'Please select a dimension.');
                else pfError('Please select a dimension.');
                return;
            }
        } else {
            const w = parseInt(document.getElementById('dimensions_gatepass_w').value, 10) || 0;
            const h = parseInt(document.getElementById('dimensions_gatepass_h').value, 10) || 0;
            if (w <= 0 || h <= 0 || w > 100 || h > 100) {
                if (window.showOrderValidationError) {
                    if (w <= 0) window.showOrderValidationError(document.getElementById('dimensions_gatepass_w'), 'Please enter width.');
                    else if (w > 100) window.showOrderValidationError(document.getElementById('dimensions_gatepass_w'), 'Maximum allowed is 100 only.');
                    if (h <= 0) window.showOrderValidationError(document.getElementById('dimensions_gatepass_h'), 'Please enter height.');
                    else if (h > 100) window.showOrderValidationError(document.getElementById('dimensions_gatepass_h'), 'Maximum allowed is 100 only.');
                } else pfError('Please enter valid dimensions (Max 100).');
                return;
            }
        }
        document.getElementById('quantity_hidden').value = document.getElementById('quantity').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date').value;
        document.getElementById('dimensions_submit').value = document.getElementById('gatepass_width_hidden').value + ' x ' + document.getElementById('gatepass_height_hidden').value;
    } else if (type === 'Custom reflectorized sign') {
        const dPreset = document.querySelector('input[name="dimension_preset"]:checked');
        if (!dPreset) {
            if (window.showOrderValidationError) window.showOrderValidationError(document.getElementById('reflDimensionsHidden').closest('.shopee-form-field').querySelector('.shopee-opt-group'), 'Please select a dimension.');
            else pfError('Please select a dimension.');
            return;
        }
        if (dPreset.value === 'Others') {
            const w = parseInt(document.getElementById('reflDimOthersW').value, 10) || 0;
            const h = parseInt(document.getElementById('reflDimOthersH').value, 10) || 0;
            if (w <= 0 || h <= 0 || w > 100 || h > 100) {
                if (window.showOrderValidationError) {
                    if (w <= 0) window.showOrderValidationError(document.getElementById('reflDimOthersW'), 'Please enter width.');
                    else if (w > 100) window.showOrderValidationError(document.getElementById('reflDimOthersW'), 'Maximum allowed is 100 only.');
                    if (h <= 0) window.showOrderValidationError(document.getElementById('reflDimOthersH'), 'Please enter height.');
                    else if (h > 100) window.showOrderValidationError(document.getElementById('reflDimOthersH'), 'Maximum allowed is 100 only.');
                } else pfError('Please enter valid dimensions (Max 100).');
                return;
            }
        }
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_custom').value;
        document.getElementById('needed_date_hidden').value = document.getElementById('needed_date_custom').value;
        document.getElementById('dimensions_submit').value = document.getElementById('reflDimensionsHidden').value;
    }

    const formData = new FormData(form);
    formData.append('action', action);

    // Visual feedback for processing
    const btn = e.submitter;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="inline-block animate-spin mr-2">↻</span> Processing...';

    fetch('api_add_to_cart_reflectorized.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = action === 'buy_now' ? 'order_review.php?item=' + data.item_key : 'cart.php';
        } else {
            pfError('Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        console.error(err);
        pfError('An error occurred during submission.');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
});

document.addEventListener('DOMContentLoaded', () => {
    reflToggleProductFields();
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
