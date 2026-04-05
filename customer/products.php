<?php
/**
 * Customer Products Page (Fixed Products Only)
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query — only show Fixed Products as requested
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.product_id AND pv.status = 'Active') as variant_count,
        (SELECT SUM(quantity) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.product_id = p.product_id AND o.order_type = 'product' AND o.status IN ('Completed', 'Delivered')) as sold_count,
        (SELECT AVG(rating) FROM reviews r WHERE r.reference_id = p.product_id AND r.review_type = 'product') as avg_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.reference_id = p.product_id AND r.review_type = 'product') as review_count
        FROM products p 
        WHERE p.status = 'Activated' AND (p.product_type = 'fixed' OR p.product_type = '')";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Pagination settings
$items_per_page = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Activated' AND (product_type = 'fixed' OR product_type = '')";
$count_params = [];
$count_types = '';

if (!empty($category)) {
    $count_sql .= " AND category = ?";
    $count_params[] = $category;
    $count_types .= 's';
}

if (!empty($search)) {
    $count_sql .= " AND (name LIKE ? OR description LIKE ?)";
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

$page_title = 'Products - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .ct-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.5rem;
    }
    
    .ct-product-card {
        background: rgba(10, 37, 48, 0.48);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(83, 197, 224, 0.16);
        border-radius: 1.25rem;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .ct-product-card:hover {
        transform: translateY(-8px);
        border-color: rgba(83, 197, 224, 0.5);
        background: rgba(10, 37, 48, 0.7);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .ct-product-img-wrapper {
        width: 100%;
        aspect-ratio: 1;
        overflow: hidden;
        position: relative;
    }

    .ct-product-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .ct-product-card:hover .ct-product-img {
        transform: scale(1.1);
    }

    .ct-product-body {
        padding: 1.25rem 1.1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        text-align: left;
    }

    .ct-product-category {
        font-size: 0.65rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #53c5e0;
        margin-bottom: 0.5rem;
        display: inline-block;
    }

    .ct-product-name {
        font-size: 1.1rem;
        font-weight: 800;
        color: #eaf6fb;
        line-height: 1.3;
        margin-bottom: 0.4rem;
        height: 2.8rem;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .ct-product-footer {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: auto;
    }

    .ct-price-tag {
        font-size: 1.15rem;
        font-weight: 900;
        color: #53c5e0;
        letter-spacing: -0.02em;
    }

    .ct-btn-primary {
        flex: 1;
        background: #0a2530;
        border: 1px solid rgba(83, 197, 224, 0.32);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.8rem 0;
        border-radius: 0.75rem;
        transition: all 0.25s;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .ct-product-card:hover .ct-btn-primary {
        background: #53C5E0;
        border-color: #53C5E0;
        color: #030d11;
        box-shadow: 0 4px 15px rgba(83, 197, 224, 0.35);
    }
    
    .ct-btn-cart {
        width: 46px;
        height: 46px;
        flex-shrink: 0;
        background: rgba(83, 197, 224, 0.1);
        border: 1px solid rgba(83, 197, 224, 0.2);
        border-radius: 0.75rem;
        color: #53c5e0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.25s;
    }

    .ct-btn-cart:hover {
        background: #53c5e0;
        color: #030d11;
        border-color: #53c5e0;
        transform: translateY(-2px);
    }

    .ct-search-box {
        background: rgba(10, 37, 48, 0.6);
        border: 1px solid rgba(83, 197, 224, 0.2);
        border-radius: 1rem;
        padding: 0.4rem;
        display: flex;
        gap: 0.4rem;
        backdrop-filter: blur(8px);
        width: 100%;
        max-width: 460px;
    }

    .ct-search-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: #fff;
        padding: 0 1rem;
        font-size: 0.9rem;
    }

    .ct-search-input::placeholder {
        color: rgba(164, 216, 235, 0.4);
    }

    .ct-search-btn {
        background: #53c5e0;
        color: #0a2530;
        font-weight: 800;
        padding: 0.6rem 1.5rem;
        border-radius: 0.75rem;
        font-size: 0.8rem;
        text-transform: uppercase;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ct-search-btn:hover {
        opacity: 0.9;
        transform: scale(0.98);
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <div class="mb-8 mt-4"></div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-lg p-12 text-center shadow-sm">
                <div class="text-6xl mb-4">📦</div>
                <p class="text-gray-500 text-lg">No products found.</p>
                <a href="products.php" class="text-shopee-orange mt-4 inline-block hover:underline font-semibold">Browse all products</a>
            </div>
        <?php else: ?>
        <div class="ct-product-grid">
            <?php foreach ($products as $product): 
                $photo = trim($product['photo_path'] ?? '');
                $prod_img = trim($product['product_image'] ?? '');
                $path_to_try = ($photo !== '') ? $photo : $prod_img;
                
                $display_img = "/printflow/public/assets/images/services/default.png";
                if ($path_to_try !== '') {
                    if ($path_to_try[0] === '/' || strpos($path_to_try, 'http') !== false) {
                        $display_img = $path_to_try;
                    } else {
                        // Check if file exists in uploads/products
                        if (file_exists(__DIR__ . '/../uploads/products/' . $path_to_try)) {
                            $display_img = '/printflow/uploads/products/' . $path_to_try;
                        }
                    }
                }
                
                $sold_count = (int)$product['sold_count'];
                $review_count = (int)$product['review_count'];
                if ($sold_count < $review_count) $sold_count = $review_count;
                
                $avg_rating = (float)$product['avg_rating'];
            ?>
                <div class="ct-product-card group" onclick="window.location.href='order_create.php?product_id=<?php echo $product['product_id']; ?>'">
                    <div class="ct-product-img-wrapper">
                        <img src="<?php echo htmlspecialchars($display_img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="ct-product-img">
                    </div>
                    
                    <div class="ct-product-body">
                        <span class="ct-product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                        <h3 class="ct-product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        
                        <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 1.5rem;">
                            <!-- Stars -->
                            <div style="display: flex; gap: 1px;">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <svg width="13" height="13" fill="<?php echo ($i <= round($avg_rating)) ? '#FBBF24' : '#374151'; ?>" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                <?php endfor; ?>
                            </div>
                            <?php if ($review_count > 0): ?>
                                <span style="font-size: 0.72rem; color: #9ca3af; margin-left: 2px;">(<?php echo $review_count; ?>) Reviews</span>
                            <?php endif; ?>
                            <span style="font-size: 0.72rem; color: #64748b; margin-left: auto;"><?php echo $sold_count; ?> sold</span>
                        </div>

                        <div class="ct-product-footer">
                            <div class="ct-price-tag"><?php echo format_currency($product['price']); ?></div>
                            <div style="flex: 1; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <button type="button" onclick="event.stopPropagation(); addToCartDirect(<?php echo $product['product_id']; ?>)" class="ct-btn-cart" title="Add to Cart">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </button>
                                <a href="order_create.php?product_id=<?php echo $product['product_id']; ?>&buy_now=1" class="ct-btn-primary" style="flex: 0 0 100px;">Buy Now</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

            <!-- Pagination -->
            <div class="mt-12 flex justify-center">
                <?php echo get_pagination_links($current_page, $total_pages, ['category' => $category, 'search' => $search]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
var PF_CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

async function addToCartDirect(productId) {
    try {
        const response = await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                product_id: productId,
                quantity: 1,
                csrf_token: PF_CSRF_TOKEN
            })
        });

        const data = await response.json();

        if (data.success) {
            if (window.updateCartBadge) updateCartBadge(data.cart_count);
            showToast('Added to cart!');
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    } catch (err) {
        console.error('Cart Error:', err);
        alert('An error occurred. Please try again.');
    }
}

function showToast(msg) {
    let toast = document.getElementById('shopee-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'shopee-toast';
        document.body.appendChild(toast);
    }
    
    toast.textContent = msg;
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%);
        background: #0a2530;
        border: 1px solid rgba(83, 197, 224, 0.3);
        color: white;
        padding: 12px 28px;
        border-radius: 1rem;
        font-size: 0.9rem;
        font-weight: 700;
        z-index: 10000;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
        box-shadow: 0 15px 35px rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    toast.innerHTML = `<svg style="width:1.2rem;height:1.2rem;color:#53c5e0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg> ${msg}`;
    
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(10px)';
    }, 2500);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
