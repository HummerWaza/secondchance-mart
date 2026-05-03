<?php
// ============================================================
// Admin - Product Management (List + Delete)
// ============================================================
$pageTitle = 'Manage Products';
require_once __DIR__ . '/includes/admin_header.php';

// ── Handle Delete ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    // Soft-delete by setting status to inactive
    $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?")
        ->execute([$deleteId]);
    flash('success', 'Product removed successfully.');
    redirect(SITE_URL . '/admin/products.php');
}

// ── Filters ──────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$catFilter   = (int)($_GET['category'] ?? 0);
$dealFilter  = $_GET['deal'] ?? '';
$stockFilter = $_GET['filter'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 15;
$offset      = ($page - 1) * $perPage;

$where  = "WHERE p.status = 'active'";
$params = [];

if ($search) {
    $where   .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}
if ($catFilter) {
    $where   .= " AND p.category_id = ?";
    $params[] = $catFilter;
}
if ($dealFilter) {
    $where   .= " AND p.deal_type = ?";
    $params[] = $dealFilter;
}
if ($stockFilter === 'low_stock') {
    $where .= " AND p.stock_quantity <= " . LOW_STOCK_THRESHOLD;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id=c.id $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, s.company_name AS supplier_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    $where
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<!-- Header Row -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">📦 Products <span class="badge bg-success"><?= $totalCount ?></span></h4>
    <a href="<?= SITE_URL ?>/admin/add-product.php" class="btn btn-success">
        <i class="fas fa-plus me-2"></i>Add New Product
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-10 shadow-sm p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search by product name..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select form-select-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="deal" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="near_expiry"  <?= $dealFilter==='near_expiry'  ? 'selected' : '' ?>>Near Expiry</option>
                <option value="overstock"    <?= $dealFilter==='overstock'    ? 'selected' : '' ?>>Overstock</option>
                <option value="damaged_pkg"  <?= $dealFilter==='damaged_pkg'  ? 'selected' : '' ?>>Damaged Pkg</option>
                <option value="seasonal"     <?= $dealFilter==='seasonal'     ? 'selected' : '' ?>>Seasonal</option>
                <option value="general"      <?= $dealFilter==='general'      ? 'selected' : '' ?>>General</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="filter" class="form-select form-select-sm">
                <option value="">All Stock Levels</option>
                <option value="low_stock" <?= $stockFilter==='low_stock' ? 'selected' : '' ?>>Low Stock</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-success btn-sm w-100">Filter</button>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Original</th>
                    <th>Discount</th>
                    <th>%</th>
                    <th>Stock</th>
                    <th>Expiry</th>
                    <th>Deal Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No products found</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p):
                    $daysLeft = daysUntilExpiry($p['expiry_date']);
                ?>
                <tr>
                    <td>
                        <img src="<?= e(productImage($p['image_url'], $p['name'])) ?>"
                             style="width:45px;height:45px;object-fit:cover;border-radius:6px;"
                             alt="">
                    </td>
                    <td>
                        <div class="fw-semibold" style="max-width:180px;">
                            <?= e($p['name']) ?>
                        </div>
                        <small class="text-muted"><?= e($p['supplier_name'] ?? '—') ?></small>
                    </td>
                    <td><span class="badge bg-light text-dark"><?= e($p['category_name']) ?></span></td>
                    <td><?= formatPrice($p['original_price']) ?></td>
                    <td class="text-green fw-bold"><?= formatPrice($p['discount_price']) ?></td>
                    <td><span class="badge bg-warning text-dark">-<?= $p['discount_percentage'] ?>%</span></td>
                    <td>
                        <span class="badge <?= $p['stock_quantity'] == 0 ? 'bg-danger' : ($p['stock_quantity'] <= LOW_STOCK_THRESHOLD ? 'bg-warning text-dark' : 'bg-success') ?>">
                            <?= $p['stock_quantity'] ?>
                        </span>
                    </td>
                    <td class="small">
                        <?php if ($p['expiry_date']): ?>
                            <span class="<?= $daysLeft !== null && $daysLeft <= 3 ? 'text-danger fw-bold' : '' ?>">
                                <?= date('d/m/Y', strtotime($p['expiry_date'])) ?>
                                <?php if ($daysLeft !== null && $daysLeft <= 7): ?>
                                <br><span class="text-danger">(<?= $daysLeft ?>d left)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= getDealBadge($p['deal_type'], 0) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="edit-product.php?id=<?= $p['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Remove this product?')">
                                <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Remove">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="p-3 d-flex justify-content-between align-items-center border-top">
        <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $catFilter ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
