<?php
// ============================================================
// Admin - Customer Management
// ============================================================
$pageTitle = 'Manage Customers';
require_once __DIR__ . '/includes/admin_header.php';

// Toggle active status
if (isset($_GET['toggle'])) {
    $toggleId = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'customer'")
        ->execute([$toggleId]);
    flash('success', 'Customer status updated.');
    redirect(SITE_URL . '/admin/customers.php');
}

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * 15;

$where  = "WHERE u.role = 'customer'";
$params = [];
if ($search) {
    $where   .= " AND (u.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$total = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN customers c ON u.id=c.user_id $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / 15);

$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.is_active, u.created_at,
           c.first_name, c.last_name, c.phone, c.city,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
           (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.id AND status != 'cancelled') AS total_spent
    FROM users u
    LEFT JOIN customers c ON u.id = c.user_id
    $where
    ORDER BY u.created_at DESC
    LIMIT 15 OFFSET $offset
");
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">👥 Customers <span class="badge bg-success"><?= $totalCount ?></span></h4>
</div>

<!-- Search -->
<div class="bg-white rounded-10 shadow-sm p-3 mb-4">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search by name or email..." value="<?= e($search) ?>" style="max-width:350px;">
        <button class="btn btn-success btn-sm">Search</button>
        <?php if ($search): ?><a href="customers.php" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Registered</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No customers found</td></tr>
                <?php endif; ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center"
                                 style="width:36px;height:36px;font-size:14px;font-weight:700;">
                                <?= strtoupper(substr($c['first_name'] ?: 'C', 0, 1)) ?>
                            </div>
                            <span class="fw-semibold"><?= e(($c['first_name'] ?: '') . ' ' . ($c['last_name'] ?: '')) ?: 'N/A' ?></span>
                        </div>
                    </td>
                    <td class="small"><?= e($c['email']) ?></td>
                    <td class="small"><?= e($c['phone'] ?: '—') ?></td>
                    <td class="small"><?= e($c['city'] ?: '—') ?></td>
                    <td class="text-center">
                        <span class="badge bg-primary"><?= $c['order_count'] ?></span>
                    </td>
                    <td class="fw-bold text-green"><?= formatPrice($c['total_spent']) ?></td>
                    <td class="small text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <span class="badge bg-<?= $c['is_active'] ? 'success' : 'danger' ?>">
                            <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?toggle=<?= $c['id'] ?>"
                           class="btn btn-sm btn-outline-<?= $c['is_active'] ? 'danger' : 'success' ?>"
                           onclick="return confirm('Change customer status?')">
                            <?= $c['is_active'] ? 'Disable' : 'Enable' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="p-3 border-top">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
