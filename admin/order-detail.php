<?php
// ============================================================
// Admin - Order Detail & Status Update
// This is the most important admin page: confirms orders and
// triggers email notifications to all parties
// ============================================================
$pageTitle = 'Order Detail';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/email.php';

$id = (int)($_GET['id'] ?? 0);

// Fetch the order
$stmt = $pdo->prepare("
    SELECT o.*, u.email AS customer_email,
           CONCAT(c.first_name,' ',c.last_name) AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON u.id = c.user_id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect(SITE_URL . '/admin/orders.php');
}

// Fetch order items
$items = $pdo->prepare("
    SELECT oi.*, p.image_url, p.category_id, cat.name AS category_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE oi.order_id = ?
");
$items->execute([$id]);
$orderItems = $items->fetchAll();

// ── Handle Status Update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $newStatus = $_POST['new_status'];
    $validStatuses = ['pending','confirmed','packed','out_for_delivery','delivered','cancelled'];

    if (in_array($newStatus, $validStatuses)) {
        $oldStatus = $order['status'];
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
            ->execute([$newStatus, $id]);

        // When admin CONFIRMS the order, send all notifications
        if ($newStatus === 'confirmed' && $oldStatus !== 'confirmed') {
            notifyOrderConfirmed($id);

            // Update payment to 'paid' for COD (simulate), card, PayNow
            if ($order['payment_method'] !== 'cod') {
                $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")
                    ->execute([$id]);
                $pdo->prepare("UPDATE payments SET status='completed', paid_at=NOW() WHERE order_id=?")
                    ->execute([$id]);
            }
        }

        // Log delivery status update
        $pdo->prepare("INSERT INTO delivery_status (order_id, status, updated_by) VALUES (?,?,?)")
            ->execute([$id, "Status changed to: $newStatus", userId()]);

        flash('success', "Order status updated to: " . statusLabel($newStatus) .
              ($newStatus === 'confirmed' ? " — All parties have been notified!" : ""));
        redirect(SITE_URL . '/admin/order-detail.php?id=' . $id);
    }
}

// Reload order after potential update
$stmt->execute([$id]);
$order = $stmt->fetch();

