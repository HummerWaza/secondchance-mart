<?php
// ============================================================
// SecondChance Mart - Supplier Dashboard
// Suppliers can: view their products, see orders needing prep,
// update stock availability
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('supplier');

$pdo = getDB();

// Get supplier record for the logged-in user
$supplierStmt = $pdo->prepare("SELECT * FROM suppliers WHERE user_id = ?");
$supplierStmt->execute([userId()]);
$supplier = $supplierStmt->fetch();

if (!$supplier) {
    flash('error', 'Supplier profile not found.');
    redirect(SITE_URL . '/login.php');
}

$supplierId = $supplier['id'];

// ── Handle Stock Update ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $productId = (int)$_POST['product_id'];
    $newStock  = max(0, (int)$_POST['new_stock']);
    // Only allow updating products that belong to this supplier
    $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ? AND supplier_id = ?")
        ->execute([$newStock, $productId, $supplierId]);
    flash('success', 'Stock updated successfully!');
    redirect(SITE_URL . '/supplier/dashboard.php');
}

// ── Supplier's Products ──────────────────────────────────────
$myProducts = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.supplier_id = ? AND p.status = 'active'
    ORDER BY p.stock_quantity ASC
");
$myProducts->execute([$supplierId]);
$products = $myProducts->fetchAll();

// ── Orders Containing Supplier's Products (confirmed orders) ─
$pendingOrders = $pdo->prepare("
    SELECT DISTINCT o.id, o.order_number, o.status, o.created_at, o.total_amount,
           o.shipping_name, o.shipping_city
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.supplier_id = ?
      AND o.status IN ('confirmed','packed')
    ORDER BY o.created_at DESC
    LIMIT 10
");
$pendingOrders->execute([$supplierId]);
$ordersToPrep = $pendingOrders->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$totalProducts  = count($products);
$lowStockCount  = count(array_filter($products, fn($p) => $p['stock_quantity'] <= LOW_STOCK_THRESHOLD));
$totalOrdersInvolved = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) FROM orders o
    JOIN order_items oi ON o.id=oi.order_id
    JOIN products p ON oi.product_id=p.id
    WHERE p.supplier_id=? AND o.status != 'cancelled'
");
$totalOrdersInvolved->execute([$supplierId]);
$totalOrders = $totalOrdersInvolved->fetchColumn();

$pageTitle = 'Supplier Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar for supplier -->
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">🏭 Supplier Portal — <?= e($supplier['company_name']) ?></a>
        <div class="d-flex gap-2">
            <a href="<?= SITE_URL ?>/" class="btn btn-sm btn-outline-light">
                <i class="fas fa-store me-1"></i>Store
            </a>
            <a href="<?= SITE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- Flash messages -->
    <?php $msg = getFlash('success'); if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= e($msg) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="role-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="fas fa-industry me-2"></i><?= e($supplier['company_name']) ?></h3>
                <p class="mb-0">Contact: <?= e($supplier['contact_person'] ?: '—') ?> | <?= e($_SESSION['email']) ?></p>
            </div>
            <div class="text-end text-white opacity-75 small">
                Supplier ID: #<?= $supplierId ?><br>
                <?= date('d M Y') ?>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-box-open"></i></div>
                <div class="stat-number"><?= $totalProducts ?></div>
                <div class="stat-label">My Products</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card orange">
                <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?= $lowStockCount ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card blue">
                <div class="stat-icon blue"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-number"><?= $totalOrders ?></div>
                <div class="stat-label">Orders Involving My Products</div>
            </div>
        </div>
    </div>

    <!-- Orders to Prepare -->
    <?php if (!empty($ordersToPrep)): ?>
    <div class="table-card mb-4">
        <div class="table-header">
            <h6 class="mb-0 fw-bold text-warning">
                <i class="fas fa-clipboard-list me-2"></i>⚠️ Orders to Prepare (<?= count($ordersToPrep) ?>)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th>Order #</th><th>Date</th><th>Delivery To</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($ordersToPrep as $o): ?>
                    <tr>
                        <td class="fw-bold text-green"><?= e($o['order_number']) ?></td>
                        <td class="small"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                        <td><?= e($o['shipping_name']) ?>, <?= e($o['shipping_city']) ?></td>
                        <td><span class="status-badge status-<?= e($o['status']) ?>"><?= statusLabel($o['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Products with Stock Update -->
    <div class="table-card">
        <div class="table-header">
            <h6 class="mb-0 fw-bold"><i class="fas fa-boxes me-2 text-green"></i>My Products & Stock Management</h6>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Discount Price</th>
                        <th>Expiry</th>
                        <th>Current Stock</th>
                        <th>Update Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No products assigned yet. Contact admin.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $p):
                        $daysLeft = daysUntilExpiry($p['expiry_date']);
                    ?>
                    <tr>
                        <td>
                            <img src="<?= e(productImage($p['image_url'], $p['name'])) ?>"
                                 style="width:45px;height:45px;object-fit:cover;border-radius:6px;" alt="">
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($p['name']) ?></div>
                            <?= getDealBadge($p['deal_type'], 0) ?>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= e($p['category_name']) ?></span></td>
                        <td class="text-green fw-bold"><?= formatPrice($p['discount_price']) ?></td>
                        <td class="small">
                            <?php if ($p['expiry_date']): ?>
                                <span class="<?= $daysLeft !== null && $daysLeft <= 3 ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('d/m/Y', strtotime($p['expiry_date'])) ?>
                                    <?php if ($daysLeft !== null && $daysLeft <= 7): ?>
                                    <br><small>(<?= $daysLeft ?>d left!)</small>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $p['stock_quantity'] == 0 ? 'bg-danger' : ($p['stock_quantity'] <= LOW_STOCK_THRESHOLD ? 'bg-warning text-dark' : 'bg-success') ?> fs-6">
                                <?= $p['stock_quantity'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="update_stock" value="1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <input type="number" name="new_stock" class="form-control form-control-sm"
                                       value="<?= $p['stock_quantity'] ?>" min="0"
                                       style="width:80px;">
                                <button type="submit" class="btn btn-sm btn-success" title="Update">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
