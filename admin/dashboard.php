<?php
// ============================================================
// SecondChance Mart - Admin Dashboard
// Shows key statistics and recent activity
// ============================================================
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

// ── Dashboard Statistics ─────────────────────────────────────
$stats = [
    'total_orders'    => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending_orders'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
    'total_customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
    'total_sales'     => $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled','pending')")->fetchColumn(),
    'total_products'  => $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
    'low_stock'       => $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= " . LOW_STOCK_THRESHOLD . " AND status='active'")->fetchColumn(),
];

// ── Recent Orders ────────────────────────────────────────────
$recentOrders = $pdo->query("
    SELECT o.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name
    FROM orders o
    LEFT JOIN customers c ON o.user_id = c.user_id
    ORDER BY o.created_at DESC
    LIMIT 8
")->fetchAll();

// ── Low Stock Products ───────────────────────────────────────
$lowStockProducts = $pdo->query("
    SELECT p.*, cat.name AS category_name
    FROM products p
    JOIN categories cat ON p.category_id = cat.id
    WHERE p.stock_quantity <= " . LOW_STOCK_THRESHOLD . "
      AND p.status = 'active'
    ORDER BY p.stock_quantity ASC
    LIMIT 6
")->fetchAll();

// ── Sales by Category (for mini chart) ──────────────────────
$salesByCategory = $pdo->query("
    SELECT cat.name, SUM(oi.quantity * oi.unit_price) AS revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories cat ON p.category_id = cat.id
    GROUP BY cat.id, cat.name
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();

// ── Order Status Counts ──────────────────────────────────────
$statusCounts = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- ── Stats Cards ────────────────────────────────────────── -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-number"><?= number_format($stats['total_orders']) ?></div>
            <div class="stat-label">Total Orders</div>
            <?php if ($stats['pending_orders'] > 0): ?>
            <div class="mt-1">
                <span class="badge bg-warning text-dark"><?= $stats['pending_orders'] ?> pending</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card orange">
            <div class="stat-icon orange"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-number"><?= CURRENCY . number_format($stats['total_sales'], 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= number_format($stats['total_customers']) ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-number"><?= $stats['low_stock'] ?></div>
            <div class="stat-label">Low Stock Items</div>
            <?php if ($stats['low_stock'] > 0): ?>
            <div class="mt-1">
                <a href="<?= SITE_URL ?>/admin/products.php?filter=low_stock" class="badge bg-danger text-white">View all</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Order Status Overview ─────────────────────────────── -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="table-card">
            <div class="table-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie text-green me-2"></i>Order Status Overview</h6>
            </div>
            <div class="p-4">
                <?php
                $allStatuses = ['pending','confirmed','packed','out_for_delivery','delivered','cancelled'];
                $total = max(1, array_sum($statusCounts));
                foreach ($allStatuses as $st):
                    $cnt = $statusCounts[$st] ?? 0;
                    $pct = round(($cnt / $total) * 100);
                    $color = statusColor($st);
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small"><?= statusLabel($st) ?></span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress" style="width:100px;height:8px;">
                            <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="small fw-bold"><?= $cnt ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sales by Category -->
    <div class="col-md-6">
        <div class="table-card">
            <div class="table-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar text-green me-2"></i>Revenue by Category</h6>
            </div>
            <div class="p-4">
                <?php
                $maxRev = max(1, max(array_column($salesByCategory, 'revenue') ?: [1]));
                foreach ($salesByCategory as $sc):
                    $pct = round(($sc['revenue'] / $maxRev) * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small"><?= e($sc['name']) ?></span>
                        <span class="small fw-bold"><?= CURRENCY . number_format($sc['revenue'], 0) ?></span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($salesByCategory)): ?>
                <p class="text-muted text-center small">No sales data yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Orders Table ───────────────────────────────── -->
<div class="table-card mb-4">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="fas fa-clock text-green me-2"></i>Recent Orders</h6>
        <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-success">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No orders yet</td></tr>
                <?php endif; ?>
                <?php foreach ($recentOrders as $ord): ?>
                <tr>
                    <td><a href="order-detail.php?id=<?= $ord['id'] ?>" class="text-green fw-bold text-decoration-none"><?= e($ord['order_number']) ?></a></td>
                    <td><?= e($ord['customer_name'] ?: 'N/A') ?></td>
                    <td class="small text-muted"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                    <td class="fw-bold"><?= formatPrice($ord['total_amount']) ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?= e($ord['payment_method']) ?></span></td>
                    <td><span class="status-badge status-<?= e($ord['status']) ?>"><?= statusLabel($ord['status']) ?></span></td>
                    <td>
                        <a href="order-detail.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Low Stock Alert ───────────────────────────────────── -->
<?php if (!empty($lowStockProducts)): ?>
<div class="table-card">
    <div class="table-header">
        <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>⚠️ Low Stock Alert</h6>
        <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-sm btn-outline-danger">Manage Products</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr><th>Product</th><th>Category</th><th>Stock Left</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockProducts as $lp): ?>
                <tr>
                    <td><?= e($lp['name']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($lp['category_name']) ?></span></td>
                    <td>
                        <span class="badge <?= $lp['stock_quantity'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                            <?= $lp['stock_quantity'] == 0 ? 'Out of Stock' : $lp['stock_quantity'] . ' left' ?>
                        </span>
                    </td>
                    <td>
                        <a href="edit-product.php?id=<?= $lp['id'] ?>" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-edit me-1"></i>Update Stock
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
