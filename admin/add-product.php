<?php
// ============================================================
// Admin - Add New Product
// ============================================================
$pageTitle = 'Add Product';
require_once __DIR__ . '/includes/admin_header.php';

$errors = [];

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $catId       = (int)($_POST['category_id'] ?? 0);
    $suppId      = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $origPrice   = (float)($_POST['original_price'] ?? 0);
    $discPrice   = (float)($_POST['discount_price'] ?? 0);
    $stock       = (int)($_POST['stock_quantity'] ?? 0);
    $expiry      = trim($_POST['expiry_date'] ?? '') ?: null;
    $imageUrl    = trim($_POST['image_url'] ?? '');
    $dealType    = $_POST['deal_type'] ?? 'general';
    $description = trim($_POST['description'] ?? '');

    // Compute discount percentage
    $discPct = $origPrice > 0 ? round((($origPrice - $discPrice) / $origPrice) * 100) : 0;

    // Validation
    if (!$name)         $errors[] = 'Product name is required.';
    if (!$catId)        $errors[] = 'Category is required.';
    if ($origPrice <= 0) $errors[] = 'Original price must be greater than 0.';
    if ($discPrice <= 0) $errors[] = 'Discount price must be greater than 0.';
    if ($discPrice > $origPrice) $errors[] = 'Discount price cannot exceed original price.';

    if (empty($errors)) {
        $pdo->prepare("
            INSERT INTO products
                (supplier_id, category_id, name, description, original_price, discount_price,
                 discount_percentage, stock_quantity, expiry_date, image_url, deal_type)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([$suppId, $catId, $name, $description, $origPrice, $discPrice,
                     $discPct, $stock, $expiry, $imageUrl, $dealType]);

        flash('success', "Product '$name' added successfully!");
        redirect(SITE_URL . '/admin/products.php');
    }
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, company_name FROM suppliers ORDER BY company_name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">➕ Add New Product</h4>
    <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Products
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0"><li><?= implode('</li><li>', array_map('htmlspecialchars', $errors)) ?></li></ul></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Main Form -->
    <div class="col-lg-8">
        <form method="POST" action="">
            <div class="checkout-card">
                <h5>Product Information</h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Product Name *</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Supplier</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">Select Supplier (optional)</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= ($_POST['supplier_id'] ?? '') == $sup['id'] ? 'selected' : '' ?>>
                                <?= e($sup['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Original Price ($) *</label>
                        <input type="number" name="original_price" class="form-control"
                               step="0.01" min="0.01"
                               value="<?= e($_POST['original_price'] ?? '') ?>"
                               oninput="calcDiscount()" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Discount Price ($) *</label>
                        <input type="number" name="discount_price" class="form-control"
                               step="0.01" min="0.01"
                               value="<?= e($_POST['discount_price'] ?? '') ?>"
                               oninput="calcDiscount()" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Discount %</label>
                        <input type="text" id="discountPctDisplay" class="form-control bg-light" readonly
                               placeholder="Auto-calculated">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Stock Quantity *</label>
                        <input type="number" name="stock_quantity" class="form-control"
                               min="0" value="<?= e($_POST['stock_quantity'] ?? 0) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control"
                               value="<?= e($_POST['expiry_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Deal Type</label>
                        <select name="deal_type" class="form-select">
                            <option value="general"     <?= ($_POST['deal_type'] ?? 'general') === 'general'     ? 'selected' : '' ?>>General Sale</option>
                            <option value="near_expiry" <?= ($_POST['deal_type'] ?? '') === 'near_expiry' ? 'selected' : '' ?>>Near Expiry</option>
                            <option value="overstock"   <?= ($_POST['deal_type'] ?? '') === 'overstock'   ? 'selected' : '' ?>>Overstock</option>
                            <option value="damaged_pkg" <?= ($_POST['deal_type'] ?? '') === 'damaged_pkg' ? 'selected' : '' ?>>Damaged Packaging</option>
                            <option value="seasonal"    <?= ($_POST['deal_type'] ?? '') === 'seasonal'    ? 'selected' : '' ?>>Seasonal</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Product Image URL</label>
                        <input type="url" name="image_url" class="form-control"
                               placeholder="https://images.unsplash.com/..."
                               value="<?= e($_POST['image_url'] ?? '') ?>"
                               oninput="previewImage(this.value)">
                        <div class="mt-2">
                            <img id="imagePreview" src="" alt=""
                                 style="max-height:120px;border-radius:6px;display:none;">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Description</label>
                        <textarea name="description" class="form-control" rows="4"
                                  placeholder="Describe the product, deal reason, quality notes..."><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-success px-4 fw-bold">
                    <i class="fas fa-save me-2"></i>Save Product
                </button>
                <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Tips Sidebar -->
    <div class="col-lg-4">
        <div class="checkout-card">
            <h6 class="fw-bold text-green">💡 Product Tips</h6>
            <ul class="small text-muted ps-3">
                <li class="mb-2">Set <strong>Expiry Date</strong> for perishable items. The system shows expiry warnings to customers automatically.</li>
                <li class="mb-2">Use <strong>Deal Type</strong> to categorize why this product is discounted.</li>
                <li class="mb-2">Discount percentage is calculated automatically from original and discount prices.</li>
                <li class="mb-2">Use Unsplash image URLs: <code>https://images.unsplash.com/photo-ID?w=400</code></li>
                <li>Low stock warning triggers when quantity ≤ <?= LOW_STOCK_THRESHOLD ?> units.</li>
            </ul>
        </div>
        <div class="checkout-card mt-3">
            <h6 class="fw-bold text-green">🖼️ Free Image Sources</h6>
            <ul class="small text-muted ps-3">
                <li><a href="https://unsplash.com" target="_blank">Unsplash.com</a></li>
                <li><a href="https://pexels.com" target="_blank">Pexels.com</a></li>
                <li>Placeholder: <code>https://placehold.co/400x300</code></li>
            </ul>
        </div>
    </div>
</div>

<script>
function calcDiscount() {
    const orig = parseFloat(document.querySelector('[name="original_price"]').value) || 0;
    const disc = parseFloat(document.querySelector('[name="discount_price"]').value) || 0;
    const pct  = orig > 0 ? Math.round(((orig - disc) / orig) * 100) : 0;
    document.getElementById('discountPctDisplay').value = pct > 0 ? pct + '%' : '—';
}
function previewImage(url) {
    const img = document.getElementById('imagePreview');
    if (url) { img.src = url; img.style.display = 'block'; }
    else      { img.style.display = 'none'; }
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
