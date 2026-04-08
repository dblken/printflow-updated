<?php
/**
 * Staff Reports - Visual Analytics Dashboard
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$branch_ctx = init_branch_context(false);
$staffBranchId = (int)$branch_ctx['selected_branch_id'];
$branchName = $branch_ctx['branch_name'];
$range = $_GET['range'] ?? 'today';
$report_date = $_GET['date'] ?? date('Y-m-d');

// Define parameters based on the selected range
if ($range === 'week') {
    $interval_label = 'This Week';
    $group_by = "DATE(order_date)";
    $sql_interval = '6 DAY';
    $sql_condition = "YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($range === 'month') {
    $interval_label = 'This Month';
    $group_by = "DATE(order_date)";
    $sql_interval = '29 DAY';
    $sql_condition = "YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE())";
} else {
    $range = 'today';
    $interval_label = 'Today';
    $group_by = "DATE(order_date)";
    $sql_interval = '0 DAY';
    $sql_condition = "DATE(order_date) = CURDATE()";
}

$status_filter = $_GET['status'] ?? 'ALL';
$status_where = "";
$status_p = [];
$status_t = "";
if ($status_filter !== 'ALL' && !empty($status_filter)) {
    $status_where = " AND status = ? ";
    $status_p = [$status_filter];
    $status_t = "s";
}

// ---- 1. RANGE-AWARE KPI METRICS (DYNAMIC) ----
// Total revenue for THE SELECTED PERIOD (Paid only)
$rev_res = db_query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE $sql_condition AND payment_status = 'Paid' AND branch_id = ? $status_where", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));
$period_revenue = $rev_res[0]['total'] ?? 0;

// Total orders count for THE SELECTED PERIOD
$ord_res = db_query("SELECT COUNT(*) as count FROM orders WHERE $sql_condition AND branch_id = ? $status_where", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));
$period_orders = $ord_res[0]['count'] ?? 0;

// Pending/Active orders received in THE SELECTED PERIOD (if status is not filtered specifically)
$active_statuses_sql = "status IN ('Pending', 'Pending Review', 'Pending Verification', 'Approved', 'Downpayment Submitted', 'In Production')";
if ($status_filter !== 'ALL') {
    $pend_res = db_query("SELECT COUNT(*) as count FROM orders WHERE status = ? AND branch_id = ? AND $sql_condition", 'si', [$status_filter, $staffBranchId]);
} else {
    $pend_res = db_query("SELECT COUNT(*) as count FROM orders WHERE $active_statuses_sql AND branch_id = ? AND $sql_condition", 'i', [$staffBranchId]);
}
$pending_period_orders = $pend_res[0]['count'] ?? 0;

// GLOBAL Backlog (All pending/active orders ever)
$global_back_res = db_query("SELECT COUNT(*) as count FROM orders WHERE status NOT IN ('Completed', 'Cancelled') AND branch_id = ? $status_where", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));
$global_backlog = $global_back_res[0]['count'] ?? 0;

// Low stock finished goods alert (This is ALWAYS current status)
$stock_res = db_query("SELECT COUNT(*) as count FROM products WHERE status = 'Activated' AND stock_quantity < 20");
$low_stock_count = $stock_res[0]['count'] ?? 0;

// ---- 2. REVENUE TREND (DYNAMIC) ----
$trend_res = db_query("
    SELECT $group_by as dte, COALESCE(SUM(total_amount), 0) as daily_total 
    FROM orders 
    WHERE $sql_condition AND branch_id = ?
    $status_where
    GROUP BY dte
    ORDER BY dte ASC
", ($status_t ? "i" . $status_t : "i"), array_merge([$staffBranchId], $status_p));

// Fill empty data points so the chart doesn't break
$trend_dates = [];
$trend_totals = [];

if ($range === 'month') {
    // Get days in current month
    $days_in_month = date('t');
    for ($i = 1; $i <= $days_in_month; $i++) {
        $date_str = date('Y-m-') . str_pad($i, 2, '0', STR_PAD_LEFT);
        $trend_dates[] = date('M d', strtotime($date_str));
        
        $found = 0;
        foreach ($trend_res as $r) { if ($r['dte'] === $date_str) { $found = (float)$r['daily_total']; break; } }
        $trend_totals[] = $found;
    }
} elseif ($range === 'week') {
    // Get days of current week (Monday to Sunday)
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    for ($i = 0; $i < 7; $i++) {
        $date_str = date('Y-m-d', strtotime($start_of_week . " +$i days"));
        $trend_dates[] = date('D', strtotime($date_str));
        
        $found = 0;
        foreach ($trend_res as $r) { if ($r['dte'] === $date_str) { $found = (float)$r['daily_total']; break; } }
        $trend_totals[] = $found;
    }
} else {
    // Today - show hourly breakdown
    $date_str = date('Y-m-d');
    $trend_dates[] = 'Today';
    $found = 0;
    foreach ($trend_res as $r) { if ($r['dte'] === $date_str) { $found = (float)$r['daily_total']; break; } }
    $trend_totals[] = $found;
}

// ---- 3. ORDER STATUS DISTRIBUTION (FIXED LABELS) ----
$std_statuses = [
    'Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled',
    'Pending Review', 'Approved', 'Downpayment Submitted', 'To Pay'
];

$status_res = db_query("
    SELECT status, COUNT(*) as status_count 
    FROM orders 
    WHERE $sql_condition AND branch_id = ?
    GROUP BY status
", 'i', [$staffBranchId]);

$status_map = [];
foreach ($status_res as $s) {
    if ($s['status']) $status_map[$s['status']] = (int)$s['status_count'];
}

$status_labels = $std_statuses;
$status_counts = array_map(fn($s) => $status_map[$s] ?? 0, $std_statuses);

// Use 'No Data Yet' only if EVERYTHING is zero across the period
if (array_sum($status_counts) === 0) {
    $status_labels = ['No Data Yet'];
    $status_counts = [1];
}

// ---- 4. TOP 5 BEST SELLING PRODUCTS (DYNAMIC) ----
$top_products = db_query("
    SELECT p.name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE $sql_condition AND o.branch_id = ?
    GROUP BY oi.product_id
    ORDER BY total_sold DESC
    LIMIT 5
", 'i', [$staffBranchId]);

$page_title = 'Visual Reports & Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .kpi-row { margin-bottom: 24px; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* 2/3 width for primary chart, 1/3 for secondary */
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } }

        /* Split Export Button Styles */
        .btn-excel-split { transition: transform 0.2s; }
        .btn-excel-split:hover { transform: translateY(-1px); }
        .btn-excel-split:hover span:first-child { background: #1f2937 !important; }
        .btn-excel-split:hover span:last-child { background: #058f8f !important; }
        
        .chart-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-title { font-size: 16px; font-weight: 800; color: #0f172a; }
        .chart-subtitle { font-size: 13px; color: #64748b; font-weight: 600; }
        
        .chart-container-large { position: relative; height: 350px; width: 100%; }
        .chart-container-small { position: relative; height: 380px; width: 100%; padding-bottom: 20px; }

        /* Top Products List Styling */
        .top-product-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .top-product-row:last-child { border-bottom: none; }
        .tp-name { font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .tp-rank { width: 24px; height: 24px; background: #f1f5f9; color: #475569; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; }
        .tp-sold { font-size: 14px; font-weight: 800; color: #059669; }

        /* Filter Standard CSS */
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 16px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            height: 38px;
        }
        .toolbar-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .toolbar-btn.active { background: #f0fdfa; border-color: #0d9488; color: #0d9488; }
        
        .filter-panel {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 280px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            z-index: 200;
            overflow: hidden;
            text-align: left;
        }
        .filter-panel-header { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #1e293b; font-size: 14px; }
        .filter-section { padding: 18px; border-bottom: 1px solid #f1f5f9; }
        .filter-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .filter-section-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-reset-link { font-size: 11px; color: #0d9488; font-weight: 700; border: none; background: none; cursor: pointer; padding: 0; }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-select-v2 { width: 100%; height: 38px; padding: 0 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; color: #1e293b; outline: none; transition: border-color 0.2s; background: #fff; }
        .filter-select-v2:focus { border-color: #0d9488; }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            padding: 14px 18px;
            border-top: 1px solid #f3f4f6;
        }
        .filter-btn-reset {
            flex: 1;
            height: 40px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 400;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .filter-btn-reset:hover { background: #f9fafb; border-color: #d1d5db; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title">Visual Reports & Analytics</h1>
                <p class="page-subtitle">A quick overview of business performance and metrics</p>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end; position:relative;" x-data="{ filterOpen: false, status: '<?php echo $status_filter; ?>', range: '<?php echo $range; ?>' }">
                <!-- Filter Button -->
                <div style="position:relative;">
                    <button class="toolbar-btn" :class="{ active: filterOpen || status !== 'ALL' || range !== 'today' }" @click="filterOpen = !filterOpen" style="height:38px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        Filters
                        <span class="filter-badge" x-show="status !== 'ALL' || range !== 'today'" x-text="(status !== 'ALL' ? 1 : 0) + (range !== 'today' ? 1 : 0)"></span>
                    </button>
                    <!-- Filter Panel -->
                    <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                        <div class="filter-panel-header">Refine Reports</div>
                        
                        <!-- Status Section -->
                        <div class="filter-section">
                            <div class="filter-section-head">
                                <span class="filter-section-label">Status</span>
                                <button class="filter-reset-link" @click="status = 'ALL'; window.location.href='?range='+range+'&status=ALL'">Reset</button>
                            </div>
                            <select class="filter-select-v2" x-model="status" @change="window.location.href='?range='+range+'&status='+status">
                                <option value="ALL">All Statuses</option>
                                <?php 
                                $all_opts = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled', 'Pending Review', 'Approved', 'Downpayment Submitted', 'To Pay'];
                                foreach($all_opts as $opt): ?>
                                    <option value="<?php echo $opt; ?>"><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Range Section -->
                        <div class="filter-section">
                            <div class="filter-section-head">
                                <span class="filter-section-label">Date Range</span>
                                <button class="filter-reset-link" @click="range = 'today'; window.location.href='?status='+status+'&range=today'">Reset</button>
                            </div>
                            <select class="filter-select-v2" x-model="range" @change="window.location.href='?status='+status+'&range='+range">
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button class="filter-btn-reset" @click="window.location.href='reports.php'">Reset all filters</button>
                        </div>
                    </div>
                </div>

                <!-- Export Button -->
                <a href="export_reports.php?range=<?php echo $range; ?>&status=<?php echo $_GET['status'] ?? 'ALL'; ?>" 
                   style="height: 38px; display: inline-flex; align-items: stretch; border-radius: 8px; overflow: hidden; text-decoration: none; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); border: 1px solid #374151; flex-shrink: 0;"
                   class="btn-excel-split">
                   <span style="background: #374151; color: #fff; padding: 0 14px; font-size: 11px; font-weight: 900; display: flex; align-items: center; letter-spacing: 0.1em;">EXPORT</span>
                   <span style="background: #06A1A1; padding: 0 10px; display: flex; align-items: center; justify-content: center; border-left: 1px solid rgba(255,255,255,0.1);">
                       <svg width="14" height="14" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                           <path d="M12 5v14M19 12l-7 7-7-7"></path>
                       </svg>
                   </span>
                </a>
            </div>
        </header>

        <main>
            <!-- ROW 1: QUICK PERFORMANCE METRICS -->
            <div class="kpi-row">
                <div class="kpi-card emerald">
                    <div class="kpi-label"><?php echo $interval_label; ?> Revenue</div>
                    <div class="kpi-value"><?php echo format_currency($period_revenue); ?></div>
                    <div class="kpi-sub">Paid orders only</div>
                </div>
                <div class="kpi-card indigo">
                    <div class="kpi-label"><?php echo $interval_label; ?> Orders</div>
                    <div class="kpi-value"><?php echo number_format($period_orders); ?></div>
                    <div class="kpi-sub">Customer transactions</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Active Jobs</div>
                    <div class="kpi-value"><?php echo number_format($pending_period_orders); ?></div>
                    <div class="kpi-sub"><?php echo number_format($global_backlog); ?> total backlog</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Low Stock</div>
                    <div class="kpi-value"><?php echo number_format($low_stock_count); ?></div>
                    <div class="kpi-sub">Items needing restock</div>
                </div>
            </div>

            <!-- ROW 2: VISUAL CHARTS -->
            <div class="dashboard-grid">
                
                <!-- Main Line Chart: Revenue Trend -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">Revenue Trend</div>
                            <div class="chart-subtitle">
                                <?php 
                                    if ($range === 'month') echo "Income for the current month";
                                    elseif ($range === 'week') echo "Income for the current week";
                                    else echo "Income for today"; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container-large">
                        <canvas id="revenueLineChart"></canvas>
                    </div>
                </div>

                <!-- Secondary Chart: Order Status Doughnut -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">Order Status</div>
                            <div class="chart-subtitle">
                                <?php 
                                    if ($range === 'month') echo "Distribution for the current month";
                                    elseif ($range === 'week') echo "Distribution for the current week";
                                    else echo "Distribution for today"; 
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="statusDoughnutChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ROW 3: LISTS & INSIGHTS -->
            <div class="dashboard-grid">
                
                <!-- Top Selling Products -->
                <div class="chart-card">
                    <div class="chart-header" style="margin-bottom:12px;">
                        <div>
                            <div class="chart-title">Top Selling Products</div>
                            <div class="chart-subtitle">
                                <?php 
                                    if ($range === 'month') echo "Most popular items ordered this month";
                                    elseif ($range === 'week') echo "Most popular items ordered this week";
                                    else echo "Most popular items ordered today"; 
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($top_products)): ?>
                        <?php $rank = 1; foreach ($top_products as $tp): ?>
                        <div class="top-product-row">
                            <div class="tp-name">
                                <span class="tp-rank">#<?php echo $rank++; ?></span>
                                <?php echo htmlspecialchars($tp['name']); ?>
                            </div>
                            <div class="tp-sold">
                                <?php echo $tp['total_sold']; ?> <span style="font-size:12px;color:#64748b;font-weight:600;">sold</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 14px;">No products sold in the last 30 days.</div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- ==========================================
     INITIALIZE CHART.JS VISUALIZATIONS 
=========================================== -->
<script>
/**
 * Global variables to store chart instances.
 * Using 'var' to prevent SyntaxError: Identifier '...' has already been declared
 * when Turbo re-executes this script on navigation.
 */
var revenueChartInstance = null;
var statusChartInstance = null;

function renderReportsCharts() {
    // ⚙️ Global Chart.js Defaults
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.color = "#64748b";

    // --- 1. REVENUE LINE CHART ---
    const revCanvas = document.getElementById('revenueLineChart');
    if (revCanvas) {
        const revCtx = revCanvas.getContext('2d');
        if (revenueChartInstance && typeof revenueChartInstance.destroy === 'function') {
            revenueChartInstance.destroy();
        }

        const gradient = revCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(6, 161, 161, 0.4)');
        gradient.addColorStop(1, 'rgba(6, 161, 161, 0.0)');

        revenueChartInstance = new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_dates); ?>,
                datasets: [{
                    label: 'Total Revenue (₱)',
                    data: <?php echo json_encode($trend_totals); ?>,
                    borderColor: '#06A1A1',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#06A1A1',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        titleFont: { size: 13 },
                        bodyFont: { size: 14, weight: 'bold' },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', drawBorder: false },
                        ticks: {
                            callback: function(value) { return '₱' + value.toLocaleString(); }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false }
                    }
                }
            }
        });
    }

    // --- 2. ORDER STATUS DOUGHNUT CHART ---
    const statusCanvas = document.getElementById('statusDoughnutChart');
    if (statusCanvas) {
        const statusCtx = statusCanvas.getContext('2d');
        if (statusChartInstance && typeof statusChartInstance.destroy === 'function') {
            statusChartInstance.destroy();
        }

        const statusColors = {
            'Pending': '#fef08a',
            'Pending Review': '#fde047',
            'Approved': '#86efac',
            'Downpayment Submitted': '#67e8f9',
            'Processing': '#3b82f6',
            'In Production': '#2563eb',
            'Ready for Pickup': '#a855f7',
            'Completed': '#22c55e',
            'Cancelled': '#ef4444',
            'No Data Yet': '#e2e8f0'
        };

        const rawLabels = <?php echo json_encode($status_labels); ?>;
        const rawData = <?php echo json_encode($status_counts); ?>;
        const bgColors = rawLabels.map(label => statusColors[label] || '#94a3b8');

        statusChartInstance = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: rawLabels,
                datasets: [{
                    data: rawData,
                    backgroundColor: bgColors,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
                options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 10
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 16,
                            font: { size: 12, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return ' ' + (context.parsed || 0) + ' Orders';
                            }
                        }
                    }
                }
            }
        });
    }
}

// Initial Load + Turbo Navigation
if (typeof renderReportsChartsScheduled === 'undefined') {
    var renderReportsChartsScheduled = true;
    document.addEventListener('DOMContentLoaded', renderReportsCharts);
    window.addEventListener('turbo:load', renderReportsCharts);
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (!revenueChartInstance && !statusChartInstance)) {
            renderReportsCharts();
        }
    });
} else {
    // If script re-runs via Turbo, just run the direct call
    renderReportsCharts();
}
</script>

</body>
</html>
