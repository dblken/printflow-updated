<?php
/**
 * Staff Products (Inventory) Page
 * PrintFlow - Printing Shop PWA
 * Read-only view for staff
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

require_once __DIR__ . '/../includes/branch_context.php';

$branch_ctx = init_branch_context(false);
$branchName = $branch_ctx['branch_name'] ?? 'Main Branch';

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM products WHERE status = 'Activated'";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR sku LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Pagination settings
$items_per_page = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Activated'";
$count_params = [];
$count_types = '';

if (!empty($category)) {
    $count_sql .= " AND category = ?";
    $count_params[] = $category;
    $count_types .= 's';
}

if (!empty($search)) {
    $count_sql .= " AND (name LIKE ? OR sku LIKE ?)";
    $count_params[] = '%' . $search . '%';
    $count_params[] = '%' . $search . '%';
    $count_types .= 'ss';
}

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sql .= " ORDER BY name ASC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$products = db_query($sql, $types, $params);
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

$page_title = 'Products & Inventory - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .page-title { font-size: 24px; font-weight: 800; color: #1f2937; margin-bottom: 20px; }
        .status-badge-pill { font-size: 10px; padding: 4px 10px; font-weight: 700; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; }
        .table-text-main { font-size: 13px; font-weight: 600; color: #1f2937; }
        .table-text-sub { font-size: 11px; color: #64748b; font-weight: 500; }
        
        /* ── Standard KPI Card Layout (Explicitly Defined) ── */
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb; position: relative; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .kpi-card.indigo { border-left: 4px solid #4f46e5; }
        .kpi-card.amber { border-left: 4px solid #f59e0b; }
        .kpi-card.blue { border-left: 4px solid #3b82f6; }
        .kpi-card.emerald { border-left: 4px solid #10b981; }
        .kpi-label { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .kpi-value { font-size: 28px; font-weight: 800; color: #1e293b; line-height: 1.2; }
        .kpi-sub { font-size: 11px; color: #64748b; font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 4px; }

        /* ── Toolbar Buttons ─── */
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
        
        .filter-badge {
            background: #0d9488;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 999px;
            margin-left: 4px;
        }

        .sort-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 200px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            z-index: 50;
            padding: 8px;
        }
        .sort-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 8px;
            color: #4b5563;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .sort-option:hover { background: #f9fafb; color: #111827; }
        .sort-option.selected { background: #f0fdfa; color: #0d9488; }

        .filter-panel {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 280px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            z-index: 50;
            overflow: hidden;
        }
        .filter-panel-header { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #1e293b; font-size: 14px; }
        .filter-section { padding: 18px; border-bottom: 1px solid #f1f5f9; }
        .filter-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .filter-section-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-reset-link { font-size: 11px; color: #0d9488; font-weight: 700; border: none; background: none; cursor: pointer; padding: 0; }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-select-v2 { width: 100%; height: 38px; padding: 0 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; color: #1e293b; outline: none; transition: border-color 0.2s; }
        .filter-select-v2:focus { border-color: #0d9488; }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 20px;">
            <h1 class="page-title" style="margin:0;">Products & Inventory</h1>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; background: #f1f5f9; padding: 6px 12px; border-radius: 8px;">
                Branch: <?php echo htmlspecialchars($branchName); ?>
            </div>
        </header>

        <main x-data="productManager()" x-init="init()">
            <!-- KPI Summary Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Products</div>
                    <div class="kpi-value"><?php echo number_format(count($products)); ?></div>
                    <div class="kpi-sub"><?php echo count($categories); ?> categories</div>
                </div>
                <?php
                $low_stock_count = 0;
                foreach($products as $p) if((int)$p['stock_quantity'] < 10) $low_stock_count++;
                ?>
                <div class="kpi-card amber">
                    <div class="kpi-label">Low Stock</div>
                    <div class="kpi-value"><?php echo $low_stock_count; ?></div>
                    <div class="kpi-sub">Items under 10 units</div>
                </div>
                <div class="kpi-card blue">
                    <div class="kpi-label">Active Catalogs</div>
                    <div class="kpi-value"><?php echo count($products); ?></div>
                    <div class="kpi-sub">Current items</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Activated</div>
                    <div class="kpi-value text-emerald-600">YES</div>
                    <div class="kpi-sub">All items enabled</div>
                </div>
            </div>

            <!-- Toolbar (Matching orders.php) -->
            <div class="card overflow-visible">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                    <h3 style="font-size:16px; font-weight:700; color:#1f2937; margin:0;">Inventory List</h3>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <!-- Search Field -->
                        <div style="position: relative; width: 260px;">
                            <input type="text" x-model="search" placeholder="Search product or SKU..." 
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
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <template x-for="s in [
                                    {id:'name_asc', label:'Name (A-Z)'},
                                    {id:'name_desc', label:'Name (Z-A)'},
                                    {id:'stock_asc', label:'Stock (Low to High)'},
                                    {id:'stock_desc', label:'Stock (High to Low)'}
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
                            <button class="toolbar-btn" :class="{ active: filterOpen || filterActive }" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                </svg>
                                Filter
                                <span class="filter-badge" x-show="filterActive">1</span>
                            </button>
                            <!-- Filter Panel -->
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Category</span>
                                        <button class="filter-reset-link" @click="categoryFilter = ''">Reset</button>
                                    </div>
                                    <select class="filter-select-v2" x-model="categoryFilter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="padding:14px 18px; background:#f9fafb; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:12px; color:#6b7280;" x-text="filteredProducts.length + ' results found'"></span>
                                    <button class="filter-reset-link" @click="categoryFilter = ''">Clear All</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="pl-6 pr-4 py-4 border-b border-gray-100">Product Info</th>
                                <th class="px-4 py-4 border-b border-gray-100">Category</th>
                                <th class="px-4 py-4 border-b border-gray-100">Type</th>
                                <th class="px-4 py-4 border-b border-gray-100 text-right">Price</th>
                                <th class="px-4 py-4 border-b border-gray-100 text-center">Stock Status</th>
                                <th class="px-4 py-4 border-b border-gray-100 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="p in paginatedProducts" :key="p.id">
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="pl-6 pr-4 py-4">
                                        <div class="table-text-main" x-text="p.name"></div>
                                        <div class="table-text-sub" x-text="'SKU: ' + p.sku"></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="table-text-sub" x-text="p.category"></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="status-badge-pill" 
                                              :class="p.product_type === 'fixed' ? 'badge-approved' : 'badge-topay'"
                                              x-text="p.product_type.charAt(0).toUpperCase() + p.product_type.slice(1)"></span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="table-text-main" x-text="'₱' + Number(p.price).toLocaleString(undefined, {minimumFractionDigits: 2})"></div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="table-text-main" :class="p.stock_quantity < 10 ? 'text-red-600' : 'text-emerald-600'" x-text="p.stock_quantity"></div>
                                            <div x-show="p.stock_quantity < 10" class="text-[9px] font-bold text-red-500 uppercase tracking-tighter">Low Stock</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="status-badge-pill badge-fulfilled" x-text="p.status"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredProducts.length === 0">
                                <td colspan="6" class="px-6 py-24 text-center">
                                    <span class="table-text-sub uppercase tracking-widest">No products found matching your search</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div x-show="totalPages > 1" class="pt-6 border-t border-gray-100 mt-4 flex justify-center gap-2">
                    <button @click="currentPage--" :disabled="currentPage === 1" class="toolbar-btn" style="padding: 0 10px;">&larr;</button>
                    <template x-for="p in Array.from({length: totalPages}, (_, i) => i + 1)" :key="p">
                        <button @click="currentPage = p" 
                                class="toolbar-btn" 
                                :class="{ 'active': currentPage === p }"
                                x-text="p" style="min-width:38px; justify-content:center;"></button>
                    </template>
                    <button @click="currentPage++" :disabled="currentPage === totalPages" class="toolbar-btn" style="padding: 0 10px;">&rarr;</button>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function productManager() {
    return {
        products: <?php echo json_encode($products); ?>,
        search: '',
        categoryFilter: '',
        activeSort: 'name_asc',
        sortOpen: false,
        filterOpen: false,
        currentPage: 1,
        itemsPerPage: 15,

        init() {
            this.$watch('search', () => this.currentPage = 1);
            this.$watch('categoryFilter', () => this.currentPage = 1);
        },

        get filterActive() {
            return this.categoryFilter !== '';
        },

        get activeSortLabel() {
            const map = {
                'name_asc': 'Name (A-Z)',
                'name_desc': 'Name (Z-A)',
                'stock_asc': 'Stock (Low to High)',
                'stock_desc': 'Stock (High to Low)'
            };
            return map[this.activeSort];
        },

        applySort(id) {
            this.activeSort = id;
            this.sortOpen = false;
        },

        get filteredProducts() {
            let res = this.products.filter(p => {
                const searchMatch = !this.search || 
                    p.name.toLowerCase().includes(this.search.toLowerCase()) || 
                    p.sku.toLowerCase().includes(this.search.toLowerCase());
                const categoryMatch = !this.categoryFilter || p.category === this.categoryFilter;
                return searchMatch && categoryMatch;
            });

            res.sort((a, b) => {
                if (this.activeSort === 'name_asc') return a.name.localeCompare(b.name);
                if (this.activeSort === 'name_desc') return b.name.localeCompare(a.name);
                if (this.activeSort === 'stock_asc') return a.stock_quantity - b.stock_quantity;
                if (this.activeSort === 'stock_desc') return b.stock_quantity - a.stock_quantity;
                return 0;
            });

            return res;
        },

        get paginatedProducts() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredProducts.slice(start, start + this.itemsPerPage);
        },

        get totalPages() {
            return Math.ceil(this.filteredProducts.length / this.itemsPerPage);
        }
    };
}
</script>

</body>
</html>