// Delivery history
$deliveryHistory = $pdo->prepare("
    SELECT ds.*, u.email
    FROM delivery_status ds
    LEFT JOIN users u ON ds.updated_by = u.id
    WHERE ds.order_id = ?
    ORDER BY ds.created_at ASC
");
$deliveryHistory->execute([$id]);
$deliveryLogs = $deliveryHistory->fetchAll();

// Email notification logs
$emailLogs = $pdo->prepare("
    SELECT * FROM email_notifications WHERE order_id = ? ORDER BY created_at ASC
");
$emailLogs->execute([$id]);
$emails = $emailLogs->fetchAll();

$allStatuses = ['pending','confirmed','packed','out_for_delivery','delivered','cancelled'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">📋 Order <?= e($order['order_number']) ?></h4>
        <span class="status-badge status-<?= e($order['status']) ?> fs-6"><?= statusLabel($order['status']) ?></span>
    </div>
    <a href="orders.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Orders
    </a>
</div>

<div class="row g-4">
    <!-- ── Left: Order Info ──────────────────────────────── -->
    <div class="col-lg-8">
        <!-- Customer & Delivery Info -->
        <div class="bg-white rounded-10 shadow-sm p-4 mb-4">
            <h5 class="fw-bold mb-3 text-green">Customer & Delivery Information</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="text-muted small">CUSTOMER</h6>
                    <div><strong><?= e($order['customer_name'] ?: 'N/A') ?></strong></div>
                    <div class="text-muted small"><?= e($order['customer_email']) ?></div>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted small">ORDER INFO</h6>
                    <div><strong>Date:</strong> <?= date('d M Y, g:i A', strtotime($order['created_at'])) ?></div>
                    <div><strong>Payment:</strong> <span class="text-uppercase"><?= e($order['payment_method']) ?></span>
                         <span class="badge bg-<?= $order['payment_status']==='paid' ? 'success':'warning' ?> ms-1">
                             <?= ucfirst($order['payment_status']) ?>
                         </span>
                    </div>
                </div>
                <div class="col-12">
                    <h6 class="text-muted small">DELIVERY ADDRESS</h6>
                    <address class="mb-0">
                        <strong><?= e($order['shipping_name']) ?></strong><br>
                        <i class="fas fa-phone text-muted me-1"></i><?= e($order['shipping_phone']) ?><br>
                        <?= e($order['shipping_address']) ?><br>
                        <?= e($order['shipping_city']) ?> <?= e($order['shipping_postal']) ?>
                    </address>
                </div>
                <?php if ($order['notes']): ?>
                <div class="col-12">
                    <h6 class="text-muted small">DELIVERY NOTES</h6>
                    <p class="mb-0 small"><?= e($order['notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Items -->
        <div class="table-card mb-4">
            <div class="table-header">
                <h6 class="mb-0 fw-bold">Order Items</h6>
            </div>
            <table class="table mb-0">
                <thead>
                    <tr><th>Product</th><th>Category</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= e(productImage($item['image_url'] ?? '', $item['product_name'])) ?>"
                                     style="width:45px;height:45px;object-fit:cover;border-radius:6px;" alt="">
                                <span class="fw-semibold"><?= e($item['product_name']) ?></span>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= e($item['category_name'] ?? '—') ?></span></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= formatPrice($item['unit_price']) ?></td>
                        <td class="fw-bold"><?= formatPrice($item['quantity'] * $item['unit_price']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-light">
                        <td colspan="4" class="text-end fw-bold">Order Total:</td>
                        <td class="fw-bold text-green fs-5"><?= formatPrice($order['total_amount']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Delivery Status History -->
        <?php if (!empty($deliveryLogs)): ?>
        <div class="bg-white rounded-10 shadow-sm p-4 mb-4">
            <h6 class="fw-bold mb-3">📋 Status History</h6>
            <?php foreach ($deliveryLogs as $log): ?>
            <div class="d-flex gap-3 mb-3">
                <div class="text-center" style="min-width:40px;">
                    <div class="step-circle" style="width:32px;height:32px;font-size:12px;">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <div>
                    <div class="fw-semibold small"><?= e($log['status']) ?></div>
                    <div class="text-muted" style="font-size:11px;">
                        <?= date('d M Y, g:i A', strtotime($log['created_at'])) ?>
                        <?= $log['email'] ? '· ' . e($log['email']) : '' ?>
                    </div>
                    <?php if ($log['notes']): ?>
                    <div class="text-muted small"><?= e($log['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Email Notifications Log -->
        <?php if (!empty($emails)): ?>
        <div class="bg-white rounded-10 shadow-sm p-4">
            <h6 class="fw-bold mb-3">📧 Email Notifications Sent</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>To</th><th>Type</th><th>Subject</th><th>Status</th><th>Sent At</th></tr></thead>
                    <tbody>
                        <?php foreach ($emails as $em): ?>
                        <tr>
                            <td class="small"><?= e($em['recipient_email']) ?></td>
                            <td><span class="badge bg-info"><?= e($em['recipient_type']) ?></span></td>
                            <td class="small"><?= e(mb_substr($em['subject'], 0, 40)) ?>…</td>
                            <td><span class="badge bg-<?= $em['status']==='sent' ? 'success' : 'danger' ?>"><?= $em['status'] ?></span></td>
                            <td class="small text-muted"><?= $em['sent_at'] ? date('d/m g:ia', strtotime($em['sent_at'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Right: Status Update ──────────────────────────── -->
    <div class="col-lg-4">
        <div class="bg-white rounded-10 shadow-sm p-4 mb-4" style="position:sticky;top:80px;">
            <h5 class="fw-bold mb-3 text-green">Update Order Status</h5>

            <!-- Current Status -->
            <div class="text-center mb-4 p-3 bg-light rounded">
                <div class="small text-muted mb-1">Current Status</div>
                <span class="status-badge status-<?= e($order['status']) ?> fs-5 px-3 py-2">
                    <?= statusLabel($order['status']) ?>
                </span>
            </div>

            <!-- Status Update Form -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Change Status To:</label>
                    <select name="new_status" class="form-select" required>
                        <?php foreach ($allStatuses as $st): ?>
                        <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>>
                            <?= statusLabel($st) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Warning when confirming -->
                <div id="confirmWarning" class="alert alert-warning py-2 small d-none">
                    <i class="fas fa-envelope me-1"></i>
                    <strong>Confirming this order will send email notifications to:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li>Customer — confirmation email</li>
                        <li>Supplier — prepare items</li>
                        <li>Warehouse — delivery assignment</li>
                        <li>Admin — completion notice</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-success w-100 fw-bold"
                        onclick="return confirm('Update order status?')">
                    <i class="fas fa-sync me-2"></i>Update Status
                </button>
            </form>

            <!-- Order Summary -->
            <hr>
            <div class="small">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Order Number:</span>
                    <strong><?= e($order['order_number']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Items:</span>
                    <strong><?= count($orderItems) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Total:</span>
                    <strong class="text-green"><?= formatPrice($order['total_amount']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Payment:</span>
                    <strong class="text-uppercase"><?= e($order['payment_method']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show warning when 'confirmed' is selected
document.querySelector('[name="new_status"]')?.addEventListener('change', function() {
    const warn = document.getElementById('confirmWarning');
    if (this.value === 'confirmed') warn.classList.remove('d-none');
    else warn.classList.add('d-none');
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
