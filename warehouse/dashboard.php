<?php
// ============================================================
// SecondChance Mart - Warehouse / Delivery Dashboard
// Delivery staff can: view assigned deliveries, update status
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('delivery');

$pdo = getDB();

// Get warehouse staff info
$staffStmt = $pdo->prepare("SELECT * FROM warehouse_staff WHERE user_id = ?");
$staffStmt->execute([userId()]);
$staff = $staffStmt->fetch();

// ── Handle Delivery Status Update ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $orderId   = (int)$_POST['order_id'];
    $newStatus = $_POST['new_status'];
    $notes     = trim($_POST['notes'] ?? '');

    $validStatuses = ['packed', 'out_for_delivery', 'delivered', 'cancelled'];
    if (in_array($newStatus, $validStatuses)) {
        // Update order status
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
            ->execute([$newStatus, $orderId]);

        // If delivered and COD, mark payment as paid
        if ($newStatus === 'delivered') {
            $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_method='cod'")
                ->execute([$orderId]);
            $pdo->prepare("UPDATE payments SET status='completed', paid_at=NOW() WHERE order_id=? AND method='cod'")
                ->execute([$orderId]);
        }

        // Log the delivery status update
        $pdo->prepare("INSERT INTO delivery_status (order_id, status, notes, updated_by) VALUES (?,?,?,?)")
            ->execute([$orderId, $newStatus, $notes, userId()]);

        flash('success', 'Delivery status updated: ' . statusLabel($newStatus));
        redirect(SITE_URL . '/warehouse/dashboard.php');
    }
}

// ── Load Deliveries ───────────────────────────────────────────
// Active deliveries: confirmed, packed, out for delivery
$activeDeliveries = $pdo->query("
    SELECT o.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name, u.email AS customer_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON u.id = c.user_id
    WHERE o.status IN ('confirmed','packed','out_for_delivery')
    ORDER BY o.updated_at DESC
")->fetchAll();

// Completed deliveries (last 10)
$completedDeliveries = $pdo->query("
    SELECT o.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON u.id = c.user_id
    WHERE o.status IN ('delivered','cancelled')
    ORDER BY o.updated_at DESC
    LIMIT 10
")->fetchAll();

// Stats
$stats = [
    'active'    => count($activeDeliveries),
    'delivered' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn(),
    'today'     => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered' AND DATE(updated_at)=CURDATE()")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Dashboard | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark" style="background:#0d2b45;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">🚚 Warehouse & Delivery — <?= e($_SESSION['name'] ?? 'Staff') ?></a>
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
    <!-- Flash -->
    <?php $msg = getFlash('success'); if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?= e($msg) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="role-header mb-4" style="background:linear-gradient(135deg,#0d2b45,#2980b9);">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="fas fa-truck me-2"></i>Delivery Dashboard</h3>
                <p class="mb-0">
                    <?= e($staff['name'] ?? 'Warehouse Staff') ?>
                    <?php if ($staff && $staff['vehicle_number']): ?>
                    · Vehicle: <?= e($staff['vehicle_number']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="text-end text-white opacity-75 small">
                Today: <?= date('d M Y') ?>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card orange">
                <div class="stat-icon orange"><i class="fas fa-truck"></i></div>
                <div class="stat-number"><?= $stats['active'] ?></div>
                <div class="stat-label">Active Deliveries</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $stats['delivered'] ?></div>
                <div class="stat-label">Total Delivered</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card blue">
                <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-number"><?= $stats['today'] ?></div>
                <div class="stat-label">Delivered Today</div>
            </div>
        </div>
    </div>

    <!-- Active Deliveries -->
    <div class="table-card mb-4">
        <div class="table-header">
            <h6 class="mb-0 fw-bold text-orange">
                <i class="fas fa-truck-moving me-2"></i>Active Delivery Queue (<?= count($activeDeliveries) ?>)
            </h6>
        </div>
        <?php if (empty($activeDeliveries)): ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:3rem;">✅</div>
            <p class="mt-2">No active deliveries. All clear!</p>
        </div>
        <?php else: ?>
        <?php foreach ($activeDeliveries as $o): ?>
        <div class="border-bottom p-4">
            <div class="row g-3 align-items-start">
                <!-- Order Info -->
                <div class="col-md-4">
                    <div class="fw-bold text-green fs-5"><?= e($o['order_number']) ?></div>
                    <div class="text-muted small"><?= date('d M Y, g:i A', strtotime($o['created_at'])) ?></div>
                    <div class="mt-2">
                        <span class="status-badge status-<?= e($o['status']) ?>"><?= statusLabel($o['status']) ?></span>
                    </div>
                    <div class="mt-2 small">
                        <strong><?= $o['item_count'] ?></strong> items ·
                        <strong class="text-green"><?= formatPrice($o['total_amount']) ?></strong>
                    </div>
                    <div class="badge bg-secondary mt-1 text-uppercase"><?= e($o['payment_method']) ?></div>
                </div>

                <!-- Delivery Address -->
                <div class="col-md-4">
                    <h6 class="fw-bold small text-muted">DELIVERY TO:</h6>
                    <address class="mb-0">
                        <strong><?= e($o['shipping_name']) ?></strong><br>
                        <i class="fas fa-phone text-success me-1"></i>
                        <a href="tel:<?= e($o['shipping_phone']) ?>"><?= e($o['shipping_phone']) ?></a><br>
                        <?= e($o['shipping_address']) ?><br>
                        <strong><?= e($o['shipping_city']) ?></strong> <?= e($o['shipping_postal']) ?>
                    </address>
                    <?php if ($o['notes']): ?>
                    <div class="mt-2 p-2 bg-warning bg-opacity-25 rounded small">
                        <i class="fas fa-sticky-note me-1"></i><?= e($o['notes']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Update Status -->
                <div class="col-md-4">
                    <h6 class="fw-bold small text-muted">UPDATE STATUS:</h6>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="new_status" class="form-select form-select-sm mb-2" required>
                            <option value="">Select new status...</option>
                            <option value="packed"           <?= $o['status']==='packed'           ? 'disabled' : '' ?>>📦 Packed</option>
                            <option value="out_for_delivery" <?= $o['status']==='out_for_delivery' ? 'disabled' : '' ?>>🚚 Out for Delivery</option>
                            <option value="delivered">✅ Delivered</option>
                            <option value="cancelled">❌ Cancelled</option>
                        </select>
                        <textarea name="notes" class="form-control form-control-sm mb-2" rows="2"
                                  placeholder="Add delivery notes (optional)..."></textarea>
                        <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">
                            <i class="fas fa-save me-1"></i>Update Delivery
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Completed Deliveries -->
    <?php if (!empty($completedDeliveries)): ?>
    <div class="table-card">
        <div class="table-header">
            <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-green"></i>Recent Completed</h6>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th>Order #</th><th>Customer</th><th>Delivery Address</th><th>Total</th><th>Status</th><th>Updated</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($completedDeliveries as $o): ?>
                    <tr>
                        <td class="fw-bold text-green"><?= e($o['order_number']) ?></td>
                        <td><?= e($o['customer_name'] ?: $o['shipping_name']) ?></td>
                        <td class="small"><?= e($o['shipping_address']) ?>, <?= e($o['shipping_city']) ?></td>
                        <td><?= formatPrice($o['total_amount']) ?></td>
                        <td><span class="status-badge status-<?= e($o['status']) ?>"><?= statusLabel($o['status']) ?></span></td>
                        <td class="small text-muted"><?= date('d/m/y g:ia', strtotime($o['updated_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
