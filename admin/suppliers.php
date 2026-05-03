<?php
// ============================================================
// Admin - Supplier Management
// ============================================================
$pageTitle = 'Manage Suppliers';
require_once __DIR__ . '/includes/admin_header.php';

$errors = [];
$editSupplier = null;

// ── Handle Add/Edit Supplier ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $company     = trim($_POST['company_name'] ?? '');
    $contact     = trim($_POST['contact_person'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';

    if ($action === 'add') {
        if (!$company || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6)  {
            $errors[] = 'Company name, valid email, and password (min 6 chars) are required.';
        } else {
            // Check if email exists
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $errors[] = 'Email already registered.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?,?,'supplier')")
                    ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO suppliers (user_id, company_name, contact_person, phone, address) VALUES (?,?,?,?,?)")
                    ->execute([$uid, $company, $contact, $phone, $address]);
                $pdo->commit();
                flash('success', 'Supplier added successfully!');
                redirect(SITE_URL . '/admin/suppliers.php');
            }
        }
    }
}

// ── Load Suppliers ───────────────────────────────────────────
$suppliers = $pdo->query("
    SELECT s.*, u.email, u.is_active, u.created_at,
           (SELECT COUNT(DISTINCT oi.order_id)
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE p.supplier_id = s.id) AS order_count,
           (SELECT COUNT(*) FROM products WHERE supplier_id = s.id AND status='active') AS product_count
    FROM suppliers s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.company_name
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">🏭 Suppliers <span class="badge bg-success"><?= count($suppliers) ?></span></h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="fas fa-plus me-2"></i>Add Supplier
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0"><li><?= implode('</li><li>', array_map('e', $errors)) ?></li></ul></div>
<?php endif; ?>

<div class="table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Products</th>
                    <th>Orders Involved</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No suppliers yet</td></tr>
                <?php endif; ?>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= e($s['company_name']) ?></div>
                        <small class="text-muted"><?= e($s['address'] ?: '—') ?></small>
                    </td>
                    <td><?= e($s['contact_person'] ?: '—') ?></td>
                    <td class="small"><?= e($s['email']) ?></td>
                    <td class="small"><?= e($s['phone'] ?: '—') ?></td>
                    <td><span class="badge bg-primary"><?= $s['product_count'] ?></span></td>
                    <td><span class="badge bg-info"><?= $s['order_count'] ?></span></td>
                    <td>
                        <span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Add New Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Company Name *</label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Login Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password * (min 6 chars)</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Phone</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="fas fa-save me-1"></i>Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
