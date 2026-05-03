<?php
// ============================================================
// SecondChance Mart - Customer Order History
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin('customer');

$pdo = getDB();

// Fetch all orders for this customer, newest first
$stmt = $pdo->prepare("
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([userId()]);
$orders = $stmt->fetchAll();

// If a specific order ID is in the URL, load its items for the detail panel
$detailOrder = null;
$detailItems = [];
if (isset($_GET['view'])) {
    $viewId = (int)$_GET['view'];
    $ds     = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $ds->execute([$viewId, userId()]);
    $detailOrder = $ds->fetch();
    if ($detailOrder) {
        $di = $pdo->prepare("
            SELECT oi.*, p.image_url
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $di->execute([$viewId]);
        $detailItems = $di->fetchAll();
    }
}

$pageTitle = 'My Orders';
include __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1 class="mb-1"><i class="fas fa-history me-2"></i>My Orders</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/">Home</a></li>
                <li class="breadcrumb-item active">My Orders</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container pb-5">
    <?php if (empty($orders)): ?>
    <div class="text-center py-5 bg-white rounded-10 shadow-sm">
        <div style="font-size:4rem;">📦</div>
        <h4 class="mt-3">No orders yet</h4>
        <p class="text-muted">You haven't placed any orders yet. Start shopping!</p>
        <a href="<?= SITE_URL ?>/products.php" class="btn btn-success btn-lg">
            <i class="fas fa-shopping-bag me-2"></i>Browse Products
        </a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <!-- ── Orders List ──────────────────────────── -->
        <div class="col-lg-<?= $detailOrder ? '5' : '12' ?>">
            <div class="table-card">
                <div class="table-header">
                    <h5 class="mb-0 fw-bold">All Orders (<?= count($orders) ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                            <tr class="<?= $detailOrder && $detailOrder['id'] === $ord['id'] ? 'table-success' : '' ?>">
                                <td>
                                    <a href="?view=<?= $ord['id'] ?>" class="text-green fw-bold text-decoration-none">
                                        <?= e($ord['order_number']) ?>
                                    </a>
                                </td>
                                <td class="small text-muted"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                                <td><?= $ord['item_count'] ?> item(s)</td>
                                <td class="fw-bold"><?= formatPrice($ord['total_amount']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= e($ord['status']) ?>">
                                        <?= statusLabel($ord['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?view=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Order Detail Panel ───────────────────── -->
        <?php if ($detailOrder): ?>
        <div class="col-lg-7">
            <div class="bg-white rounded-10 shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-green">Order <?= e($detailOrder['order_number']) ?></h5>
                        <small class="text-muted">
                            Placed on <?= date('d M Y, g:i A', strtotime($detailOrder['created_at'])) ?>
                        </small>
                    </div>
                    <span class="status-badge status-<?= e($detailOrder['status']) ?> fs-6">
                        <?= statusLabel($detailOrder['status']) ?>
                    </span>
                </div>

                <!-- Delivery Info -->
                <div class="bg-light rounded p-3 mb-3">
                    <h6 class="fw-bold mb-2"><i class="fas fa-map-marker-alt text-green me-2"></i>Delivery Address</h6>
                    <address class="mb-0 small">
                        <strong><?= e($detailOrder['shipping_name']) ?></strong><br>
                        <?= e($detailOrder['shipping_phone']) ?><br>
                        <?= e($detailOrder['shipping_address']) ?>,
                        <?= e($detailOrder['shipping_city']) ?> <?= e($detailOrder['shipping_postal']) ?>
                    </address>
                </div>

                <!-- Payment -->
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="text-muted">Payment Method:</span>
                    <strong class="text-uppercase"><?= e($detailOrder['payment_method']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="text-muted">Payment Status:</span>
                    <span class="badge bg-<?= $detailOrder['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                        <?= ucfirst($detailOrder['payment_status']) ?>
                    </span>
                </div>

                <!-- Items -->
                <h6 class="fw-bold mb-3"><i class="fas fa-box text-green me-2"></i>Items</h6>
                <?php foreach ($detailItems as $item): ?>
                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                    <div class="d-flex gap-3 align-items-center">
                        <img src="<?= e(productImage($item['image_url'] ?? '', $item['product_name'])) ?>"
                             style="width:55px;height:55px;object-fit:cover;border-radius:8px;" alt="">
                        <div>
                            <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                            <small class="text-muted">
                                <?= $item['quantity'] ?> × <?= formatPrice($item['unit_price']) ?>
                            </small>
                        </div>
                    </div>
                    <strong><?= formatPrice($item['quantity'] * $item['unit_price']) ?></strong>
                </div>
                <?php endforeach; ?>

                <!-- Total -->
                <div class="d-flex justify-content-between fw-bold border-top pt-2 fs-5">
                    <span>Total Paid</span>
                    <span class="text-green"><?= formatPrice($detailOrder['total_amount']) ?></span>
                </div>

                <!-- Notes -->
                <?php if ($detailOrder['notes']): ?>
                <div class="mt-3 p-2 bg-light rounded small">
                    <strong>Notes:</strong> <?= e($detailOrder['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
