<?php
// ============================================================
// Admin - Edit Product
// ============================================================
$pageTitle = 'Edit Product';
require_once __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$product = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$product->execute([$id]);
$product = $product->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect(SITE_URL . '/admin/products.php');
}

$errors = [];

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
    $status      = $_POST['status'] ?? 'active';
    $discPct     = $origPrice > 0 ? round((($origPrice - $discPrice) / $origPrice) * 100) : 0;

    if (!$name)         $errors[] = 'Name required.';
    if (!$catId)        $errors[] = 'Category required.';
    if ($origPrice <= 0) $errors[] = 'Invalid original price.';
    if ($discPrice <= 0 || $discPrice > $origPrice) $errors[] = 'Invalid discount price.';

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE products SET
                category_id=?, supplier_id=?, name=?, description=?,
                original_price=?, discount_price=?, discount_percentage=?,
                stock_quantity=?, expiry_date=?, image_url=?, deal_type=?, status=?
            WHERE id=?
        ")->execute([$catId, $suppId, $name, $description, $origPrice, $discPrice,
                     $discPct, $stock, $expiry, $imageUrl, $dealType, $status, $id]);

        flash('success', "Product updated successfully!");
        redirect(SITE_URL . '/admin/products.php');
    }
    // Merge POST into product for re-display
    $product = array_merge($product, $_POST);
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, company_name FROM suppliers ORDER BY company_name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">✏️ Edit Product</h4>
    <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0"><li><?= implode('</li><li>', array_map('e', $errors)) ?></li></ul></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST">
            <div class="checkout-card">
                <h5>Edit Product: <?= e($product['name']) ?></h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Product Name *</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($product['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Supplier</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">No Supplier</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= $product['supplier_id'] == $sup['id'] ? 'selected' : '' ?>>
                                <?= e($sup['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Original Price ($)</label>
                        <input type="number" name="original_price" class="form-control"
                               step="0.01" value="<?= e($product['original_price']) ?>"
                               oninput="calcDiscount()" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Discount Price ($)</label>
                        <input type="number" name="discount_price" class="form-control"
                               step="0.01" value="<?= e($product['discount_price']) ?>"
                               oninput="calcDiscount()" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Discount %</label>
                        <input type="text" id="discountPctDisplay" class="form-control bg-light" readonly
                               value="<?= $product['discount_percentage'] ?>%">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Stock Quantity</label>
                        <input type="number" name="stock_quantity" class="form-control"
                               min="0" value="<?= e($product['stock_quantity']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control"
                               value="<?= e($product['expiry_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Deal Type</label>
                        <select name="deal_type" class="form-select">
                            <?php foreach (['general'=>'General Sale','near_expiry'=>'Near Expiry','overstock'=>'Overstock','damaged_pkg'=>'Damaged Packaging','seasonal'=>'Seasonal'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $product['deal_type'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Status</label>
                        <select name="status" class="form-select">
                            <option value="active"   <?= $product['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Image URL</label>
                        <input type="url" name="image_url" class="form-control"
                               value="<?= e($product['image_url'] ?? '') ?>"
                               oninput="previewImage(this.value)">
                        <?php if (!empty($product['image_url'])): ?>
                        <img id="imagePreview" src="<?= e($product['image_url']) ?>"
                             style="max-height:120px;border-radius:6px;margin-top:8px;">
                        <?php else: ?>
                        <img id="imagePreview" src="" style="display:none;max-height:120px;border-radius:6px;margin-top:8px;">
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= e($product['description']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-success px-4 fw-bold">
                    <i class="fas fa-save me-2"></i>Update Product
                </button>
                <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="checkout-card text-center">
            <h6 class="fw-bold text-green mb-3">Current Image</h6>
            <img src="<?= e(productImage($product['image_url'], $product['name'])) ?>"
                 style="width:100%;border-radius:8px;object-fit:cover;max-height:200px;" alt="">
            <div class="mt-3 text-start small text-muted">
                <strong>Product ID:</strong> #<?= $product['id'] ?><br>
                <strong>Created:</strong> <?= date('d M Y', strtotime($product['created_at'])) ?>
            </div>
        </div>
    </div>
</div>

<script>
function calcDiscount() {
    const orig = parseFloat(document.querySelector('[name="original_price"]').value) || 0;
    const disc = parseFloat(document.querySelector('[name="discount_price"]').value) || 0;
    const pct  = orig > 0 ? Math.round(((orig - disc) / orig) * 100) : 0;
    document.getElementById('discountPctDisplay').value = pct + '%';
}
function previewImage(url) {
    const img = document.getElementById('imagePreview');
    if (url) { img.src = url; img.style.display = 'block'; }
    else      { img.style.display = 'none'; }
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
