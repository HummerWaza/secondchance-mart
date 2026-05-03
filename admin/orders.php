<?php
// ============================================================
// Admin - Order Management (All Orders)
// ============================================================
$pageTitle = 'Manage Orders';
require_once __DIR__ . '/includes/admin_header.php';

$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];

if ($statusFilter) {
    $where   .= " AND o.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $where   .= " AND (o.order_number LIKE ? OR o.shipping_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
$totalCount->execute($params);
$totalCount = (int)$totalCount->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT o.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name, u.email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON u.id = c.user_id
    $where
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status counts for filter tabs
$statusCountsRaw = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll();
$statusCounts    = array_column($statusCountsRaw, 'cnt', 'status');
$allStatuses     = ['pending','confirmed','packed','out_for_delivery','delivered','cancelled'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">🛍️ Orders <span class="badge bg-success"><?= $totalCount ?></span></h4>
</div>

<!-- Status Filter Tabs -->
<div class="d-flex gap-2 flex-wrap mb-3">
    <a href="orders.php" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-secondary' ?>">
        All <span class="badge bg-secondary ms-1"><?= array_sum($statusCounts) ?></span>
    </a>
    <?php foreach ($allStatuses as $st): ?>
    <a href="orders.php?status=<?= $st ?>"
       class="btn btn-sm btn-<?= $statusFilter === $st ? statusColor($st) : 'outline-secondary' ?>">
        <?= statusLabel($st) ?>
        <span class="badge bg-light text-dark ms-1"><?= $statusCounts[$st] ?? 0 ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<div class="bg-white rounded-10 shadow-sm p-3 mb-4">
    <form method="GET" class="d-flex gap-2">
        <?php if ($statusFilter): ?>
        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <?php endif; ?>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search by order number or customer name..."
               value="<?= e($search) ?>" style="max-width:350px;">
        <button class="btn btn-success btn-sm">Search</button>
        <?php if ($search || $statusFilter): ?>
        <a href="orders.php" class="btn btn-outline-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No orders found</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $ord): ?>
                <tr>
                    <td>
                        <a href="order-detail.php?id=<?= $ord['id'] ?>"
                           class="text-green fw-bold text-decoration-none">
                            <?= e($ord['order_number']) ?>
                        </a>
                    </td>
                    <td>
                        <div><?= e($ord['customer_name'] ?: $ord['shipping_name']) ?></div>
                        <small class="text-muted"><?= e($ord['email']) ?></small>
                    </td>
                    <td class="small text-muted">
                        <?= date('d M Y', strtotime($ord['created_at'])) ?><br>
                        <span><?= date('g:i A', strtotime($ord['created_at'])) ?></span>
                    </td>
                    <td><?= $ord['item_count'] ?> item(s)</td>
                    <td class="fw-bold"><?= formatPrice($ord['total_amount']) ?></td>
                    <td>
                        <span class="badge bg-secondary text-uppercase"><?= e($ord['payment_method']) ?></span><br>
                        <small class="badge <?= $ord['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= ucfirst($ord['payment_status']) ?>
                        </small>
                    </td>
                    <td>
                        <span class="status-badge status-<?= e($ord['status']) ?>">
                            <?= statusLabel($ord['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="order-detail.php?id=<?= $ord['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="p-3 d-flex justify-content-between align-items-center border-top">
        <small class="text-muted"><?= $totalCount ?> total orders | Page <?= $page ?> of <?= $totalPages ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= $statusFilter ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
