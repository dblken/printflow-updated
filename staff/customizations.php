<?php
/**
 * Staff: Customizations Management
 * Production tracking & material assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');
require_role(['Admin', 'Staff', 'Manager']);
$page_title = 'Customizations - PrintFlow';

$branch_ctx = init_branch_context(false);
$staffBranchId = (int)$branch_ctx['selected_branch_id'];
$branchName = $branch_ctx['branch_name'];

$branchFilter = $staffBranchId;
$joBranchSql = '';
$joBranchTypes = '';
$joBranchParams = [];
$ordBranchSql = '';
$ordBranchTypes = '';
$ordBranchParams = [];
if ($branchFilter !== null) {
    $b = (int) $branchFilter;
    $joBranchSql = ' AND COALESCE(jo.branch_id, (SELECT o2.branch_id FROM orders o2 WHERE o2.order_id = jo.order_id LIMIT 1)) = ?';
    $joBranchTypes = 'i';
    $joBranchParams = [$b];
    $ordBranchSql = ' AND branch_id = ?';
    $ordBranchTypes = 'i';
    $ordBranchParams = [$b];
}

// Get statistics for KPIs (include both job_orders and regular orders pending review)
$total_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE 1=1" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$total_orders_pending = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE order_type = 'custom' AND status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$total_jobs = $total_jobs_jobs + $total_orders_pending;

$pending_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'PENDING'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$pending_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE order_type = 'custom' AND status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$pending_jobs = $pending_jobs_jobs + $pending_orders;

$approval_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'APPROVED'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$in_production_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'IN_PRODUCTION'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$in_production_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE order_type = 'custom' AND status IN ('Processing', 'In Production', 'Printing', 'Paid – In Process', 'Paid - In Process')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$in_production = $in_production_jobs + $in_production_orders;

$completed_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'COMPLETED'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$completed_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE order_type = 'custom' AND status = 'Completed'" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$completed_jobs = $completed_jobs_jobs + $completed_orders;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="turbo-visit-control" content="reload">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        /* PREMIUM KPI SECTION (Fluid Layout) */
        .kpi-premium-container {
            background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(6, 161, 161, 0.1);
        }
        .kpi-bg-shape {
            position: absolute;
            background: linear-gradient(135deg, rgba(6, 161, 161, 0.1), rgba(6, 161, 161, 0.05));
            border-radius: 50%;
            pointer-events: none;
            filter: blur(50px);
            z-index: 1;
        }
        .shape-1 { width: 400px; height: 400px; top: -150px; right: -50px; animation: float 18s infinite alternate; }
        .shape-2 { width: 300px; height: 300px; bottom: -80px; left: -80px; animation: float 15s infinite alternate-reverse; }

        .kpi-card-v2 {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 2;
        }
        .kpi-card-v2:hover { 
            transform: translateY(-4px); 
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 30px -10px rgba(6, 161, 161, 0.12); 
            border-color: #06A1A1;
        }
        .kpi-v2-value { 
            font-size: 28px; 
            font-weight: 950; 
            color: #013a3a; 
            line-height: 1; 
            margin-bottom: 8px; 
            letter-spacing: -0.04em;
        }
        .kpi-v2-label { 
            font-size: 10px; 
            font-weight: 800; 
            color: #0d9488; 
            text-transform: uppercase; 
            letter-spacing: 0.1em;
            opacity: 0.9;
        }
        .kpi-v2-sub { 
            font-size: 11px; 
            color: #475569; 
            margin-top: 4px; 
            font-weight: 600;
            opacity: 0.6;
        }
        .kpi-card-indicator { position: absolute; top: 12px; right: 18px; width: 28px; height: 4px; border-radius: 2px; opacity: 0.4; }

        @keyframes float {
            from { transform: translate(0, 0) rotate(0deg); }
            to { transform: translate(20px, 20px) rotate(10deg); }
        }

        .kpi-v2-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        @media (max-width: 1200px) { .kpi-v2-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .kpi-v2-row { grid-template-columns: 1fr; } }



        /* ── Status Tabs (Restored) ─── */
        .pf-custom-tabs-row {
            display: flex;
            align-items: center;
            width: 100%;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .pf-custom-tabs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        .pill-tab { 
            position: relative;
            padding: 8px 14px; 
            font-weight: 700; 
            font-size: 11px; 
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b; 
            border-radius: 9999px; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            white-space: nowrap;
        }
        .pill-tab:hover { background: #f1f5f9; color: #1e293b; border-color: #cbd5e1; }
        .pill-tab.active { background: #0d9488; color: #ffffff; border-color: #0d9488; box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.2); }
        .tab-count { 
            background: rgba(0, 0, 0, 0.1);
            color: inherit; 
            font-size: 10px; 
            padding: 1px 7px; 
            border-radius: 9999px; 
            font-weight: 800;
        }
        .pill-tab.active .tab-count { background: rgba(255, 255, 255, 0.2); color: #ffffff; }

        /* ── Toolbar Buttons (Sort / Filter) ─── */
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
        .toolbar-btn.active { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
        .toolbar-btn svg { flex-shrink: 0; }

        /* ── Filter Panel ─── */
        .filter-panel {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 340px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            overflow: hidden;
        }
        .filter-panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .filter-section {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
        }
        .filter-section:last-of-type { border-bottom: none; }
        .filter-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-section-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .filter-reset-link {
            font-size: 12px;
            font-weight: 600;
            color: #0d9488;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            box-sizing: border-box;
            transition: border-color 0.15s;
        }
        .filter-input:focus { outline: none; border-color: #0d9488; }
        .filter-date-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .filter-select-v2 {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            background: #fff;
            box-sizing: border-box;
            cursor: pointer;
        }
        .filter-select-v2:focus { outline: none; border-color: #0d9488; }

        /* ── Sort Dropdown ─── */
        .sort-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            padding: 6px 0;
            overflow: hidden;
        }
        .sort-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            transition: background 0.1s;
        }
        .sort-option:hover { background: #f9fafb; }
        .sort-option.selected { color: #0d9488; font-weight: 600; background: #f0fdfa; }
        .sort-option .check { margin-left: auto; color: #0d9488; }

        /* ── Active filter badge ─── */
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #0d9488;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
        }

        [x-cloak] { display: none !important; }
        .dashboard-container { min-height: 100vh; background: #f8fafc; }
        .main-content {
            padding: 12px 14px 0 !important;
            max-width: none !important;
            width: 100%;
        }
        main { padding: 0 !important; }
        header { padding: 12px 0 12px 0 !important; background: transparent !important; margin-bottom: 0 !important; }
        .page-title { margin-bottom: 0 !important; }
        
        .card { 
            padding: 16px !important; 
            border-radius: 12px !important; 
            margin-bottom: 12px !important;
            border: 1px solid #e2e8f0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
        }
        .kpi-premium-container { margin-bottom: 12px !important; padding: 18px 24px !important; }
    </style>
</head>
<body data-base-url="<?php echo htmlspecialchars(BASE_URL); ?>" data-csrf="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
<div class="dashboard-container">
    <?php 
    if (in_array($_SESSION['user_type'] ?? '', ['Staff', 'Manager'])) {
        include __DIR__ . '/../includes/staff_sidebar.php';
    } else {
        include __DIR__ . '/../includes/admin_sidebar.php';
    }
    ?>
    <div class="main-content">
        <div id="staffJoCustomizationsPage" x-data="joManager('ALL')" x-init="init()" class="pf-staff-customizations-root" @keydown.escape.window="onSvcEscape()">
        <header style="display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 20px;">
            <h1 class="page-title" style="margin:0;">Customizations</h1>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; background: #f1f5f9; padding: 6px 12px; border-radius: 8px;">
                Branch: <?php echo htmlspecialchars($branchName); ?>
            </div>
        </header>

        <main>
            <!-- New Premium KPI Container -->
            <div class="kpi-premium-container">
                <div class="kpi-bg-shape shape-1"></div>
                <div class="kpi-bg-shape shape-2"></div>
                
                <div class="kpi-v2-row">
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #06A1A1;"></div>
                        <div class="kpi-v2-label">Total Customizations</div>
                        <div class="kpi-v2-value"><?php echo $total_jobs; ?></div>
                        <div class="kpi-v2-sub"><?php echo $completed_jobs; ?> items finished</div>
                    </div>
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #d97706;"></div>
                        <div class="kpi-v2-label">Pending Approval</div>
                        <div class="kpi-v2-value" style="color: #d97706;"><?php echo $pending_jobs; ?></div>
                        <div class="kpi-v2-sub">Awaiting review</div>
                    </div>
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #0369a1;"></div>
                        <div class="kpi-v2-label">Approved</div>
                        <div class="kpi-v2-value" style="color: #0369a1;"><?php echo $approval_jobs; ?></div>
                        <div class="kpi-v2-sub">Ready for production</div>
                    </div>
                    <div class="kpi-card-v2">
                        <div class="kpi-card-indicator" style="background: #059669;"></div>
                        <div class="kpi-v2-label">In Production</div>
                        <div class="kpi-v2-value" style="color: #059669;"><?php echo $in_production; ?></div>
                        <div class="kpi-v2-sub">Aktive task tracks</div>
                    </div>
                </div>
            </div>

            <!-- Jobs List & Filters (matching Enterprise reference) -->
            <div class="card overflow-visible">
                <!-- Restored Status Tabs Row -->
                <div class="pf-custom-tabs-row">
                    <div class="pf-custom-tabs">
                        <template x-for="st in [
                            {id:'ALL', label:'All'},
                            {id:'PENDING', label:'Pending'},
                            {id:'APPROVED', label:'Approved'},
                            {id:'TO_PAY', label:'To Pay'},
                            {id:'TO_VERIFY', label:'To Verify'},
                            {id:'IN_PRODUCTION', label:'In Production'},
                            {id:'TO_RECEIVE', label:'To Pickup'},
                            {id:'COMPLETED', label:'Completed'},
                            {id:'CANCELLED', label:'Cancelled'}
                        ]" :key="st.id">
                            <button type="button" @click="activeStatus = st.id" :class="{ 'active': activeStatus === st.id }" class="pill-tab">
                                <span x-text="st.label"></span>
                                <span class="tab-count" x-text="getStatusCount(st.id)">0</span>
                            </button>
                        </template>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                    <h3 style="font-size:16px; font-weight:700; color:#1f2937; margin:0;">Customizations List</h3>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <!-- Search Field -->
                        <div style="position: relative; width: 260px;">
                            <input type="text" x-model="search" placeholder="Search Order # or Customer..." 
                                   style="width: 100%; height: 38px; padding: 0 12px 0 36px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 13px;">
                            <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </div>
                        </div>

                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: sortOpen }" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/>
                                </svg>
                                <span x-text="activeSortLabel">Newest First</span>
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <template x-for="s in [
                                    {id:'newest', label:'Newest First'},
                                    {id:'oldest', label:'Oldest First'},
                                    {id:'name_asc', label:'Customer (A-Z)'},
                                    {id:'name_desc', label:'Customer (Z-A)'}
                                ]" :key="s.id">
                                    <div class="sort-option" :class="{ 'selected': activeSort === s.id }" @click="applySort(s.id)">
                                        <span x-text="s.label"></span>
                                        <svg x-show="activeSort === s.id" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: filterOpen || activeFilterCount > 0 }" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                </svg>
                                Filters
                                <span class="filter-badge" x-show="activeFilterCount > 0" x-text="activeFilterCount"></span>
                            </button>
                            <!-- Filter Panel -->
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Advanced Filters</div>
                                
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Service Type</span>
                                        <button class="filter-reset-link" @click="serviceFilter = 'ALL'">Reset</button>
                                    </div>
                                    <select class="filter-select-v2" x-model="serviceFilter">
                                        <option value="ALL">All Services</option>
                                        <option value="TARPAULIN PRINTING">Tarpaulin</option>
                                        <option value="T-SHIRT PRINTING">T-Shirt</option>
                                        <option value="DECALS/STICKERS (PRINT/CUT)">Stickers/Decals</option>
                                        <option value="SINTRA BOARD">Sintra Board</option>
                                        <option value="SOUVENIRS">Souvenirs</option>
                                    </select>
                                </div>

                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Order Date</span>
                                        <button class="filter-reset-link" @click="dateFilter = 'ALL'">Reset</button>
                                    </div>
                                    <select class="filter-select-v2" x-model="dateFilter">
                                        <option value="ALL">All Time</option>
                                        <option value="TODAY">Today</option>
                                        <option value="WEEK">Last 7 Days</option>
                                        <option value="MONTH">This Month</option>
                                        <option value="CUSTOM">Custom Range</option>
                                    </select>
                                    <div x-show="dateFilter === 'CUSTOM'" style="margin-top:10px;" class="filter-date-row">
                                        <div>
                                            <div class="filter-date-label">From</div>
                                            <input type="date" class="filter-input" x-model="customDateFrom">
                                        </div>
                                        <div>
                                            <div class="filter-date-label">To</div>
                                            <input type="date" class="filter-input" x-model="customDateTo">
                                        </div>
                                    </div>
                                </div>

                                <div style="padding:14px 18px; background:#f9fafb; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:12px; color:#6b7280;" x-text="filteredOrders.length + ' results found'"></span>
                                    <button class="filter-reset-link" @click="resetFilters()">Clear All</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6" style="clear:both;">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="pl-6 pr-4 py-4 w-[12%] border-b border-gray-100">Order #</th>
                                <th class="px-4 py-4 w-[30%] border-b border-gray-100">Customization Info</th>
                                <th class="px-4 py-4 w-[18%] border-b border-gray-100 text-center">Status</th>
                                <th class="px-4 py-4 w-[20%] border-b border-gray-100">Customer</th>
                                <th class="px-4 py-4 w-[15%] border-b border-gray-100 text-right">Created</th>
                                <th class="px-4 py-4 w-[10%] border-b border-gray-100 text-center uppercase tracking-widest text-[10px]">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="jo in paginatedOrders" :key="(jo.order_type || 'JOB') + '-' + jo.id">
                                <tr @click="viewDetails(jo.id, jo.order_type || 'JOB')" class="group transition-all hover:bg-gray-50/50 relative cursor-pointer">
                                    <td class="pl-6 pr-4 py-4 relative">
                                        <div class="row-indicator"></div>
                                        <span class="table-text-main" x-text="(jo.order_type === 'ORDER' ? '#ORD-' : (jo.order_type === 'SERVICE' ? '#SRV-' : '#JO-')) + jo.id.toString().padStart(5, '0')"></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex flex-col gap-0 min-w-0">
                                                <div class="table-text-main truncate" x-text="jo.job_title || jo.service_type"></div>
                                                <div class="table-text-sub uppercase tracking-wider" x-show="jo.order_type !== 'SERVICE'"><span x-text="jo.width_ft"></span>'×<span x-text="jo.height_ft"></span>' • <span x-text="jo.quantity"></span> pcs</div>
                                                <div class="table-text-sub uppercase tracking-wider" x-show="jo.order_type === 'SERVICE'">Service purchase</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div :class="{
                                            'badge-fulfilled':  jo.status === 'COMPLETED',
                                            'badge-approved':   jo.status === 'APPROVED',
                                            'badge-topay':      jo.status === 'TO_PAY',
                                            'badge-verify':     jo.status === 'VERIFY_PAY',
                                            'badge-production': jo.status === 'IN_PRODUCTION',
                                            'badge-pickup':     jo.status === 'TO_RECEIVE',
                                            'badge-pending':    jo.status === 'PENDING',
                                            'badge-cancelled':  jo.status === 'CANCELLED'
                                        }" class="status-badge-pill" x-text="jo.status === 'COMPLETED' ? 'Fulfilled' : 
                                           (jo.status === 'APPROVED' ? 'Approved' : 
                                           (jo.status === 'TO_PAY' ? 'To Pay' : 
                                           (jo.status === 'VERIFY_PAY' ? 'To Verify' : 
                                           (jo.status === 'IN_PRODUCTION' ? 'Processing' : 
                                           (jo.status === 'TO_RECEIVE' ? 'To Pickup' : jo.status)))))">
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="table-text-main" x-text="jo.first_name + ' ' + (jo.last_name || '')"></div>
                                        <div class="table-text-sub" style="margin-top:4px;max-width:220px;word-break:break-word;" x-show="jo.customer_contact" x-text="jo.customer_contact"></div>
                                        <div style="margin-top:4px;">
                                            <span style="font-size:10px; font-weight:700; width:100px; padding:2px 0;" class="status-badge-pill" :class="jo.customer_type === 'NEW' ? 'badge-approved' : 'badge-fulfilled'" x-text="jo.customer_type"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="table-text-main" x-text="jo.created_at ? new Date(jo.created_at).toLocaleDateString(undefined, {month:'long', day:'numeric', year:'numeric'}) : ''"></div>
                                        <div class="table-text-sub uppercase" x-text="jo.due_date ? 'Due ' + new Date(jo.due_date).toLocaleDateString() : ''"></div>
                                    </td>
                                    <td class="px-4 py-4 text-center space-x-1">
                                        <button @click.stop="viewDetails(jo.id, jo.order_type || 'JOB')" class="btn-staff-action btn-staff-action-blue">View</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredOrders.length === 0">
                                <td colspan="6" class="px-6 py-24 text-center">
                                    <span class="table-text-sub uppercase tracking-widest">No matching jobs in this stage</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div x-show="totalPages > 1" style="display:block; width:100%; text-align:center; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; gap:4px;">
                        <button x-show="currentPage > 1" @click="currentPage--" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:500;transition:all 0.2s;" onmouseover="this.style.background='#f5f7fa'" onmouseout="this.style.background='white'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <template x-for="(p, i) in pageNumbers" :key="i">
                        <span style="display:inline-flex;">
                            <span x-show="p === '...'" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;font-size:13px;color:#9ca3af;letter-spacing:1px;">···</span>
                            <button x-show="p !== '...'" @click="currentPage = p" :style="currentPage === p ? 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #0d9488;background:#0d9488;color:white;text-decoration:none;font-size:13px;font-weight:600;' : 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;text-decoration:none;font-size:13px;font-weight:500;transition:all 0.2s;'" x-text="p"></button>
                        </span>
                    </template>
                    <button x-show="currentPage < totalPages" @click="currentPage++" style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:500;transition:all 0.2s;" onmouseover="this.style.background='#f5f7fa'" onmouseout="this.style.background='white'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    </div>
                </div>
            </div>
        </main>

    <!-- No more materials modal - integrated into details -->

<!-- Image Preview Lightbox -->
<div x-show="previewFile" x-cloak style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:999999; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; box-sizing:border-box;">
    <div style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center;">
        <img :src="previewFile" style="max-width:100%; max-height:calc(100vh - 160px); border-radius:12px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); object-fit:contain;">
        <div style="margin-top:24px; display:flex; justify-content:center; gap:16px;">
            <button @click="previewFile = null" type="button" style="background:#ef4444; color:white; padding:12px 32px; border-radius:8px; border:none; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                Close Original Image
            </button>
            <a :href="previewFile" download style="background:white; color:#1f2937; padding:12px 24px; border-radius:8px; border:none; text-decoration:none; font-size:14px; font-weight:600; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download Artwork
            </a>
        </div>
    </div>
</div>

<!-- Customization Details Modal — matching customers_management.php style -->
<div x-show="showDetailsModal" x-cloak>
    <div class="modal-overlay" @click.self="showDetailsModal = false">
        <div class="modal-panel" @click.stop>

            <!-- Loading State -->
            <div x-show="loadingDetails" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#06A1A1;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading job details...</p>
            </div>

            <!-- Content -->
            <div x-show="!loadingDetails && currentJo.id">

                <!-- Modal Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;" x-text="'Customization #' + currentJo.id"></h3>
                        <p style="font-size:12px;color:#6b7280;margin:2px 0 0;" x-text="getCorrectServiceType(currentJo)"></p>
                    </div>
                    <button @click="showDetailsModal = false" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div style="padding:24px;">

                    <!-- Customer Row -->
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">
                        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#06A1A1,#047676);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:22px;flex-shrink:0;" x-text="currentJo.customer_full_name ? currentJo.customer_full_name[0].toUpperCase() : '?'"></div>
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#1f2937;" x-text="currentJo.customer_full_name"></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                                <span style="font-size:11px; font-weight:700; min-width:80px; padding:2px 8px;" class="status-badge-pill" :class="currentJo.customer_type === 'NEW' ? 'badge-approved' : 'badge-fulfilled'" x-text="currentJo.customer_type"></span>
                                <span style="font-size:12px;color:#6b7280;" x-text="currentJo.customer_contact"></span>
                            </div>
                            <div x-show="currentJo.customer_address" style="font-size:12px;color:#6b7280;margin-top:8px;max-width:100%;word-break:break-word;" x-text="currentJo.customer_address"></div>
                        </div>
                    </div>


                    <!-- Dynamic Order Details (service-specific fields from customization_data) -->
                    <template x-if="currentJo.items && currentJo.items.length > 0">
                        <div style="margin-bottom:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:12px;">Order Details (Customer Specifications)</label>
                            <template x-for="(item, idx) in currentJo.items" :key="item.order_item_id || idx">
                                <div style="margin-bottom:16px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                                    <div style="font-size:13px; font-weight:700; color:#1f2937; margin-bottom:10px;" x-text="getDynamicProductName(item) + ' × ' + item.quantity"></div>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:10px;">
                                        <template x-for="([k, v]) in getDisplayableCustom(item.customization)" :key="k">
                                            <div style="padding:8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; min-width:0; overflow-wrap:break-word;">
                                                <div style="font-size:10px; font-weight:600; color:#6b7280; text-transform:uppercase; margin-bottom:2px;" x-text="getCustomLabel(k)"></div>
                                                <div style="font-size:12px; font-weight:500; color:#1f2937; word-break:break-word; overflow-wrap:break-word;" x-text="formatCustomValuePlain(v)"></div>
                                                <a x-show="isDisplayableLink(v)" :href="sanitizeStaffLink(v)" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#4f46e5;font-weight:600;margin-top:4px;display:inline-block;">Open link →</a>
                                            </div>
                                        </template>
                                    </div>
                                    <template x-if="item.design_url">
                                        <div style="margin-top:12px;">
                                            <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px;">Design Preview</div>
                                            <div style="display:flex; align-items:flex-end; gap:12px;">
                                                <img :src="item.design_url" 
                                                     @click="previewFile = item.design_url"
                                                     style="width:140px; height:auto; border-radius:10px; border:1px solid #e2e8f0; cursor:zoom-in; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);" 
                                                     onerror="this.src='<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/images/services/default.png', ENT_QUOTES, 'UTF-8'); ?>'">
                                                <a href="javascript:void(0)" @click.prevent="previewFile = item.design_url" style="font-size:11px; color:#4f46e5; text-decoration:none; font-weight:600; padding:6px 10px; background:#f5f3ff; border-radius:6px; transition:all 0.2s;" onmouseover="this.style.background='#ddd6fe'" onmouseout="this.style.background='#f5f3ff'">
                                                    Open Original Image
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="item.reference_url">
                                        <div style="margin-top:12px;">
                                            <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px;">Reference image</div>
                                            <div style="display:flex; align-items:flex-end; gap:12px;">
                                                <img :src="item.reference_url"
                                                     @click="previewFile = item.reference_url"
                                                     style="width:140px; height:auto; border-radius:10px; border:1px solid #e2e8f0; cursor:zoom-in; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);"
                                                     onerror="this.style.display='none'">
                                                <a href="javascript:void(0)" @click.prevent="previewFile = item.reference_url" style="font-size:11px; color:#4f46e5; text-decoration:none; font-weight:600; padding:6px 10px; background:#f5f3ff; border-radius:6px;">Open reference image</a>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>


                    <!-- Notes -->
                    <div style="margin-bottom:20px;" x-show="combinedCustomerNotes().trim() !== '' && combinedCustomerNotes() !== 'No specific instructions.'">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:6px;">Order Notes</label>
                        <div style="font-size:13px;color:#6b7280;background:#fffbeb;border:1px solid #fef3c7;padding:10px 14px;border-radius:8px;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;" x-text="combinedCustomerNotes()"></div>
                    </div>

                    <!-- 4. TO_VERIFY (Payment Verification) -->
                    <template x-if="isVerifyStageRow(currentJo)">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;display:block;margin-bottom:16px;">Step 4: Verify Payment Proof</label>
                            
                            <div style="display:flex; gap:20px; align-items:flex-start;">
                                <div style="width:160px; flex-shrink:0;">
                                    <template x-if="currentJo.payment_proof_path">
                                        <a href="javascript:void(0)" @click.prevent="previewFile = '/printflow/api_view_proof.php?file=' + encodeURIComponent(currentJo.payment_proof_path)"
                                           style="display:block;line-height:0;">
                                            <img :src="'/printflow/api_view_proof.php?file=' + encodeURIComponent(currentJo.payment_proof_path)"
                                                 style="width:100%; height:auto; border-radius:8px; border:1px solid #d1d5db; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.1);"
                                                 alt="Proof — opens full size in new tab">
                                        </a>
                                    </template>
                                </div>
                                <div style="flex:1;">
                                    <div style="margin-bottom:16px;">
                                        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase;">Amount Submitted</div>
                                        <div style="font-size:22px; font-weight:800; color:#1f2937;" x-text="'₱' + Number(currentJo.payment_submitted_amount || 0).toLocaleString()"></div>
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <button @click="verifyPayment()" class="btn-staff-action btn-staff-action-emerald" style="flex:1;">✓ Approve Payment</button>
                                        <button @click="openRejectPaymentModal()" class="btn-staff-action btn-staff-action-red" style="flex:1;">✕ Reject</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- 2. APPROVED (Set Price & Materials) -->
                    <template x-if="currentJo.status === 'APPROVED'">
                        <div style="margin-bottom:20px; display:flex; flex-direction:column; gap:20px;">
                            
                            <!-- Production Details Section -->
                            <div style="padding:20px; border-radius:16px; border:1px solid #e2e8f0; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <h3 style="font-size:14px; font-weight:700; color:#1f2937; text-transform:uppercase; letter-spacing:0.025em; margin:0;">Production Assignment</h3>
                                    <span style="font-size:11px; background:#f3f4f6; color:#6b7280; padding:4px 10px; border-radius:100px; font-weight:600;" x-text="getCorrectServiceType(currentJo)"></span>
                                </div>

                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                                    
                                    <!-- A. Materials Selection -->
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="font-size:12px; font-weight:700; color:#374151;">[1] Core Materials</label>
                                        
                                        <!-- Searchable Selection -->
                                        <div style="position:relative;">
                                            <input type="text" x-model="materialSearch" placeholder="Search materials (e.g. tarpaulin, vinyl...)" 
                                                   style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; margin-bottom:8px;">
                                            
                                            <select x-model="newMaterialId" @change="newMaterialId = $event.target.value; newMaterialRollId = ''; availableRollsList = []; if(isRollTracked(newMaterialId)) loadAvailableRolls(newMaterialId);" 
                                                    style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:white; cursor:pointer;">
                                                <option value="">-- Choose Material --</option>
                                                <template x-for="item in availableMaterialsForCurrentOrder" :key="item.id">
                                                    <option :value="item.id" x-text="`${item.name} (${item.current_stock} ${item.unit_of_measure})`"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <template x-if="newMaterialId">
                                            <div style="padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                                <div style="grid-column: span 2;">
                                                    <label style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Qty / Length</label>
                                                    <input type="number" x-model.number="newMaterialQty" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                                </div>
                                                <template x-if="isTarpaulin(newMaterialId)">
                                                    <div style="grid-column: span 2;">
                                                        <label style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Height (ft)</label>
                                                        <input type="number" x-model.number="newMaterialHeight" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                                    </div>
                                                </template>
                                                <button @click="addMaterialToQueue()" class="btn-staff-action btn-staff-action-indigo" style="grid-column: span 2; padding:8px; font-size:12px; font-weight:600;">Add to Order Content</button>
                                            </div>
                                        </template>

                                        <!-- Queue Display -->
                                        <div x-show="pendingMaterials.length > 0" style="display:flex; flex-direction:column; gap:6px;">
                                            <template x-for="(pm, idx) in pendingMaterials" :key="idx">
                                                <div style="display:flex; align-items:center; justify-content:space-between; background:#f1f5f9; border-radius:8px; padding:8px 12px; font-size:12px; border:1px solid #e2e8f0;">
                                                    <span style="font-weight:600; color:#1e293b;" x-text="pm.name"></span>
                                                    <div style="display:flex; align-items:center; gap:12px;">
                                                        <span style="color:#64748b;" x-text="pm.qty + ' ' + pm.uom"></span>
                                                        <button @click="pendingMaterials.splice(idx,1)" style="color:#ef4444; border:none; background:none; cursor:pointer; font-weight:700;">✕</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- B. Ink Options -->
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <label style="font-size:12px; font-weight:700; color:#374151;">[2] Ink Options</label>
                                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                                                <input type="checkbox" x-model="useInk" style="width:14px; height:14px; cursor:pointer;">
                                                <span style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">Use Ink</span>
                                            </label>
                                        </div>

                                        <div x-show="useInk" x-transition style="padding:12px; border:1px dashed #cbd5e1; border-radius:12px; background:#f9fafb;">
                                            <label style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:block;">Select Ink Set</label>
                                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px;">
                                                <template x-for="type in availableInkOptionsForService" :key="type">
                                                    <button @click="inkCategorySelected = type" 
                                                            :style="inkCategorySelected === type ? 'background:#06A1A1; color:white; border-color:#06A1A1;' : 'background:white; color:#64748b; border-color:#e2e8f0;'"
                                                            style="padding:6px 12px; border-radius:6px; border:1px solid; font-size:11px; font-weight:700; transition:all 0.2s;"
                                                            x-text="type"></button>
                                                </template>
                                            </div>

                                            <template x-if="inkCategorySelected">
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                                    <div>
                                                        <label style="font-size:9px; font-weight:700; color:#ef4444; text-transform:uppercase; display:block;">RED (ml)</label>
                                                        <input type="number" x-model.number="inkRed" step="0.1" style="width:100%; padding:6px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px;">
                                                    </div>
                                                    <div>
                                                        <label style="font-size:9px; font-weight:700; color:#3b82f6; text-transform:uppercase; display:block;">BLUE (ml)</label>
                                                        <input type="number" x-model.number="inkBlue" step="0.1" style="width:100%; padding:6px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px;">
                                                    </div>
                                                    <div>
                                                        <label style="font-size:9px; font-weight:700; color:#1f2937; text-transform:uppercase; display:block;">BLACK (ml)</label>
                                                        <input type="number" x-model.number="inkBlack" step="0.1" style="width:100%; padding:6px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px;">
                                                    </div>
                                                    <div>
                                                        <label style="font-size:9px; font-weight:700; color:#eab308; text-transform:uppercase; display:block;">YELLOW (ml)</label>
                                                        <input type="number" x-model.number="inkYellow" step="0.1" style="width:100%; padding:6px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px;">
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <div x-show="!useInk" style="font-size:11px; color:#94a3b8; font-style:italic; text-align:center; padding:10px;">
                                            Ink is disabled for this job type.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Final Step: Pricing and Submit -->
                            <div style="padding:20px; border-radius:16px; border:1px solid #06A1A1; background:linear-gradient(to right, #f0fdfa, #f0fdf4); box-shadow:0 4px 6px -1px rgba(6, 161, 161, 0.1);">
                                <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:20px; flex-wrap:wrap;">
                                    <div style="flex:1; min-width:200px;">
                                        <label style="font-size:12px; font-weight:700; color:#0f766e; text-transform:uppercase; display:block; margin-bottom:8px;">[3] Final Pricing (₱)</label>
                                        <div style="position:relative;">
                                            <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-weight:700; color:#0f766e;">₱</span>
                                            <input type="number" x-model.number="jobPriceInput" 
                                                   style="width:100%; padding:12px 12px 12px 32px; border:2px solid #06A1A1; border-radius:10px; font-size:20px; font-weight:800; color:#0f766e; outline:none;">
                                        </div>
                                    </div>
                                    <button @click="submitToPay()" class="btn-action emerald" style="padding:0 32px; height:52px; font-size:15px; font-weight:800; border-radius:12px; display:flex; align-items:center; gap:10px; box-shadow:0 10px 15px -3px rgba(16, 185, 129, 0.4);">
                                        <span>Confirm Approval</span>
                                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    </button>
                                </div>
                                <p style="font-size:12px; color:#0d9488; font-weight:500; margin-top:12px; display:flex; align-items:center; gap:6px;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Approving will notify the customer and prepare materials for production.
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- 3. TO_PAY (Waiting for Payment) -->
                    <template x-if="currentJo.status === 'TO_PAY'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #dbeafe; background:#f0f9ff;">
                            <label style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;display:block;margin-bottom:12px;">Step 3: Awaiting Payment</label>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                                <div style="font-size:14px; font-weight:500; color:#1e40af;">Total Outstanding:</div>
                                <div style="font-size:20px; font-weight:800; color:#1e40af;" x-text="'₱' + Number(currentJo.estimated_total || 0).toLocaleString()"></div>
                            </div>
                            <div style="font-size:13px; color:#1e40af; line-height:1.5;">Waiting for the customer to upload payment proof. Once uploaded, it will appear in the TO VERIFY section.</div>
                        </div>
                    </template>

                    <!-- 5. IN_PRODUCTION -->
                    <template x-if="currentJo.status === 'IN_PRODUCTION' || currentJo.status === 'Processing'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #fbd38d; background:#fffaf0;">
                            <label style="font-size:11px;font-weight:700;color:#9c4221;text-transform:uppercase;display:block;margin-bottom:12px;">Step 5: Production In Progress</label>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:14px; color:#9c4221; font-weight:500;">Currently in production phase.</div>
                                <button @click="jobAction('TO_RECEIVE')" class="btn-action amber">📦 Mark as Ready for Pickup</button>
                            </div>
                        </div>
                    </template>

                    <!-- 6. TO_RECEIVE -->
                    <template x-if="currentJo.status === 'TO_RECEIVE'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #c4b5fd; background:#f5f3ff;">
                            <label style="font-size:11px;font-weight:700;color:#5b21b6;text-transform:uppercase;display:block;margin-bottom:12px;">Step 6: Ready for Pickup</label>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:14px; color:#5b21b6; font-weight:500;">Customer has been notified to pick up the order.</div>
                                <button @click="completeOrder()" class="btn-action emerald">✓ Mark Final Completed</button>
                            </div>
                        </div>
                    </template>

                    <!-- 7. COMPLETED -->
                    <template x-if="currentJo.status === 'COMPLETED'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #bbf7d0; background:#f0fdf4;">
                            <label style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;display:block;margin-bottom:4px;">Workflow Finished</label>
                            <div style="font-size:15px; font-weight:700; color:#15803d; display:flex; align-items:center; gap:8px;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Order Successfully Completed
                            </div>
                        </div>
                    </template>

                    <div x-show="currentJo.materials && currentJo.materials.length > 0" style="margin-top:20px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Assigned Production Materials</label>
                        <template x-for="m in (currentJo.materials || [])" :key="m.id">
                            <div style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:#1f2937;" x-text="m.item_name"></div>
                                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                                        <span x-show="m.track_by_roll == 1">
                                            Req: <span x-text="m.computed_required_length_ft"></span>'
                                            <span x-show="m.roll_code"> (Roll: <span x-text="m.roll_code"></span>)</span>
                                            <span x-show="!m.roll_code"> (Auto Pick Roll)</span>
                                        </span>
                                        <span x-show="m.track_by_roll == 0">Qty: <span x-text="m.quantity"></span></span>
                                        <template x-if="m.metadata && m.metadata.lamination_item_id">
                                            <div style="color:#059669; font-weight:600; margin-top:4px;">
                                                + Lamination (Auto Pick Roll) — <span x-text="m.metadata.lamination_length_ft"></span>'
                                            </div>
                                        </template>
                                        <template x-if="m.metadata && m.metadata.waste_length_ft !== undefined">
                                            <div style="color:#b45309; margin-top:2px;">
                                                Recorded Waste: <span x-text="m.metadata.waste_length_ft"></span>'
                                            </div>
                                        </template>
                                        <span style="color:#06A1A1; font-weight:600; margin-left:8px;" x-show="m.deducted_at">✓ Deducted</span>
                                    </div>
                                </div>
                                <template x-if="!m.deducted_at">
                                    <button type="button" @click="removeMaterial(m.id)" style="background:none; border:none; color:#ef4444; font-size:11px; font-weight:600; cursor:pointer; padding:4px 8px; border-radius:4px; transition:all 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">Remove</button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <template x-if="currentJo.ink_usage && currentJo.ink_usage.length > 0">
                        <div style="margin-top:16px;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Ink Consumption Recorded</label>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <template x-for="ink in (currentJo.ink_usage || [])" :key="ink.id">
                                    <div style="background:#fdf4ff; border:1px solid #fbcfe8; border-radius:6px; padding:6px 10px; font-size:11px; font-weight:600; color:#9d174d;">
                                        <span x-text="ink.item_name + ' → '"></span>
                                        <span x-text="ink.quantity_used + ' bottle'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Design Status / Revision Alert -->
                    <template x-if="currentJo.design_status === 'Revision Submitted'">
                        <div style="margin-bottom:20px; padding:12px; background:#e0f2fe; border:1px solid #bae6fd; border-radius:10px; display:flex; align-items:center; gap:10px;">
                             <div style="background:#0284c7; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 6px -1px rgba(2, 132, 199, 0.4);">
                                 <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 11l5 5m0 0l-5 5m5-5H6"/></svg>
                             </div>
                            <div>
                                <div style="font-size:13px; font-weight:700; color:#0369a1;">Revision Re-submitted</div>
                                <div style="font-size:12px; color:#075985;">The customer has uploaded a new design file. Please review and approve.</div>
                            </div>
                        </div>
                    </template>

                    <!-- Artwork Files -->
                    <div x-show="currentJo.files && currentJo.files.length > 0" style="margin-top:16px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Artwork Files</label>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <template x-for="file in (currentJo.files || [])" :key="file.id">
                                <a :href="'/printflow/' + file.file_path.replace(/^\/+/, '')" target="_blank" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#1f2937;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#06A1A1'" onmouseout="this.style.borderColor='#e5e7eb'">
                                    <span style="font-size:12px;font-weight:500;" x-text="file.file_name"></span>
                                    <span style="font-size:11px;color:#06A1A1;font-weight:600;">View ↗</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>


                <!-- Modal Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <!-- Left: Status actions -->
                    <div style="display:flex;gap:8px; flex-wrap:wrap; align-items:center;">
                        <div x-show="isPendingReviewStatus(currentJo)" style="display:flex; gap:8px;">
                            <button type="button" @click="jobAction('APPROVED')" class="btn-action indigo" style="padding:6px 12px; font-weight:600;">✓ Approve to Set Price</button>
                            <button type="button" @click="openRevisionModal()" class="btn-action" style="padding:6px 12px; color:#ef4444; background:#fef2f2; border:1px solid #fee2e2; font-weight:600;">✕ Request Revision</button>
                        </div>
                        <button type="button" x-show="currentJo.status !== 'CANCELLED' && currentJo.status !== 'COMPLETED'" @click="jobAction('CANCELLED')" class="btn-action red" style="padding:6px 12px;">✕ Cancel</button>
                    </div>
                    <!-- Right: Close -->
                    <button @click="showDetailsModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REVISION MODAL -->
    <template x-if="showRevisionModal">
        <div>
            <!-- Backdrop -->
            <div x-show="showRevisionModal" x-cloak
                 style="position:fixed; inset:0; z-index:10001; background:rgba(17,24,39,0.7);"
                 @click="closeRevisionModal()"></div>
            <!-- Modal Panel — true viewport center via transform -->
            <div x-show="showRevisionModal" x-cloak
                 style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:10002;
                        width:calc(100% - 32px); max-width:420px;
                        background:white; border-radius:16px;
                        box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);
                        border:1px solid #fee2e2; overflow:hidden;">
                <!-- Header -->
                <div style="padding:16px 20px; border-bottom:1px solid #fee2e2; background:#fef2f2; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:16px; font-weight:700; color:#b91c1c;">Request Revision</h3>
                    <button @click="closeRevisionModal()" style="background:none; border:none; color:#f87171; cursor:pointer;" onmouseover="this.style.color='#b91c1c'" onmouseout="this.style.color='#f87171'">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Body -->
                <div style="padding:20px;">
                    <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Reason for Revision</label>
                    <select x-model="revisionReasonSelect" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; outline:none;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'">
                        <option value="">-- Select a reason --</option>
                        <option value="Low image quality">Low image quality</option>
                        <option value="Wrong design uploaded">Wrong design uploaded</option>
                        <option value="Incorrect details provided">Incorrect details provided</option>
                        <option value="Not printable / invalid format">Not printable / invalid format</option>
                        <option value="Others">Others</option>
                    </select>
                    <div x-show="revisionReasonSelect === 'Others'" style="transition:all 0.2s;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Please specify</label>
                        <textarea x-model="revisionReasonText" rows="3" placeholder="Enter custom reason..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; outline:none; box-sizing:border-box;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                </div>
                <!-- Footer -->
                <div style="padding:16px 20px; border-top:1px solid #f3f4f6; background:#f9fafb; display:flex; justify-content:flex-end; gap:8px;">
                    <button @click="closeRevisionModal()" class="btn-secondary">Cancel</button>
                    <button @click="submitRevision()" class="btn-action red">Submit Revision</button>
                </div>
            </div>
        </div>
    </template>

    <!-- REJECT PAYMENT MODAL -->
    <template x-if="showRejectPaymentModal">
        <div>
            <!-- Backdrop -->
            <div x-show="showRejectPaymentModal" x-cloak
                 style="position:fixed; inset:0; z-index:10001; background:rgba(17,24,39,0.7);"
                 @click="closeRejectPaymentModal()"></div>
            <!-- Modal Panel -->
            <div x-show="showRejectPaymentModal" x-cloak
                 style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:10002;
                        width:calc(100% - 32px); max-width:420px;
                        background:white; border-radius:16px;
                        box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);
                        border:1px solid #fee2e2; overflow:hidden;">
                <!-- Header -->
                <div style="padding:16px 20px; border-bottom:1px solid #fee2e2; background:#fef2f2; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:16px; font-weight:700; color:#b91c1c;">Reject Payment Proof</h3>
                    <button @click="closeRejectPaymentModal()" style="background:none; border:none; color:#f87171; cursor:pointer;" onmouseover="this.style.color='#b91c1c'" onmouseout="this.style.color='#f87171'">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Body -->
                <div style="padding:20px;">
                    <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Reason for Rejection</label>
                    <select x-model="rejectPaymentReasonSelect" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; outline:none;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'">
                        <option value="">-- Select a reason --</option>
                        <option value="Unclear image / receipt">Unclear image / receipt</option>
                        <option value="Incorrect amount submitted">Incorrect amount submitted</option>
                        <option value="Payment not received">Payment not received</option>
                        <option value="Expired reference">Expired reference</option>
                        <option value="Others">Others</option>
                    </select>
                    <div x-show="rejectPaymentReasonSelect === 'Others'" style="transition:all 0.2s;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Please specify</label>
                        <textarea x-model="rejectPaymentReasonText" rows="3" placeholder="Enter custom reason..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; outline:none; box-sizing:border-box;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                </div>
                <!-- Footer -->
                <div style="padding:16px 20px; border-top:1px solid #f3f4f6; background:#f9fafb; display:flex; justify-content:flex-end; gap:8px;">
                    <button @click="closeRejectPaymentModal()" class="btn-secondary">Cancel</button>
                    <button @click="submitRejectPayment()" class="btn-action red">Confirm Rejection</button>
                </div>
            </div>
        </div>
    </template>
        <!-- Custom Staff Alert Modal -->
        <div x-show="alertModal.show" x-cloak style="position:fixed; inset:0; z-index:90000; display:flex; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px);">
            <div @click.self="closeStaffAlert()" style="position:fixed; inset:0; background:rgba(17,24,39,0.7);"></div>
            <div style="background:white; border-radius:24px; width:100%; max-width:400px; position:relative; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.4); animation:modalIn 0.3s ease-out;">
                <div style="padding:32px 32px 24px; text-align:center;">
                    <div style="width:56px; height:56px; background:#f0f9ff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                        <svg width="28" height="28" fill="none" stroke="#00A1A1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 x-text="alertModal.title" style="font-size:20px; font-weight:800; color:#111827; margin:0 0 8px;"></h3>
                    <p x-text="alertModal.message" style="font-size:15px; color:#4b5563; line-height:1.6; margin:0;"></p>
                </div>
                <div style="padding:0 32px 32px;">
                    <button @click="closeStaffAlert()" style="width:100%; background:#111827; color:white; border:none; border-radius:14px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">OK</button>
                </div>
            </div>
        </div>

        <!-- Custom Staff Confirm Modal -->
        <div x-show="confirmModal.show" x-cloak style="position:fixed; inset:0; z-index:90000; display:flex; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px);">
            <div @click.self="closeStaffConfirm(false)" style="position:fixed; inset:0; background:rgba(17,24,39,0.7);"></div>
            <div style="background:white; border-radius:24px; width:100%; max-width:420px; position:relative; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.4); animation:modalIn 0.3s ease-out;">
                <div style="padding:32px 32px 24px; text-align:center;">
                    <div style="width:56px; height:56px; background:#fff7ed; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                        <svg width="28" height="28" fill="none" stroke="#ea580c" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 x-text="confirmModal.title" style="font-size:20px; font-weight:800; color:#111827; margin:0 0 8px;"></h3>
                    <p x-text="confirmModal.message" style="font-size:15px; color:#4b5563; line-height:1.6; margin:0;"></p>
                </div>
                <div style="padding:0 32px 32px; display:flex; gap:12px;">
                    <button @click="closeStaffConfirm(false)" x-text="confirmModal.cancelText" style="flex:1; background:#f3f4f6; color:#4b5563; border:none; border-radius:14px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'"></button>
                    <button @click="closeStaffConfirm(true)" x-text="confirmModal.confirmText" style="flex:1; background:#00A1A1; color:white; border:none; border-radius:14px; padding:14px; font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(6,161,161,0.25)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'"></button>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/partials/service_order_modal.php'; ?>
        </div><!-- /#staffJoCustomizationsPage -->
    </div><!-- /.main-content -->
</div><!-- /.dashboard-container -->

<script src="<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/js/staff_service_order_modal.js'); ?>"></script>
<script>
    function joManager(defaultStatus = 'PENDING') {
        return {
            ...printflowStaffServiceOrderModalMixin({
                async afterSvcMutation() { await this.loadOrders(); }
            }),
            statuses: ['ALL', 'PENDING', 'APPROVED', 'TO_PAY', 'TO_VERIFY', 'IN_PRODUCTION', 'TO_RECEIVE', 'COMPLETED', 'CANCELLED'],
            activeStatus: defaultStatus || 'ALL',
            filterOpen: false,
            sortOpen: false,
            activeSort: 'newest',
            serviceFilter: 'ALL',
            dateFilter: 'ALL',
            customDateFrom: '',
            customDateTo: '',
            minPrice: '',
            maxPrice: '',
            customerSearch: '',

            currentPage: 1,
            itemsPerPage: 15,
            orders: [],
            machines: [],
            showDetailsModal: false,
            loadingDetails: false,
            showRevisionModal: false,
            revisionReasonSelect: '',
            revisionReasonText: '',
            showRejectPaymentModal: false,
            rejectPaymentReasonSelect: '',
            rejectPaymentReasonText: '',
            previewFile: null,
            currentJo: {},
            availableRolls: {},
            allInventoryItems: [],
            inventoryPollMs: 20000,
            newMaterialId: '',
            newMaterialQty: 1,
            newMaterialHeight: 0,
            newMaterialRollId: '',
            newMaterialNotes: '',
            newMaterialMetadata: {lamination: '', lamination_roll_id: ''},
            pendingMaterials: [],
            availableRollsList: [],
            laminationItemsList: [],
            availableLamRollsList: [],
            impactPreview: null,
            search: '',
            jobPriceInput: 0,
            
            // Ink Settings
            inkCategorySelected: '',
            inkBlue: '',
            inkRed: '',
            inkBlack: '',
            inkYellow: '',
            useInk: false,
            sortOrder: 'newest',
            materialSearch: '',
            alertModal: {
                show: false,
                title: 'System Message',
                message: '',
                onClose: null
            },
            confirmModal: {
                show: false,
                title: 'Confirm Action',
                message: '',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                onConfirm: null,
                onCancel: null
            },
            showStaffAlert(title, message, onClose = null) {
                this.alertModal.title = title;
                this.alertModal.message = message;
                this.alertModal.onClose = onClose;
                this.alertModal.show = true;
            },
            closeStaffAlert() {
                const cb = this.alertModal.onClose;
                this.alertModal.show = false;
                if (typeof cb === 'function') cb();
            },
            showStaffConfirm(title, message, onConfirm, onCancel = null, confirmText = 'Confirm', cancelText = 'Cancel') {
                this.confirmModal.title = title;
                this.confirmModal.message = message;
                this.confirmModal.confirmText = confirmText;
                this.confirmModal.cancelText = cancelText;
                this.confirmModal.onConfirm = onConfirm;
                this.confirmModal.onCancel = onCancel;
                this.confirmModal.show = true;
            },
            closeStaffConfirm(isConfirm) {
                this.confirmModal.show = false;
                if (isConfirm && typeof this.confirmModal.onConfirm === 'function') {
                    this.confirmModal.onConfirm();
                } else if (!isConfirm && typeof this.confirmModal.onCancel === 'function') {
                    this.confirmModal.onCancel();
                }
            },

            serviceMapping: {
                'TARPAULIN PRINTING': { categories: [2], ink: 'TARP' },
                'T-SHIRT PRINTING': { categories: [7], ink: ['L120', 'L130'] },
                'DECALS/STICKERS (PRINT/CUT)': { categories: [3, 8], ink: ['L120', 'L130'] },
                'GLASS/WALL STICKERS': { categories: [3, 8], ink: ['L120', 'L130'] },
                'TRANSPARENT STICKERS': { categories: [3], ink: ['L120', 'L130'] },
                'REFLECTORIZED': { categories: [3], ink: ['L120', 'L130'] },
                'SINTRA BOARD': { categories: [3], ink: ['L120', 'L130'] },
                'SOUVENIRS': { categories: [3, 1], ink: ['L120', 'L130'] }
            },

            inkTypes: {
                'TARP': { 'BLUE': 24, 'RED': 25, 'BLACK': 26, 'YELLOW': 27 },
                'L120': { 'BLUE': 28, 'RED': 29, 'BLACK': 30, 'YELLOW': 31 },
                'L130': { 'BLUE': 32, 'RED': 33, 'BLACK': 34, 'YELLOW': 35 }
            },

            getDynamicProductName(item) {
                const custom = item.customization || {};
                
                // Helper to safely find a key value case-insensitively
                const findKey = (searchKeys) => {
                    for (const [k, v] of Object.entries(custom)) {
                        const lowerK = k.toLowerCase().replace(/_/g, ' ');
                        const lowerS = searchKeys.map(s => s.toLowerCase().replace(/_/g, ' '));
                        if (lowerS.includes(lowerK) && v) return v;
                    }
                    return null;
                };

                const sintraVal = findKey(['sintra_type', 'sintra type', 'type']);
                if (sintraVal || findKey(['is_standee', 'thickness', 'sintraboard_thickness'])) {
                    return 'Sintra Board - ' + (sintraVal || 'Standee');
                }

                const tarpVal = findKey(['tarp_size', 'tarp size']);
                if (tarpVal) {
                    return 'Tarpaulin Printing - ' + tarpVal;
                }

                const width = findKey(['width']);
                const height = findKey(['height']);
                if (width && height && findKey(['finish', 'with_eyelets'])) {
                    return 'Tarpaulin Printing (' + width + 'x' + height + ' ft)';
                }

                if (findKey(['vinyl_type', 'print_placement', 'tshirt_color', 'shirt_color', 'tshirt_size', 'shirt_source'])) {
                    return 'T-Shirt Printing';
                }

                const stickerVal = findKey(['sticker_type', 'sticker type', 'shape', 'cut_type']);
                if (stickerVal) {
                    return 'Decals/Stickers (Print/Cut) - ' + stickerVal;
                }

                return item.product_name || 'Standard Product';
            },
            getCorrectServiceType(jo) {
                if (!jo) return '';
                if (jo.items && jo.items.length > 0) {
                    for (const item of jo.items) {
                        const custom = item.customization || {};
                        const findKey = (searchKeys) => {
                            for (const [k, v] of Object.entries(custom)) {
                                const lowerK = k.toLowerCase().replace(/_/g, ' ');
                                const lowerS = searchKeys.map(s => s.toLowerCase().replace(/_/g, ' '));
                                if (lowerS.includes(lowerK) && v) return true;
                            }
                            return false;
                        };

                        // User Priority Logic
                        if (findKey(['sintra_type', 'sintra type', 'is_standee', 'type', 'thickness', 'sintraboard_thickness'])) return 'SINTRA BOARD';
                        if (findKey(['tarp_size', 'tarp size', 'with_eyelets', 'finish'])) return 'TARPAULIN PRINTING';
                        if (findKey(['width']) && findKey(['height']) && findKey(['finish', 'with_eyelets'])) return 'TARPAULIN PRINTING';
                        if (findKey(['vinyl_type', 'print_placement', 'tshirt_color', 'shirt_color', 'shirt_source'])) return 'T-SHIRT PRINTING';
                        if (findKey(['sticker_type', 'sticker type', 'shape', 'cut_type', 'lamination'])) return 'DECALS/STICKERS (PRINT/CUT)';
                    }
                }
                const raw = String(jo.service_type || jo.job_title || 'Custom Service').toUpperCase();
                // Standardize common fallbacks
                if (raw.includes('SINTRA')) return 'SINTRA BOARD';
                if (raw.includes('TARP')) return 'TARPAULIN PRINTING';
                if (raw.includes('SHIRT')) return 'T-SHIRT PRINTING';
                if (raw.includes('STICKER') || raw.includes('DECAL')) return 'DECALS/STICKERS (PRINT/CUT)';
                return raw;
            },

            customFieldLabels: {
                size: 'Size', color: 'Color', shirt_color: 'Color', print_placement: 'Placement',
                design_type: 'Design Type', template: 'Template', width: 'Width (ft)', height: 'Height (ft)',
                finish: 'Finish', with_eyelets: 'Eyelets', shape: 'Shape', waterproof: 'Waterproof',
                lamination: 'Lamination', laminate_option: 'Lamination Option', layout: 'Layout',
                dimensions: 'Dimensions', needed_date: 'Needed Date', notes: 'Notes', additional_notes: 'Notes',
                tshirt_provider: 'T-Shirt Provider', shirt_source: 'Shirt Source', brand: 'Brand',
                material: 'Material', surface_application: 'Surface', surface_type: 'Surface Type',
                sintraboard_thickness: 'Thickness', is_standee: 'Standee', sticker_type: 'Sticker Type',
                sintra_type: 'Type',
                cut_type: 'Cut Type', thickness: 'Thickness', installation_fee: 'Installation Fee',
                design_upload: 'Design upload', reference_upload: 'Reference upload',
                design_upload_path: 'Design file path', reference_upload_path: 'Reference file path'
            },
            // Redundant / internal keys to skip in the customization grid.
            // notes and additional_notes are handled separately in the yellow 'Order Notes' box.
            customFieldSkip: ['Branch_ID', 'branch_id', 'service_type', 'product_type', 'notes', 'additional_notes', 'layout_file', 'reference_file'],
            getDisplayableCustom(custom) {
                if (!custom || typeof custom !== 'object') return [];
                const skip = this.customFieldSkip;
                return Object.entries(custom).filter(([k, v]) => {
                    if (v === '' || v == null) return false;
                    if (skip.includes(k)) return false;
                    if (typeof v === 'string' && v.length > 2000) return false;
                    return true;
                });
            },
            getCustomLabel(k) {
                return this.customFieldLabels[k] || k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            },
            formatCustomValuePlain(v) {
                if (v == null) return '';
                if (typeof v === 'object') return JSON.stringify(v);
                return String(v);
            },
            isDisplayableLink(v) {
                if (v == null || typeof v === 'object') return false;
                const s = String(v).trim();
                if (s.length < 2) return false;
                if (/^https?:\/\//i.test(s)) return true;
                return s.startsWith('/');
            },
            sanitizeStaffLink(v) {
                const s = String(v).trim();
                if (/^https?:\/\//i.test(s)) return s;
                if (s.startsWith('/')) return s;
                return '#';
            },
            combinedCustomerNotes() {
                const j = this.currentJo;
                if (!j) return '';
                
                // Priority 1: Store order notes (Checkout notes)
                let note = (j.store_order_notes || '').trim();
                
                // Priority 2: Item-specific customization notes
                if (!note && j.items && j.items.length) {
                    for (const it of j.items) {
                        const c = it.customization || {};
                        const n = (c.notes || c.additional_notes || '').trim();
                        if (n) { 
                            note = n; 
                            break; 
                        }
                    }
                }
                
                // Priority 3: Production/Job notes (filter out Revision history)
                if (!note && j.notes) {
                    const lines = j.notes.split('\n')
                        .filter(l => !l.includes('[REVISION REQUEST]'))
                        .map(l => l.trim())
                        .filter(l => l !== '');
                    note = lines.join('\n');
                }

                return note || '';
            },
            isPendingReviewStatus(jo) {
                if (!jo) return false;
                const s = String(jo.status || '');
                const u = s.toUpperCase().replace(/\s+/g, '_');
                if (['PENDING', 'PENDING_REVIEW', 'PENDING_APPROVAL', 'FOR_REVISION'].includes(u)) return true;
                return ['Pending Review', 'Pending Approval', 'For Revision'].includes(s);
            },

            /** Row is in payment-verification stage (TO_VERIFY tab + merge dedupe). */
            isVerifyStageRow(row) {
                if (!row) return false;
                const s = String(row.status || '').toUpperCase().replace(/\s+/g, '_');
                const p = String(row.payment_proof_status || '').toUpperCase();
                const stage = s === 'VERIFY_PAY' || s === 'TO_VERIFY' || s === 'PENDING_VERIFICATION' || s === 'DOWNPAYMENT_SUBMITTED';
                const proofPresent = Boolean(row.payment_proof_path || row.payment_proof);
                const amountSubmitted = Number(row.payment_submitted_amount || 0);

                // If proof already verified/rejected, never show verify action.
                if (p === 'VERIFIED' || p === 'REJECTED') return false;

                // Normal: status indicates verify stage and proof is marked SUBMITTED.
                if (stage) return p === 'SUBMITTED';

                // Inconsistent rows: if proof is present with a positive submitted amount,
                // still treat it as verify-stage so staff can complete verification.
                if (proofPresent && amountSubmitted > 0) return true;

                // Fallback: if payment_proof_status is SUBMITTED, consider it verify-stage.
                return p === 'SUBMITTED';
            },

            /** Store/job row is actively in production (matches IN_PRODUCTION tab). */
            isInProductionRow(row) {
                if (!row) return false;
                const raw = String(row.status || '').trim();
                const t = raw.toUpperCase().replace(/\s+/g, '_');
                const p = String(row.payment_proof_status || '').toUpperCase();
                // Job row lagged after verify but proof is verified on job_orders
                if (p === 'VERIFIED' && (t === 'TO_PAY' || t === 'APPROVED')) return true;
                if (t === 'IN_PRODUCTION' || t === 'PROCESSING' || t === 'PRINTING') return true;
                // orders.status after verify: "Paid – In Process" (en-dash) or "Paid - In Process" (ASCII) if SQL CASE did not normalize
                if (/PAID[-–_\s]+IN[-–_\s]+PROCESS/i.test(raw)) return true;
                return false;
            },

            /** Waiting for customer payment — exclude proof submitted (TO_VERIFY) or already verified (production). */
            isToPayRow(row) {
                if (!row) return false;
                const s = String(row.status || '').toUpperCase().replace(/\s+/g, '_');
                const p = String(row.payment_proof_status || '').toUpperCase();
                if (!(s === 'TO_PAY' || s === 'APPROVED')) return false;
                if (p === 'SUBMITTED' || p === 'VERIFIED') return false;
                return true;
            },

            get availableMaterialsForCurrentOrder() {
                if (!this.currentJo || !this.allInventoryItems) return [];
                
                const serviceRaw = String(this.currentJo.service_type || this.currentJo.job_title || '').toUpperCase();
                let allowedCats = [];

                // Detect matching service from mapping
                for (const [key, map] of Object.entries(this.serviceMapping)) {
                    if (serviceRaw.includes(key)) {
                        allowedCats = map.categories;
                        break;
                    }
                }

                return this.allInventoryItems.filter(item => {
                    // MUST HAVE STOCK > 0
                    const stock = parseFloat(item.current_stock || 0);
                    if (stock <= 0) return false;

                    // If we found specific categories for this service, filter by them
                    if (allowedCats.length > 0) {
                        if (!allowedCats.includes(Number(item.category_id))) return false;
                    }

                    // Search filter
                    if (this.materialSearch && !item.name.toUpperCase().includes(this.materialSearch.toUpperCase())) {
                        return false;
                    }

                    return true;
                });
            },

            get availableInkOptionsForService() {
                if (!this.currentJo) return [];
                const serviceRaw = String(this.currentJo.service_type || this.currentJo.job_title || '').toUpperCase();
                for (const [key, map] of Object.entries(this.serviceMapping)) {
                    if (serviceRaw.includes(key)) {
                        return Array.isArray(map.ink) ? map.ink : [map.ink];
                    }
                }
                return ['L120', 'L130']; // Default
            },

            async init() {
                this.$watch('search', () => { this.currentPage = 1; });
                this.$watch('activeStatus', () => { this.currentPage = 1; });
                this.$watch('serviceFilter', () => { this.currentPage = 1; });
                this.$watch('dateFilter', () => { this.currentPage = 1; });
                this.$watch('customDateFrom', () => { this.currentPage = 1; });
                this.$watch('customDateTo', () => { this.currentPage = 1; });
                this.$watch('activeSort', () => { this.currentPage = 1; });

                await this.loadOrders();
                await this.loadMachines();
                await this.loadAllInventoryItems();

                // Keep stock values in sync with admin-side ledger deductions.
                // This page otherwise fetches `current_stock` only once on load and would show stale stock.
                if (!window.pfStaffCustomizationsInventoryPollListenerAttached) {
                    window.pfStaffCustomizationsInventoryPollListenerAttached = true;
                    document.addEventListener('turbo:before-cache', function () {
                        if (window.pfStaffCustomizationsInventoryPoll) {
                            clearInterval(window.pfStaffCustomizationsInventoryPoll);
                            window.pfStaffCustomizationsInventoryPoll = null;
                        }
                    });
                }
                if (window.pfStaffCustomizationsInventoryPoll) {
                    clearInterval(window.pfStaffCustomizationsInventoryPoll);
                }
                window.pfStaffCustomizationsInventoryPoll = setInterval(() => {
                    this.loadAllInventoryItems().catch(() => {});
                }, this.inventoryPollMs);
                
                // Auto-open modal if order_id is in URL
                const params = new URLSearchParams(window.location.search);
                const orderId = params.get('order_id');
                const initialStatus = params.get('status');

                if (initialStatus) {
                    // Map common statuses to tabs
                    const statusMap = {
                        'TO_VERIFY': 'TO_VERIFY',
                        'PENDING_VERIFICATION': 'TO_VERIFY',
                        'DOWNPAYMENT_SUBMITTED': 'TO_VERIFY',
                        'VERIFY_PAY': 'TO_VERIFY',
                        'TO_PAY': 'TO_PAY',
                        'PENDING': 'PENDING',
                        'PENDING_REVIEW': 'PENDING',
                        'APPROVED': 'APPROVED',
                        'PROCESSING': 'IN_PRODUCTION'
                    };
                    const mapped = statusMap[initialStatus.toUpperCase().replace(/\s+/g, '_')] || initialStatus;
                    if (this.statuses.includes(mapped)) {
                        this.activeStatus = mapped;
                    } else if (orderId) {
                        // If we have an order_id but the status doesn't match a tab, default to ALL to ensure it's found
                        this.activeStatus = 'ALL';
                    }
                }

                if (orderId) {
                    const jobType = params.get('job_type') || 'JOB';
                    await this.viewDetails(parseInt(orderId, 10), jobType);
                }
            },

            async loadOrders() {
                try {
                    const [joRes, ordersRes] = await Promise.all([
                        fetch('../admin/job_orders_api.php?action=list_orders&per_page=200').then(r => r.json()),
                        fetch('../admin/job_orders_api.php?action=list_pending_orders').then(r => r.json())
                    ]);

                    const jobOrders = joRes.success ? joRes.data : [];
                    if (!ordersRes.success) {
                        console.warn('list_pending_orders failed:', ordersRes.error || ordersRes);
                    }
                    const regularOrders = ordersRes.success ? ordersRes.data : [];
                    
                    // Merge then sort newest first
                    const combined = [...jobOrders, ...regularOrders];
                    const sorted = combined.sort((a, b) => {
                        const ta = new Date(a.created_at || a.order_date || 0).getTime();
                        const tb = new Date(b.created_at || b.order_date || 0).getTime();
                        return tb - ta;
                    });

                    // Merge JOB + ORDER for same store order_id: keep the row that reflects payment verification.
                    const storeIdsWithJob = new Set(
                        jobOrders
                            .filter(j => j.order_id != null && j.order_id !== '')
                            .map(j => String(j.order_id))
                    );

                    this.orders = sorted
                        .filter(row => {
                            if (row.order_type !== 'ORDER') return true;
                            const oid = String(row.order_id ?? row.id ?? '');
                            if (!storeIdsWithJob.has(oid)) return true;
                            if (!this.isVerifyStageRow(row)) return false;
                            const jobRow = sorted.find(
                                r => r.order_type === 'JOB' && r.order_id != null && String(r.order_id) === oid
                            );
                            if (jobRow && this.isVerifyStageRow(jobRow)) return false;
                            return true;
                        })
                        .filter(row => {
                            if (row.order_type !== 'JOB') return true;
                            if (row.order_id == null || row.order_id === '') return true;
                            const oid = String(row.order_id);
                            const orderRow = sorted.find(
                                r => r.order_type === 'ORDER' && String(r.order_id ?? r.id) === oid
                            );
                            if (!orderRow || !this.isVerifyStageRow(orderRow)) return true;
                            return this.isVerifyStageRow(row);
                        })
                        .map(o => ({
                            ...o,
                            _ts: new Date(o.created_at || o.order_date || 0).getTime()
                        }));
                } catch(err) {
                    console.error('Error loading orders:', err);
                    this.orders = [];
                }
            },

            async loadMachines() {
                const res = await (await fetch('../admin/job_orders_api.php?action=list_machines')).json();
                this.machines = res.success ? res.data : [];
            },

            sameId(a, b) {
                if (a == null || b == null) return false;
                return String(a) === String(b);
            },

            /** job_orders.id for API calls (handles ORDER rows where id is store order_id) */
            effectiveJobId() {
                const j = this.currentJo;
                if (!j) return null;
                if (j.order_type === 'ORDER') {
                    const jid = j.job_order_id;
                    return jid != null && jid !== '' ? Number(jid) : null;
                }
                return j.id != null && j.id !== '' ? Number(j.id) : null;
            },

            /** Resolves job_orders.id from store order_id when job_order_id was missing (API limit / older rows). */
            async resolveEffectiveJobId() {
                let jid = this.effectiveJobId();
                if (jid != null && !Number.isNaN(jid) && jid > 0) return jid;
                const j = this.currentJo;
                if (!j || j.order_type !== 'ORDER') return null;
                const oid = j.order_id ?? j.id;
                if (oid == null || oid === '') return null;
                try {
                    const res = await (await fetch(`../admin/job_orders_api.php?action=resolve_job_for_order&order_id=${encodeURIComponent(oid)}`)).json();
                    if (res.success && res.job_id) {
                        this.currentJo.job_order_id = res.job_id;
                        await this.loadOrders();
                        return Number(res.job_id);
                    }
                } catch (e) {
                    console.error('resolve_job_for_order', e);
                }
                return null;
            },

            findOrder(id, orderType = 'JOB') {
                return this.orders.find(o => this.sameId(o.id, id) && (o.order_type || 'JOB') === (orderType || 'JOB'));
            },

            getCorrectServiceType(jo) {
                const combined = ((jo.job_title || '') + ' ' + (jo.service_type || '')).toUpperCase();
                if (combined.includes('T-SHIRT') || combined.includes('TSHIRT') || combined.includes('T SHIRT')) return 'T-SHIRT PRINTING';
                if (combined.includes('TARPAULIN')) return 'TARPAULIN PRINTING';
                if (combined.includes('TRANSPARENT STICKER') || combined.includes('TRANSPARENT')) return 'TRANSPARENT STICKER PRINTING';
                if (combined.includes('STICKER') || combined.includes('DECAL')) return 'DECALS/STICKERS (PRINT/CUT)';
                if (combined.includes('SINTRA')) return 'SINTRA BOARD';
                if (combined.includes('SOUVENIR')) return 'SOUVENIRS';
                if (combined.includes('REFLECTORIZED')) return 'REFLECTORIZED SIGNAGE';
                return 'OTHER';
            },

            get activeFilterCount() {
                let count = 0;
                if (this.activeStatus !== 'ALL') count++;
                if (this.serviceFilter !== 'ALL') count++;
                if (this.dateFilter !== 'ALL') count++;
                return count;
            },

            get activeSortLabel() {
                const labels = {
                    'newest': 'Newest First',
                    'oldest': 'Oldest First',
                    'name_asc': 'Customer (A-Z)',
                    'name_desc': 'Customer (Z-A)'
                };
                return labels[this.activeSort] || 'Sort by';
            },

            applySort(sort) {
                this.activeSort = sort;
                this.sortOpen = false;
            },

            resetFilters() {
                this.activeStatus = 'ALL';
                this.serviceFilter = 'ALL';
                this.dateFilter = 'ALL';
                this.customDateFrom = '';
                this.customDateTo = '';
                this.filterOpen = false;
            },

            get filteredOrders() {
                const lowerSearch = this.search.toLowerCase();
                const filtered = this.orders.filter(jo => {
                    // Search Bar
                    if (lowerSearch) {
                        const idStr = (jo.order_type === 'ORDER' ? '#ORD-' : (jo.order_type === 'SERVICE' ? '#SRV-' : '#JO-')) + jo.id.toString().padStart(5, '0');
                        const matchSearch = 
                            idStr.toLowerCase().includes(lowerSearch) ||
                            (jo.job_title && jo.job_title.toLowerCase().includes(lowerSearch)) ||
                            (jo.service_type && jo.service_type.toLowerCase().includes(lowerSearch)) ||
                            (((jo.first_name || '') + ' ' + (jo.last_name || '')).toLowerCase().includes(lowerSearch)) ||
                            (jo.id && jo.id.toString().includes(lowerSearch));
                        if (!matchSearch) return false;
                    }

                    // Status Filter
                    let matchStatus = false;
                    if (this.activeStatus === 'ALL') {
                        matchStatus = true;
                    } else if (this.activeStatus === 'TO_VERIFY') {
                        matchStatus = this.isVerifyStageRow(jo);
                    } else if (this.activeStatus === 'TO_PAY') {
                        matchStatus = this.isToPayRow(jo);
                    } else if (this.activeStatus === 'IN_PRODUCTION') {
                        matchStatus = this.isInProductionRow(jo);
                    } else {
                        matchStatus = jo.status === this.activeStatus;
                    }
                    if (!matchStatus) return false;

                    // Service Filter
                    if (this.serviceFilter !== 'ALL') {
                        const rowService = this.getCorrectServiceType(jo);
                        if (rowService !== this.serviceFilter) return false;
                    }

                    // Date Filter
                    if (this.dateFilter !== 'ALL') {
                        const orderDate = new Date(jo.created_at || jo.order_date);
                        const now = new Date();
                        
                        if (this.dateFilter === 'TODAY') {
                            if (orderDate.toDateString() !== now.toDateString()) return false;
                        } else if (this.dateFilter === 'WEEK') {
                            const lastWeek = new Date();
                            lastWeek.setDate(now.getDate() - 7);
                            if (orderDate < lastWeek) return false;
                        } else if (this.dateFilter === 'MONTH') {
                            if (orderDate.getMonth() !== now.getMonth() || orderDate.getFullYear() !== now.getFullYear()) return false;
                        } else if (this.dateFilter === 'CUSTOM') {
                            if (this.customDateFrom) {
                                const from = new Date(this.customDateFrom);
                                from.setHours(0,0,0,0);
                                if (orderDate < from) return false;
                            }
                            if (this.customDateTo) {
                                const to = new Date(this.customDateTo);
                                to.setHours(23,59,59,999);
                                if (orderDate > to) return false;
                            }
                        }
                    }
                    
                    return true;
                });

                // Sorting
                return filtered.sort((a, b) => {
                    if (this.activeSort === 'newest') return (b._ts || 0) - (a._ts || 0);
                    if (this.activeSort === 'oldest') return (a._ts || 0) - (b._ts || 0);
                    
                    const nameA = ((a.first_name || '') + ' ' + (a.last_name || '')).toLowerCase();
                    const nameB = ((b.first_name || '') + ' ' + (b.last_name || '')).toLowerCase();
                    if (this.activeSort === 'name_asc') return nameA.localeCompare(nameB);
                    if (this.activeSort === 'name_desc') return nameB.localeCompare(nameA);
                    
                    return 0;
                });
            },

            get paginatedOrders() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return this.filteredOrders.slice(start, end);
            },

            get totalPages() {
                return Math.ceil(this.filteredOrders.length / this.itemsPerPage);
            },

            get pageNumbers() {
                const total = this.totalPages;
                if (total <= 1) return [];
                let pages = [1];
                const window = 2;
                for (let i = Math.max(2, this.currentPage - window); i <= Math.min(total - 1, this.currentPage + window); i++) {
                    pages.push(i);
                }
                if (total > 1 && !pages.includes(total)) pages.push(total);
                
                let uniquePages = [...new Set(pages)].sort((a,b) => a - b);
                let finalPages = [];
                let prev = null;
                for (let p of uniquePages) {
                    if (prev && p - prev > 1) finalPages.push('...');
                    finalPages.push(p);
                    prev = p;
                }
                return finalPages;
            },

            getStatusCount(status) {
                if (status === 'ALL') return this.orders.length;
                if (status === 'TO_VERIFY') {
                    return this.orders.filter(o => this.isVerifyStageRow(o)).length;
                }
                if (status === 'TO_PAY') {
                    return this.orders.filter(o => this.isToPayRow(o)).length;
                }
                if (status === 'IN_PRODUCTION') {
                    return this.orders.filter(o => this.isInProductionRow(o)).length;
                }
                return this.orders.filter(o => o.status === status).length;
            },

            async viewDetails(id, orderType = 'JOB') {
                let order = this.findOrder(id, orderType);
                if (orderType === 'SERVICE' || order?.order_type === 'SERVICE') {
                    window.location.href = `service_orders.php?open_id=${id}`;
                    return;
                }

                this.showDetailsModal = true;
                this.loadingDetails = true;
                this.currentJo = {};
                const base = document.body.getAttribute('data-base-url') || '/printflow';
                
                if (orderType === 'ORDER') {
                    // Always fetch full order details to get `items` array and dynamic fields
                    try {
                        const detailRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_regular_order&id=${id}`)).json();
                        if (detailRes.success) {
                            order = detailRes.data;
                        }
                    } catch (e) { console.error('Error fetching order detail:', e); }
                    
                    if (!order || !order.items) {
                        this.loadingDetails = false;
                        this.showDetailsModal = false;
                        this.showStaffAlert('Not Found', 'Order not found or not accessible.');
                        return;
                    }
                    this.currentJo = { ...order, order_type: 'ORDER' };
                    this.jobPriceInput = this.currentJo.estimated_total || 0;
                    if (!this.currentJo.job_order_id) {
                        await this.resolveEffectiveJobId();
                    }
                    this.loadingDetails = false;
                } else {
                    // JOB ORDER
                    const jid = id || (order ? order.id : null);
                    if (!jid) {
                        this.loadingDetails = false;
                        this.showDetailsModal = false;
                        return;
                    }

                    try {
                        const res = await (await fetch(`${base}/admin/job_orders_api.php?action=get_order&id=${jid}`)).json();
                        if (res.success) {
                            this.currentJo = { ...res.data, order_type: 'JOB' };
                            this.jobPriceInput = this.currentJo.estimated_total || 0;
                            this.resetMaterialForm();
                            this.resetInkForm();
                            for (const m of this.currentJo.materials || []) {
                                if (m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                            }
                        } else {
                            // Fallback: It might be a regular order ID passed with job_type=JOB
                            const fallbackRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_regular_order&id=${jid}`)).json();
                            if (fallbackRes.success) {
                                this.currentJo = { ...fallbackRes.data, order_type: 'ORDER' };
                                this.jobPriceInput = this.currentJo.estimated_total || 0;
                                if (!this.currentJo.job_order_id) {
                                    await this.resolveEffectiveJobId();
                                }
                            } else {
                                this.showStaffAlert('Error', 'Order details could not be loaded.');
                                this.showDetailsModal = false;
                            }
                        }
                    } catch (e) {
                        console.error('Error loading job details', e);
                        this.showDetailsModal = false;
                    }
                    this.loadingDetails = false;
                }
            },

            isOverdue(date) {
                if(!date) return false;
                return new Date(date) < new Date() && this.activeStatus !== 'COMPLETED' && this.activeStatus !== 'CANCELLED';
            },

            async loadAvailableRolls(itemId) {
                if(this.availableRolls[itemId]) {
                    this.availableRollsList = this.availableRolls[itemId];
                    return;
                }
                const res = await (await fetch(`../admin/inventory_rolls_api.php?action=list_rolls&item_id=${itemId}`)).json();
                if(res.success) {
                    this.availableRolls[itemId] = res.data;
                    this.availableRollsList = res.data;
                }
            },

            isRollTracked(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.track_by_roll == 1;
            },

            async assignRoll(jomId, rollId) {
                const fd = new FormData();
                fd.append('action', 'assign_roll');
                fd.append('jom_id', jomId);
                fd.append('roll_id', rollId);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.refreshMaterials();
                } else {
                    this.showStaffAlert('Error', res.error);
                }
            },

            async jobAction(status, machineId = null) {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Production Job Error', 'Could not create or find a production job for this store order. Confirm the order has line items in Orders.');
                    return;
                }
                const ok = await this.updateStatus(jid, status, machineId);
                if (ok) this.showDetailsModal = false;
            },

            async updateStatus(id, status, machineId = null, reason = '') {
                if (id == null || id === '' || Number(id) <= 0) {
                    this.showStaffAlert('Error', 'Invalid job order id.');
                    return false;
                }
                const fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('id', id);
                fd.append('status', status);
                if(machineId) fd.append('machine_id', machineId);
                if(reason) fd.append('reason', reason);
                
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    if (this.showDetailsModal && (this.sameId(this.effectiveJobId(), id) || this.sameId(this.currentJo.id, id))) {
                        await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    }
                    return true;
                }
                this.showStaffAlert('Update Failed', res.error);
                return false;
            },

            async parseJsonResponse(r) {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Non-JSON response', text.slice(0, 500));
                    return { success: false, error: 'Server returned an invalid response. Check console or PHP error log.' };
                }
            },

            async verifyPayment() {
                this.showStaffConfirm(
                    'Verify Payment',
                    `Verify payment of ₱${this.currentJo.payment_submitted_amount}?`,
                    async () => {
                        const base = document.body.getAttribute('data-base-url') || '/printflow';
                        const ot = this.currentJo.order_type || 'JOB';
                        let res;

                        if (ot === 'ORDER') {
                            const oid = this.currentJo.order_id || this.currentJo.id;
                            const fd = new FormData();
                            fd.append('order_id', oid);
                            fd.append('action', 'Approve');
                            const r = await fetch(base + '/staff/api_verify_payment.php', { method: 'POST', body: fd });
                            res = await this.parseJsonResponse(r);
                        } else {
                            const jid = await this.resolveEffectiveJobId();
                            if (!jid) {
                                this.showStaffAlert('Error', 'No linked production job for payment verification.');
                                return;
                            }
                            const fd = new FormData();
                            fd.append('action', 'verify_payment');
                            fd.append('id', jid);
                            const r = await fetch(base + '/admin/api_verify_job_payment.php', { method: 'POST', body: fd });
                            res = await this.parseJsonResponse(r);
                        }

                        if(res.success) {
                            this.activeStatus = 'IN_PRODUCTION';
                            await this.loadOrders();
                            await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                            this.showStaffAlert('Success', 'Payment verified and balance updated.');
                        } else {
                            this.showStaffAlert('Verification Failed', res.error || 'Verification failed.');
                        }
                    }
                );
            },

            openRejectPaymentModal() {
                this.rejectPaymentReasonSelect = '';
                this.rejectPaymentReasonText = '';
                this.showRejectPaymentModal = true;
            },

            closeRejectPaymentModal() {
                this.showRejectPaymentModal = false;
            },

            async submitRejectPayment() {
                const finalReason = this.rejectPaymentReasonSelect === 'Others' ? this.rejectPaymentReasonText : this.rejectPaymentReasonSelect;
                if (!finalReason) {
                    this.showStaffAlert('Input Required', 'Please select or specify a reason.');
                    return;
                }
                await this.rejectPayment(finalReason);
                this.closeRejectPaymentModal();
            },

            async rejectPayment(reasonOverride = null) {
                let reason = reasonOverride;
                if (!reason) {
                    reason = prompt("Enter reason for rejection (e.g., Unclear image, Incorrect amount):");
                }
                if(!reason) return;

                const base = document.body.getAttribute('data-base-url') || '/printflow';
                const ot = this.currentJo.order_type || 'JOB';
                let res;

                if (ot === 'ORDER') {
                    const oid = this.currentJo.order_id || this.currentJo.id;
                    const fd = new FormData();
                    fd.append('order_id', oid);
                    fd.append('action', 'Reject');
                    fd.append('reason', reason);
                    const r = await fetch(base + '/staff/api_verify_payment.php', { method: 'POST', body: fd });
                    res = await this.parseJsonResponse(r);
                } else {
                    const jid = await this.resolveEffectiveJobId();
                    if (!jid) {
                        this.showStaffAlert('Error', 'No linked production job.');
                        return;
                    }
                    const fd = new FormData();
                    fd.append('action', 'reject_payment');
                    fd.append('id', jid);
                    fd.append('reason', reason);
                    const r = await fetch(base + '/admin/api_verify_job_payment.php', { method: 'POST', body: fd });
                    res = await this.parseJsonResponse(r);
                }

                if(res.success) {
                    await this.loadOrders();
                    await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    this.showStaffAlert('Success', 'Payment proof rejected.');
                } else {
                    this.showStaffAlert('Rejection Failed', res.error || 'Rejection failed.');
                }
            },

            async setJobPrice(id) {
                if(this.jobPriceInput < 0) return;
                let jid = id != null ? id : await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Error', 'No linked production job.');
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', jid);
                fd.append('price', this.jobPriceInput);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(!res.success) {
                    this.showStaffAlert('Error', res.error);
                    throw new Error(res.error);
                }
            },

            addMaterialToQueue() {
                if (!this.newMaterialId) return;
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                if (!item) return;
                let meta = {};
                if (this.isTarpaulin(this.newMaterialId)) {
                    meta.height_ft = this.newMaterialHeight;
                    meta.finishing = this.newMaterialMetadata.finishing || '';
                } else if (this.isSticker(this.newMaterialId)) {
                    meta.lamination = this.newMaterialMetadata.lamination || '';
                }
                this.pendingMaterials.push({
                    item_id: this.newMaterialId,
                    name: item.name,
                    qty: this.newMaterialQty,
                    uom: this.isSticker(this.newMaterialId) ? 'pcs' : (item.unit_of_measure || 'pcs'),
                    roll_id: this.newMaterialRollId || '',
                    notes: this.newMaterialNotes,
                    metadata: meta
                });
                // Reset form
                this.newMaterialId = '';
                this.newMaterialQty = 1;
                this.newMaterialHeight = 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.newMaterialMetadata = {};
            },

            async submitToPay() {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Error', 'No linked production job for materials and pricing.');
                    return;
                }
                // Save all pending materials from the queue
                for (const pm of this.pendingMaterials) {
                    const fd = new FormData();
                    fd.append('action', 'add_material');
                    fd.append('order_id', jid);
                    fd.append('item_id', pm.item_id);
                    fd.append('quantity', pm.qty);
                    fd.append('uom', pm.uom);
                    fd.append('roll_id', pm.roll_id);
                    fd.append('notes', pm.notes);
                    fd.append('metadata', JSON.stringify(pm.metadata));
                    const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                    if (!res.success) { this.showStaffAlert('Material Error', 'Failed to save material: ' + res.error); return; }
                }
                this.pendingMaterials = [];

                // Also save the current form if something is still selected
                if (this.newMaterialId) {
                    await this.addMaterial();
                }

                // Handle Ink Usage Check and Submission
                if (this.useInk && this.inkCategorySelected) {
                    const mappedInks = this.inkTypes[this.inkCategorySelected];
                    const inkPayload = [];
                    
                    if (this.inkBlue > 0) inkPayload.push({ item_id: mappedInks['BLUE'], color: 'BLUE', quantity: this.inkBlue });
                    if (this.inkRed > 0) inkPayload.push({ item_id: mappedInks['RED'], color: 'RED', quantity: this.inkRed });
                    if (this.inkBlack > 0) inkPayload.push({ item_id: mappedInks['BLACK'], color: 'BLACK', quantity: this.inkBlack });
                    if (this.inkYellow > 0) inkPayload.push({ item_id: mappedInks['YELLOW'], color: 'YELLOW', quantity: this.inkYellow });

                    if (inkPayload.length > 0) {
                        const fdInk = new FormData();
                        fdInk.append('action', 'save_ink_usage');
                        fdInk.append('order_id', jid);
                        fdInk.append('ink_data', JSON.stringify(inkPayload));

                        const resInk = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fdInk })).json();
                        if (!resInk.success) {
                            this.showStaffAlert('Ink Error', 'Failed to save ink usage: ' + resInk.error);
                            return;
                        }
                    }
                }

                // Re-fetch to get latest materials
                await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');

                if ((!this.currentJo.materials || this.currentJo.materials.length === 0) && (!this.currentJo.ink_usage || this.currentJo.ink_usage.length === 0)) {
                    this.showStaffAlert('Production Required', 'Please add at least one production material or ink before submitting to pay.');
                    return;
                }

                await this.setJobPrice(jid);
                await this.updateStatus(jid, 'TO_PAY');
            },

            async loadAllInventoryItems() {
                const res = await (await fetch('../admin/inventory_items_api.php?action=get_items&active_only=1')).json();
                if(res.success) {
                    // Drop roll cache so any newly issued/received roll deductions become visible.
                    this.availableRolls = {};
                    this.allInventoryItems = res.data;
                    this.laminationItemsList = this.allInventoryItems.filter(i => i.name.toUpperCase().includes('LAMINATE'));
                }
            },

            async loadAvailableLamRolls(itemId) {
                if(!itemId) return;
                const res = await (await fetch(`../admin/inventory_rolls_api.php?action=list&item_id=${itemId}&status=OPEN`)).json();
                if(res.success) {
                    this.availableLamRollsList = res.data;
                }
            },

            async approveOrder() {
                this.showStaffConfirm(
                    'Approve Order',
                    'Approve this order and request payment?',
                    async () => {
                        const id = this.currentJo.id;
                        const jid = this.effectiveJobId();
                        const oid = this.currentJo.order_id || this.currentJo.id;
                        
                        // Set price first
                        if (parseFloat(this.jobPriceInput) > 0) {
                            await this.updatePrice();
                        }

                        if (this.currentJo.order_type === 'ORDER') {
                            const fd = new FormData();
                            fd.append('order_id', oid);
                            fd.append('status', 'To Pay');
                            fd.append('update_status', '1');
                            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
                            
                            const res = await (await fetch('orders.php', { 
                                method: 'POST', 
                                body: fd, 
                                headers: {'X-Requested-With': 'XMLHttpRequest'} 
                            })).json();

                            if (res.success) {
                                this.showStaffAlert('Success', 'Order approved and moved to To Pay!');
                                await this.loadOrders();
                                this.showDetailsModal = false;
                            } else {
                                this.showStaffAlert('Error', 'Error: ' + (res.error || 'Failed to update order status'));
                            }
                        } else {
                            // Custom Job Order
                            await this.updateStatus(id, 'TO_PAY');
                        }
                    }
                );
            },

            async updatePrice() {
                const jid = this.effectiveJobId();
                const oid = this.currentJo.order_id || this.currentJo.id;
                const price = parseFloat(this.jobPriceInput);
                
                if (this.currentJo.order_type === 'ORDER') {
                   const fd = new FormData();
                   fd.append('action', 'update_order_price');
                   fd.append('order_id', oid);
                   fd.append('price', price);
                   const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                   if (!res.success) this.showStaffAlert('Error', 'Failed to update price: ' + res.error);
                   else {
                       this.currentJo.total_amount = price;
                       this.currentJo.estimated_total = price;
                       console.log('Price updated successfully');
                   }
                } else {
                    await this.setJobPrice(jid);
                }
            },

            async setJobPrice(jid) {
                if (!jid) return;
                const price = parseFloat(this.jobPriceInput);
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', jid);
                fd.append('price', price);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if (res.success) {
                    this.currentJo.estimated_total = price;
                } else {
                    this.showStaffAlert('Error', 'Error setting price: ' + res.error);
                }
            },

            openRevisionModal() {
                this.revisionReasonSelect = '';
                this.revisionReasonText = '';
                this.showRevisionModal = true;
            },

            closeRevisionModal() {
                this.showRevisionModal = false;
            },

            async submitRevision() {
                const oid = this.effectiveJobId();
                if (!oid) return;
                
                let finalReason = this.revisionReasonSelect;
                if (finalReason === 'Others' || !finalReason) {
                    finalReason = this.revisionReasonText.trim();
                }
                
                if (!finalReason) {
                    this.showStaffAlert('Input Required', 'Please select or specify a reason for the revision request.');
                    return;
                }

                this.showStaffConfirm(
                    'Request Revision',
                    `Submit revision request?\nReason: ${finalReason}`,
                    async () => {
                        const ok = await this.updateStatus(oid, 'For Revision', null, finalReason);
                        if (ok) {
                            this.showStaffAlert('Success', 'Revision requested successfully.');
                            this.showRevisionModal = false;
                            this.showDetailsModal = false; 
                        }
                    }
                );
            },

            async addMaterial() {
                if(!this.newMaterialId || !this.newMaterialQty) return;
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    this.showStaffAlert('Error', 'No linked production job.');
                    return;
                }
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                const fd = new FormData();
                fd.append('action', 'add_material');
                fd.append('order_id', jid);
                fd.append('item_id', this.newMaterialId);
                fd.append('quantity', this.newMaterialQty);
                fd.append('uom', this.isSticker(this.newMaterialId) ? 'pcs' : (item.unit_of_measure || 'pcs'));
                fd.append('roll_id', this.newMaterialRollId);
                fd.append('notes', this.newMaterialNotes);
                
                // Construct metadata based on category
                let meta = {};
                if (this.isTarpaulin(this.newMaterialId)) {
                    meta.height_ft = this.newMaterialHeight;
                    meta.finishing = this.newMaterialMetadata.finishing || '';
                } else if (this.isSticker(this.newMaterialId)) {
                    // STICKER LOGIC
                    let orderedHeight = this.currentJo.height_ft > 0 ? this.currentJo.height_ft : 1;
                    meta.waste_length_ft = Math.max(0, this.newMaterialQty - orderedHeight);
                    if (this.newMaterialMetadata.lamination) {
                        meta.lamination_item_id = this.newMaterialMetadata.lamination;
                        meta.lamination_roll_id = this.newMaterialMetadata.lamination_roll_id || null;
                        meta.lamination_length_ft = this.newMaterialQty; // Lamination length matches consumed vinyl length
                    }
                }
                fd.append('metadata', JSON.stringify(meta));

                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    this.resetMaterialForm();
                    await this.refreshMaterials();
                } else {
                    this.showStaffAlert('Error', res.error);
                }
            },

            resetMaterialForm() {
                this.newMaterialId = '';
                this.newMaterialQty = 1;
                this.newMaterialHeight = 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.materialSearch = '';
                this.availableLamRollsList = [];
                this.newMaterialMetadata = {
                    lamination: '',
                    lamination_roll_id: ''
                };
            },

            resetInkForm() {
                this.inkCategorySelected = '';
                this.inkBlue = '';
                this.inkRed = '';
                this.inkBlack = '';
                this.inkYellow = '';
                this.useInk = false;
            },

            isTarpaulin(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 2; // confirmed from schema check
            },

            isSticker(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 3;
            },

            isPlate(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 1;
            },

            async removeMaterial(jomId) {
                this.showStaffConfirm(
                    'Remove Material',
                    'Remove this material?',
                    async () => {
                        const fd = new FormData();
                        fd.append('action', 'remove_material');
                        fd.append('id', jomId);
                        const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                        if(res.success) {
                            await this.refreshMaterials();
                        } else {
                            this.showStaffAlert('Error', res.error);
                        }
                    }
                );
            },

            async refreshMaterials() {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) return;
                const res = await (await fetch(`../admin/job_orders_api.php?action=get_order&id=${jid}`)).json();
                if(res.success) {
                    this.currentJo = { ...res.data, order_type: 'JOB' };
                    for(const m of (this.currentJo.materials || [])) {
                        if(m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                    }
                }
            },

            async completeOrder(machineId = null) {
                this.showStaffConfirm(
                    'Complete Order',
                    'This will permanently deduct materials from inventory. Proceed?',
                    async () => {
                        const jid = await this.resolveEffectiveJobId();
                        if (!jid) {
                            this.showStaffAlert('Error', 'No linked production job for this entry.');
                            return;
                        }
                        const ok = await this.updateStatus(jid, 'COMPLETED', machineId);
                        if (ok) {
                            await this.loadAllInventoryItems();
                            this.availableRolls = {};
                            this.showDetailsModal = false;
                        }
                    }
                );
            }
        }
    }
    /*
     * Do NOT call Alpine.initTree here when document.readyState !== 'loading' (Turbo body swap).
     * Inline scripts run before turbo:load's setTimeout; initTree(root) + initTree(.main-content) double-mounts x-for (tripled tabs, zero counts).
     * Full load: Alpine.start() (defer) inits the page. Turbo: public/assets/js/turbo-init.js initTree(.main-content) runs after swap.
     */
</script>
</body>
</html>
