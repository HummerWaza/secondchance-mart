<?php
// ============================================================
// SecondChance Mart - Product Listing / Browse Page
// Supports: category filter, deal type filter, search, pagination
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// ── Read filter parameters ───────────────────────────────────
$categorySlug = $_GET['category'] ?? '';
$dealType     = $_GET['deal']     ?? '';
$search       = trim($_GET['search'] ?? '');
$sortBy       = $_GET['sort']     ?? 'discount';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = PRODUCTS_PER_PAGE;
$offset       = ($page - 1) * $perPage;

// ── Build dynamic WHERE clause ───────────────────────────────
$where  = "WHERE p.status = 'active'";
$params = [];

if ($categorySlug) {
    $where .= " AND c.slug = ?";
    $params[] = $categorySlug;
}
if ($dealType) {
    $where .= " AND p.deal_type = ?";
    $params[] = $dealType;
}
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ── Sort order ───────────────────────────────────────────────
$orderBy = match($sortBy) {
    'price_asc'    => 'p.discount_price ASC',
    'price_desc'   => 'p.discount_price DESC',
    'expiry'       => 'p.expiry_date ASC',
    'newest'       => 'p.created_at DESC',
    default        => 'p.discount_percentage DESC',   // highest discount first
};

// ── Count total for pagination ───────────────────────────────
$countSql  = "SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id=c.id $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages    = ceil($totalProducts / $perPage);

// ── Fetch products ───────────────────────────────────────────
$sql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           s.company_name AS supplier_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    $where
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ── Load all categories for filter sidebar ───────────────────
$allCategories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS cnt
    FROM categories c
    LEFT JOIN products p ON c.id=p.category_id AND p.status='active'
    GROUP BY c.id ORDER BY c.id
")->fetchAll();

// Active category name for page title
$activeCategory = null;
if ($categorySlug) {
    foreach ($allCategories as $cat) {
        if ($cat['slug'] === $categorySlug) { $activeCategory = $cat; break; }
    }
}

