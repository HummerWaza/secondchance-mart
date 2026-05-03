<?php
// ============================================================
// SecondChance Mart - Product Detail Page
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

// Fetch the product
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           s.company_name AS supplier_name, s.contact_person AS supplier_contact
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect(SITE_URL . '/products.php');
}

// Related products (same category, different product)
$related = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' AND p.stock_quantity > 0
    ORDER BY p.discount_percentage DESC
    LIMIT 4
");
$related->execute([$product['category_id'], $id]);
$relatedProducts = $related->fetchAll();

$daysLeft  = daysUntilExpiry($product['expiry_date']);
$savings   = $product['original_price'] - $product['discount_price'];
$pageTitle = $product['name'];
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="page-hero">
    <div class="container">
        <h1 class="mb-1"><?= e($product['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/products.php">Products</a></li>
                <li class="breadcrumb-item">
                    <a href="<?= SITE_URL ?>/products.php?category=<?= e($product['category_slug']) ?>">
                        <?= e($product['category_name']) ?>
                    </a>
                </li>
                <li class="breadcrumb-item active"><?= e($product['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <!-- ── Product Image ────────────────────────────── -->
        <div class="col-md-5">
            <div class="position-relative">
                <img src="<?= e(productImage($product['image_url'], $product['name'])) ?>"
                     alt="<?= e($product['name']) ?>"
                     class="product-detail-img shadow"
                     onerror="this.src='https://placehold.co/500x400/27ae60/ffffff?text=No+Image'">
                <!-- Discount badge -->
                <?php if ($product['discount_percentage'] > 0): ?>
                <div class="position-absolute top-0 end-0 m-3">
                    <span class="badge bg-warning text-dark fs-5 p-2">
                        -<?= $product['discount_percentage'] ?>%
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Product Info ──────────────────────────────── -->
        <div class="col-md-7">
            <div class="bg-white rounded-10 shadow-sm p-4 h-100">
                <!-- Category & Deal Badges -->
                <div class="mb-2">
                    <a href="<?= SITE_URL ?>/products.php?category=<?= e($product['category_slug']) ?>"
                       class="badge bg-success text-white text-decoration-none me-2">
                        <?= e($product['category_name']) ?>
                    </a>
                    <?= getDealBadge($product['deal_type'], $product['discount_percentage']) ?>
                </div>

                <!-- Name -->
                <h2 class="fw-bold mb-3"><?= e($product['name']) ?></h2>

                <!-- Supplier -->
                <p class="text-muted mb-2">
                    <i class="fas fa-store text-green me-2"></i>
                    Supplied by: <strong><?= e($product['supplier_name'] ?? 'SecondChance Mart') ?></strong>
                </p>

                <!-- Pricing -->
                <div class="product-detail-price mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="price-sale"><?= formatPrice($product['discount_price']) ?></span>
                        <div>
                            <div class="price-original"><?= formatPrice($product['original_price']) ?></div>
                            <?php if ($savings > 0): ?>
                            <div class="savings"><i class="fas fa-tag me-1"></i>You save <?= formatPrice($savings) ?>!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Expiry & Stock Info -->
                <div class="row g-2 mb-3">
                    <!-- Stock -->
                    <div class="col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-muted">In Stock</div>
                            <?php if ($product['stock_quantity'] > 10): ?>
                                <div class="stock-indicator justify-content-center">
                                    <span class="stock-dot in"></span>
                                    <strong class="text-success"><?= $product['stock_quantity'] ?> available</strong>
                                </div>
                            <?php elseif ($product['stock_quantity'] > 0): ?>
                                <div class="stock-indicator justify-content-center">
                                    <span class="stock-dot low"></span>
                                    <strong class="text-warning">Only <?= $product['stock_quantity'] ?> left!</strong>
                                </div>
                            <?php else: ?>
                                <div class="stock-indicator justify-content-center">
                                    <span class="stock-dot out"></span>
                                    <strong class="text-danger">Out of Stock</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Expiry -->
                    <div class="col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-muted">Best Before</div>
                            <?php if ($product['expiry_date']): ?>
                                <strong class="<?= $daysLeft !== null && $daysLeft <= 3 ? 'text-danger' : 'text-dark' ?>">
                                    <?= formatDate($product['expiry_date']) ?>
                                </strong>
                                <?php if ($daysLeft !== null): ?>
                                <div class="small <?= $daysLeft <= 3 ? 'text-danger' : 'text-muted' ?>">
                                    <?= $daysLeft <= 0 ? 'Expired!' : ($daysLeft === 1 ? 'Tomorrow' : "$daysLeft days left") ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong class="text-success">No Expiry</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <h6 class="fw-bold">About this product:</h6>
                    <p class="text-muted mb-0"><?= nl2br(e($product['description'])) ?></p>
                </div>

                <!-- Quantity + Add to Cart -->
                <?php if ($product['stock_quantity'] > 0): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="qty-control">
                        <button class="qty-btn" data-action="minus">−</button>
                        <input type="number" id="detailQty" class="qty-input"
                               value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                        <button class="qty-btn" data-action="plus">+</button>
                    </div>
                    <button class="btn btn-success btn-lg flex-grow-1 fw-bold"
                            onclick="addToCartWithQty(<?= $product['id'] ?>, this)">
                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                    </button>
                </div>
                <?php else: ?>
                <button class="btn btn-secondary btn-lg w-100 mb-3" disabled>
                    <i class="fas fa-times-circle me-2"></i>Out of Stock
                </button>
                <?php endif; ?>

                <!-- Safety Note for Near Expiry -->
                <?php if ($product['deal_type'] === 'near_expiry'): ?>
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Near expiry deal:</strong> This product is approaching its best-before date but is still safe and good to consume. Please consume within the date shown.
                </div>
                <?php elseif ($product['deal_type'] === 'damaged_pkg'): ?>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-box-open me-2"></i>
                    <strong>Packaging note:</strong> The outer packaging may be dented or slightly damaged, but the product inside is fully sealed and safe to use.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Related Products ─────────────────────────────── -->
    <?php if (!empty($relatedProducts)): ?>
    <div class="mt-5">
        <h3 class="section-heading mb-1">Related <span>Products</span></h3>
        <div class="section-divider mb-4"></div>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($relatedProducts as $rp): ?>
            <div class="col">
                <div class="product-card">
                    <div class="product-img-wrap">
                        <img src="<?= e(productImage($rp['image_url'], $rp['name'])) ?>"
                             alt="<?= e($rp['name']) ?>" loading="lazy">
                        <?php if ($rp['discount_percentage'] > 0): ?>
                        <div class="discount-badge">-<?= $rp['discount_percentage'] ?>%</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <div class="product-name"><?= e($rp['name']) ?></div>
                        <div class="price-block mt-2">
                            <span class="price-original"><?= formatPrice($rp['original_price']) ?></span>
                            <span class="price-discount"><?= formatPrice($rp['discount_price']) ?></span>
                        </div>
                    </div>
                    <div class="product-actions">
                        <button class="btn-cart" onclick="addToCart(<?= $rp['id'] ?>, this)">
                            <i class="fas fa-cart-plus me-1"></i>Add to Cart
                        </button>
                        <a href="<?= SITE_URL ?>/product-detail.php?id=<?= $rp['id'] ?>" class="btn-detail">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Add to cart with the quantity selected on this page
function addToCartWithQty(productId, btn) {
    const qty = parseInt(document.getElementById('detailQty')?.value || 1);
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';

    fetch('/secondchance/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: productId, quantity: qty })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || `${qty} item(s) added to cart!`, 'success');
            updateCartBadge(data.cart_count);
        } else {
            showToast(data.message || 'Could not add to cart.', 'danger');
        }
    })
    .catch(() => showToast('Connection error.', 'danger'))
    .finally(() => { btn.disabled = false; btn.innerHTML = originalHtml; });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
