<?php
// ============================================================
// SecondChance Mart - Home Page
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// ── Load Categories with product counts ─────────────────────
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY c.id
")->fetchAll();

// ── Featured Products (highest discount, active) ─────────────
$featuredProducts = $pdo->query("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           s.company_name AS supplier_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.status = 'active' AND p.stock_quantity > 0
    ORDER BY p.discount_percentage DESC
    LIMIT 8
")->fetchAll();

// ── Near Expiry Deals (expiring within 7 days) ───────────────
$nearExpiryProducts = $pdo->query("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           s.company_name AS supplier_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.status = 'active'
      AND p.stock_quantity > 0
      AND p.expiry_date IS NOT NULL
      AND p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY p.expiry_date ASC
    LIMIT 4
")->fetchAll();

// ── Stats for banner ─────────────────────────────────────────
$totalProducts  = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>

<!-- ── Hero Section ──────────────────────────────────────── -->
<section class="hero-section d-flex align-items-center">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-7 hero-content">
                <div class="hero-badge">🌱 Eco-Friendly Shopping</div>
                <h1 class="hero-title text-white mb-3">
                    Get Groceries at<br>
                    <span class="hero-highlight">Up to 70% Off!</span>
                </h1>
                <p class="text-white-50 fs-5 mb-4">
                    Shop near-expiry products, overstock clearance, and seasonal deals.
                    Save money while helping reduce food waste.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= SITE_URL ?>/products.php" class="btn btn-warning btn-lg fw-bold px-4">
                        <i class="fas fa-shopping-bag me-2"></i>Shop Now
                    </a>
                    <a href="<?= SITE_URL ?>/products.php?category=near-expiry" class="btn btn-outline-light btn-lg px-4">
                        <i class="fas fa-clock me-2"></i>Near Expiry Deals
                    </a>
                </div>

                <!-- Mini stats -->
                <div class="d-flex gap-4 mt-4">
                    <div class="text-white">
                        <div class="fs-4 fw-bold"><?= number_format($totalProducts) ?>+</div>
                        <div class="text-white-50 small">Products</div>
                    </div>
                    <div class="text-white">
                        <div class="fs-4 fw-bold"><?= number_format($totalCustomers) ?>+</div>
                        <div class="text-white-50 small">Happy Customers</div>
                    </div>
                    <div class="text-white">
                        <div class="fs-4 fw-bold">70%</div>
                        <div class="text-white-50 small">Max Savings</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Trust Bar ─────────────────────────────────────────── -->
<div class="bg-white border-bottom py-3">
    <div class="container">
        <div class="row text-center g-2">
            <div class="col-6 col-md-3">
                <i class="fas fa-truck text-success me-2"></i>
                <small class="fw-semibold">Free Delivery Over $30</small>
            </div>
            <div class="col-6 col-md-3">
                <i class="fas fa-shield-alt text-success me-2"></i>
                <small class="fw-semibold">Quality Guaranteed</small>
            </div>
            <div class="col-6 col-md-3">
                <i class="fas fa-sync-alt text-success me-2"></i>
                <small class="fw-semibold">Easy Returns</small>
            </div>
            <div class="col-6 col-md-3">
                <i class="fas fa-leaf text-success me-2"></i>
                <small class="fw-semibold">Reduce Food Waste</small>
            </div>
        </div>
    </div>
</div>

<!-- ── Categories ────────────────────────────────────────── -->
<section class="py-5">
    <div class="container">
        <h2 class="section-heading mb-1">Browse by <span>Category</span></h2>
        <div class="section-divider"></div>

        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3">
            <?php foreach ($categories as $cat): ?>
            <div class="col">
                <a href="<?= SITE_URL ?>/products.php?category=<?= e($cat['slug']) ?>"
                   class="text-decoration-none">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="fas <?= e($cat['icon']) ?>"></i>
                        </div>
                        <p class="category-name"><?= e($cat['name']) ?></p>
                        <p class="category-count"><?= $cat['product_count'] ?> items</p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Deal Banners ───────────────────────────────────────── -->
<section class="py-2 pb-4">
    <div class="container">
        <div class="row g-3">
            <!-- Banner 1: Near Expiry -->
            <div class="col-md-4">
                <a href="<?= SITE_URL ?>/products.php?deal=near_expiry" class="text-decoration-none">
                    <div class="deal-banner deal-banner-1"
                         style="background-image:url('https://images.unsplash.com/photo-1542838132-92c53300491e?w=600&q=80')">
                        <div class="deal-banner-content">
                            <span class="badge bg-white text-danger mb-2">⏰ FLASH DEAL</span>
                            <h4 class="text-white fw-bold mb-1">Near Expiry Items</h4>
                            <p class="text-white-50 small mb-2">Up to 70% off — limited time</p>
                            <span class="btn btn-sm btn-white bg-white text-danger fw-bold">Shop Now →</span>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Banner 2: Overstock -->
            <div class="col-md-4">
                <a href="<?= SITE_URL ?>/products.php?deal=overstock" class="text-decoration-none">
                    <div class="deal-banner deal-banner-2"
                         style="background-image:url('https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=600&q=80')">
                        <div class="deal-banner-content">
                            <span class="badge bg-white text-warning mb-2">📦 OVERSTOCK</span>
                            <h4 class="text-white fw-bold mb-1">Bulk Clearance</h4>
                            <p class="text-white-50 small mb-2">Stock must go — grab your share</p>
                            <span class="btn btn-sm btn-white bg-white text-warning fw-bold">Shop Now →</span>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Banner 3: Damaged Packaging -->
            <div class="col-md-4">
                <a href="<?= SITE_URL ?>/products.php?deal=damaged_pkg" class="text-decoration-none">
                    <div class="deal-banner deal-banner-3"
                         style="background-image:url('https://images.unsplash.com/photo-1550989460-0adf9ea622e2?w=600&q=80')">
                        <div class="deal-banner-content">
                            <span class="badge bg-white text-success mb-2">📋 PACKAGING DEAL</span>
                            <h4 class="text-white fw-bold mb-1">Dented & Imperfect</h4>
                            <p class="text-white-50 small mb-2">Packaging imperfect, product perfect</p>
                            <span class="btn btn-sm btn-white bg-white text-success fw-bold">Shop Now →</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ── Featured Products ──────────────────────────────────── -->
<section class="py-4 pb-5 bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-2">
            <div>
                <h2 class="section-heading mb-1">🔥 <span>Best Deals</span> Today</h2>
                <div class="section-divider"></div>
            </div>
            <a href="<?= SITE_URL ?>/products.php" class="btn btn-outline-success btn-sm">View All</a>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($featuredProducts as $p):
                $daysLeft = daysUntilExpiry($p['expiry_date']);
            ?>
            <div class="col">
                <div class="product-card">
                    <!-- Product Image -->
                    <div class="product-img-wrap">
                        <img src="<?= e(productImage($p['image_url'], $p['name'])) ?>"
                             alt="<?= e($p['name']) ?>" loading="lazy">
                        <!-- Deal Type Badge -->
                        <span class="deal-badge <?= 'bg-' . statusColor('pending') ?>">
                            <?= getDealBadge($p['deal_type'], 0) ?>
                        </span>
                        <!-- Discount % Badge -->
                        <?php if ($p['discount_percentage'] > 0): ?>
                        <div class="discount-badge">-<?= $p['discount_percentage'] ?>%</div>
                        <?php endif; ?>
                    </div>

                    <!-- Product Info -->
                    <div class="product-body">
                        <div class="product-category"><?= e($p['category_name']) ?></div>
                        <div class="product-name"><?= e($p['name']) ?></div>
                        <div class="product-supplier"><i class="fas fa-store me-1"></i><?= e($p['supplier_name'] ?? 'SecondChance Mart') ?></div>

                        <!-- Expiry Warning -->
                        <?php if ($daysLeft !== null && $daysLeft <= 7): ?>
                        <div class="expiry-warn">
                            <i class="fas fa-clock me-1"></i>
                            <span data-expiry="<?= e($p['expiry_date']) ?>"></span>
                        </div>
                        <?php endif; ?>

                        <!-- Pricing -->
                        <div class="price-block mt-2">
                            <span class="price-original"><?= formatPrice($p['original_price']) ?></span>
                            <span class="price-discount"><?= formatPrice($p['discount_price']) ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="product-actions">
                        <button class="btn-cart"
                                onclick="addToCart(<?= $p['id'] ?>, this)"
                                <?= ($p['stock_quantity'] < 1) ? 'disabled' : '' ?>>
                            <?= ($p['stock_quantity'] < 1) ? 'Out of Stock' : '<i class="fas fa-cart-plus me-1"></i>Add to Cart' ?>
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
    </div>
</section>

<!-- ── Near Expiry Section ────────────────────────────────── -->
<?php if (!empty($nearExpiryProducts)): ?>
<section class="py-5" style="background:#fff8f0;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-2">
            <div>
                <h2 class="section-heading mb-1">⏰ <span>Near Expiry</span> — Save More!</h2>
                <div class="section-divider"></div>
                <p class="text-muted small">These items expire soon — buy now at massive discounts!</p>
            </div>
            <a href="<?= SITE_URL ?>/products.php?category=near-expiry" class="btn btn-outline-danger btn-sm">View All</a>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3">
            <?php foreach ($nearExpiryProducts as $p):
                $daysLeft = daysUntilExpiry($p['expiry_date']);
            ?>
            <div class="col">
                <div class="product-card" style="border-top: 3px solid #e74c3c;">
                    <div class="product-img-wrap">
                        <img src="<?= e(productImage($p['image_url'], $p['name'])) ?>"
                             alt="<?= e($p['name']) ?>" loading="lazy">
                        <div class="discount-badge bg-danger">-<?= $p['discount_percentage'] ?>%</div>
                    </div>
                    <div class="product-body">
                        <div class="product-category text-danger"><?= e($p['category_name']) ?></div>
                        <div class="product-name"><?= e($p['name']) ?></div>
                        <div class="expiry-warn mb-2">
                            <i class="fas fa-clock me-1"></i>
                            <?php if ($daysLeft === 0): ?>Expires today!
                            <?php elseif ($daysLeft === 1): ?>Expires tomorrow!
                            <?php else: ?>Expires in <?= $daysLeft ?> days
                            <?php endif; ?>
                        </div>
                        <div class="price-block">
                            <span class="price-original"><?= formatPrice($p['original_price']) ?></span>
                            <span class="price-discount text-danger"><?= formatPrice($p['discount_price']) ?></span>
                        </div>
                    </div>
                    <div class="product-actions">
                        <button class="btn-cart" style="background:#e74c3c;"
                                onclick="addToCart(<?= $p['id'] ?>, this)">
                            <i class="fas fa-cart-plus me-1"></i>Add to Cart
                        </button>
                        <a href="<?= SITE_URL ?>/product-detail.php?id=<?= $p['id'] ?>"
                           class="btn-detail"><i class="fas fa-eye"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── Why Choose Us ─────────────────────────────────────── -->
<section class="py-5 bg-white">
    <div class="container">
        <h2 class="section-heading text-center mb-1">Why <span>SecondChance Mart</span>?</h2>
        <div class="section-divider mx-auto mb-4"></div>

        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="p-4">
                    <div class="category-icon mx-auto mb-3" style="font-size:2rem;">💰</div>
                    <h6 class="fw-bold">Massive Savings</h6>
                    <p class="text-muted small">Save up to 70% on groceries that are still perfectly safe and edible.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4">
                    <div class="category-icon mx-auto mb-3" style="font-size:2rem;">🌱</div>
                    <h6 class="fw-bold">Reduce Food Waste</h6>
                    <p class="text-muted small">Every purchase helps prevent food waste and supports a sustainable future.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4">
                    <div class="category-icon mx-auto mb-3" style="font-size:2rem;">✅</div>
                    <h6 class="fw-bold">Quality Assured</h6>
                    <p class="text-muted small">All products are inspected. We only sell items that are safe and edible.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4">
                    <div class="category-icon mx-auto mb-3" style="font-size:2rem;">🚚</div>
                    <h6 class="fw-bold">Fast Delivery</h6>
                    <p class="text-muted small">Same-day or next-day delivery available. Free delivery on orders over $30.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