$pageTitle = $activeCategory ? $activeCategory['name'] : ($search ? "Search: $search" : 'All Products');
include __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container">
        <h1 class="mb-1">
            <?php if ($search): ?>
                Search Results for "<?= e($search) ?>"
            <?php elseif ($activeCategory): ?>
                <i class="fas <?= e($activeCategory['icon']) ?> me-2"></i><?= e($activeCategory['name']) ?>
            <?php else: ?>
                🛒 All Products
            <?php endif; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/">Home</a></li>
                <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <!-- ── Sidebar ─────────────────────────────────────── -->
        <div class="col-lg-3">
            <!-- Search Box -->
            <div class="bg-white rounded-10 shadow-sm p-3 mb-3">
                <h6 class="fw-bold mb-2 text-green"><i class="fas fa-search me-2"></i>Search</h6>
                <form method="GET" action="">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control"
                               placeholder="Search products..."
                               value="<?= e($search) ?>">
                        <button class="btn btn-success" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>

            <!-- Category Filter -->
            <div class="bg-white rounded-10 shadow-sm p-3 mb-3">
                <h6 class="fw-bold mb-3 text-green"><i class="fas fa-th-large me-2"></i>Categories</h6>
                <a href="<?= SITE_URL ?>/products.php"
                   class="d-flex justify-content-between align-items-center text-decoration-none py-1 px-2 rounded mb-1
                          <?= !$categorySlug ? 'bg-success text-white' : 'text-dark' ?>">
                    <span><i class="fas fa-border-all me-2"></i>All Products</span>
                    <span class="badge <?= !$categorySlug ? 'bg-white text-success' : 'bg-light text-muted' ?>">
                        <?= array_sum(array_column($allCategories, 'cnt')) ?>
                    </span>
                </a>
                <?php foreach ($allCategories as $cat): ?>
                <a href="<?= SITE_URL ?>/products.php?category=<?= e($cat['slug']) ?>"
                   class="d-flex justify-content-between align-items-center text-decoration-none py-1 px-2 rounded mb-1
                          <?= $categorySlug === $cat['slug'] ? 'bg-success text-white' : 'text-dark' ?>"
                   style="font-size:13px;">
                    <span><i class="fas <?= e($cat['icon']) ?> me-2"></i><?= e($cat['name']) ?></span>
                    <span class="badge <?= $categorySlug === $cat['slug'] ? 'bg-white text-success' : 'bg-light text-muted' ?>">
                        <?= $cat['cnt'] ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Deal Type Filter -->
            <div class="bg-white rounded-10 shadow-sm p-3 mb-3">
                <h6 class="fw-bold mb-3 text-green"><i class="fas fa-tags me-2"></i>Deal Type</h6>
                <?php
                $dealTypes = [
                    '' => ['All Deals', 'fa-border-all', 'secondary'],
                    'near_expiry'  => ['Near Expiry', 'fa-clock', 'danger'],
                    'overstock'    => ['Overstock',   'fa-boxes', 'warning'],
                    'damaged_pkg'  => ['Damaged Pkg', 'fa-box-open', 'info'],
                    'seasonal'     => ['Seasonal',    'fa-leaf', 'primary'],
                    'general'      => ['General Sale','fa-tag', 'success'],
                ];
                foreach ($dealTypes as $key => $label):
                    $isActive = ($dealType === $key);
                ?>
                <a href="<?= SITE_URL ?>/products.php?deal=<?= $key ?><?= $categorySlug ? '&category='.$categorySlug : '' ?>"
                   class="d-block text-decoration-none py-1 px-2 rounded mb-1 small
                          <?= $isActive ? 'bg-'.$label[2].' text-white' : 'text-dark' ?>">
                    <i class="fas <?= $label[1] ?> me-2"></i><?= $label[0] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Product Grid ──────────────────────────────── -->
        <div class="col-lg-9">
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center bg-white rounded-10 shadow-sm p-3 mb-3">
                <span class="text-muted small">
                    Showing <strong><?= min($offset+1, $totalProducts) ?>–<?= min($offset+$perPage, $totalProducts) ?></strong>
                    of <strong><?= $totalProducts ?></strong> products
                </span>
                <form method="GET" action="" class="d-flex align-items-center gap-2">
                    <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
                    <?php if ($search): ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif; ?>
                    <?php if ($dealType): ?><input type="hidden" name="deal" value="<?= e($dealType) ?>"><?php endif; ?>
                    <label class="small text-muted mb-0">Sort by:</label>
                    <select name="sort" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="discount"   <?= $sortBy==='discount'    ? 'selected' : '' ?>>Biggest Discount</option>
                        <option value="price_asc"  <?= $sortBy==='price_asc'   ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sortBy==='price_desc'  ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="expiry"     <?= $sortBy==='expiry'      ? 'selected' : '' ?>>Expiring Soonest</option>
                        <option value="newest"     <?= $sortBy==='newest'      ? 'selected' : '' ?>>Newest</option>
                    </select>
                </form>
            </div>

            <!-- Products -->
            <?php if (empty($products)): ?>
            <div class="text-center py-5 bg-white rounded-10 shadow-sm">
                <div style="font-size:4rem;">🔍</div>
                <h5 class="mt-3">No products found</h5>
                <p class="text-muted">Try adjusting your search or filters.</p>
                <a href="<?= SITE_URL ?>/products.php" class="btn btn-success">View All Products</a>
            </div>
            <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                <?php foreach ($products as $p):
                    $daysLeft = daysUntilExpiry($p['expiry_date']);
                ?>
                <div class="col">
                    <div class="product-card">
                        <div class="product-img-wrap">
                            <img src="<?= e(productImage($p['image_url'], $p['name'])) ?>"
                                 alt="<?= e($p['name']) ?>" loading="lazy">
                            <?php if ($p['discount_percentage'] > 0): ?>
                            <div class="discount-badge">-<?= $p['discount_percentage'] ?>%</div>
                            <?php endif; ?>
                            <!-- Out of stock overlay -->
                            <?php if ($p['stock_quantity'] < 1): ?>
                            <div class="position-absolute inset-0 d-flex align-items-center justify-content-center"
                                 style="background:rgba(0,0,0,.45);inset:0;position:absolute;">
                                <span class="badge bg-dark fs-6">Out of Stock</span>
                            </div>
                            <?php elseif ($p['stock_quantity'] <= LOW_STOCK_THRESHOLD): ?>
                            <span class="deal-badge bg-warning text-dark">
                                Only <?= $p['stock_quantity'] ?> left!
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="product-body">
                            <div class="product-category"><?= e($p['category_name']) ?></div>
                            <div class="product-name"><?= e($p['name']) ?></div>
                            <div class="product-supplier">
                                <i class="fas fa-store me-1"></i><?= e($p['supplier_name'] ?? 'SecondChance Mart') ?>
                            </div>

                            <?php if ($daysLeft !== null && $daysLeft <= 7): ?>
                            <div class="expiry-warn">
                                <i class="fas fa-clock me-1"></i>
                                <?php if ($daysLeft === 0): ?>Expires today!
                                <?php elseif ($daysLeft === 1): ?>Expires tomorrow!
                                <?php else: ?>Expires in <?= $daysLeft ?> days<?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="price-block mt-2">
                                <span class="price-original"><?= formatPrice($p['original_price']) ?></span>
                                <span class="price-discount"><?= formatPrice($p['discount_price']) ?></span>
                                <?php if ($p['discount_percentage'] > 0): ?>
                                <small class="text-orange">Save <?= formatPrice($p['original_price'] - $p['discount_price']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="product-actions">
                            <button class="btn-cart"
                                    onclick="addToCart(<?= $p['id'] ?>, this)"
                                    <?= ($p['stock_quantity'] < 1) ? 'disabled' : '' ?>>
                                <?= ($p['stock_quantity'] < 1)
                                    ? 'Out of Stock'
                                    : '<i class="fas fa-cart-plus me-1"></i>Add to Cart' ?>
                            </button>
                            <a href="<?= SITE_URL ?>/product-detail.php?id=<?= $p['id'] ?>"
                               class="btn-detail" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++):
                        $qp = http_build_query(array_merge($_GET, ['page' => $i]));
                    ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= $qp ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
