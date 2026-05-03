<?php
// ============================================================
// SecondChance Mart - Checkout Page
// Payment methods: Card, PayNow, Bank Transfer (COD removed)
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';

requireLogin('customer');

$pdo       = getDB();
$cartItems = getCartItems();

if (empty($cartItems)) {
    flash('error', 'Your cart is empty.');
    redirect(SITE_URL . '/cart.php');
}

$subtotal = getCartTotal();
$shipping = $subtotal >= 30 ? 0 : 3.99;
$total    = $subtotal + $shipping;

// Pre-fill from customer profile
$custStmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$custStmt->execute([userId()]);
$profile = $custStmt->fetch() ?: [];

$errors = [];

// ── Handle POST: Place Order ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipName    = trim($_POST['ship_name']    ?? '');
    $shipPhone   = trim($_POST['ship_phone']   ?? '');
    $shipAddress = trim($_POST['ship_address'] ?? '');
    $shipCity    = trim($_POST['ship_city']    ?? '');
    $shipPostal  = trim($_POST['ship_postal']  ?? '');
    $payMethod   = $_POST['payment_method']   ?? '';
    $notes       = trim($_POST['notes'] ?? '');

    if (!$shipName)    $errors[] = 'Full name is required.';
    if (!$shipPhone)   $errors[] = 'Phone number is required.';
    if (!$shipAddress) $errors[] = 'Delivery address is required.';
    if (!$shipCity)    $errors[] = 'City is required.';
    if (!in_array($payMethod, ['card', 'paynow', 'bank_transfer'])) {
        $errors[] = 'Please select a payment method.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $orderNum = generateOrderNumber();
            $pdo->prepare("
                INSERT INTO orders
                    (user_id, order_number, total_amount, payment_method, payment_status,
                     shipping_name, shipping_phone, shipping_address, shipping_city, shipping_postal, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                userId(), $orderNum, $total, $payMethod, 'pending',
                $shipName, $shipPhone, $shipAddress, $shipCity, $shipPostal, $notes,
            ]);
            $orderId = $pdo->lastInsertId();

            $itemStmt  = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price) VALUES (?,?,?,?,?)");
            $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
            foreach ($cartItems as $item) {
                $itemStmt->execute([$orderId, $item['product_id'], $item['name'], $item['quantity'], $item['discount_price']]);
                $stockStmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            }

            $pdo->prepare("INSERT INTO payments (order_id, amount, method) VALUES (?,?,?)")
                ->execute([$orderId, $total, $payMethod]);

            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([userId()]);

            $pdo->commit();

            notifyOrderPlaced($orderId);

            flash('success', 'Order placed successfully!');
            redirect(SITE_URL . '/order-confirmation.php?id=' . $orderId);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Order failed. Please try again. (' . $e->getMessage() . ')';
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1 class="mb-1"><i class="fas fa-lock me-2"></i>Secure Checkout</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/cart.php">Cart</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container pb-5">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><li><?= implode('</li><li>', array_map('htmlspecialchars', $errors)) ?></li></ul>
    </div>
    <?php endif; ?>

    <!-- Progress Steps -->
    <div class="order-steps mb-4">
        <div class="order-step"><div class="step-circle">✓</div><small>Cart</small></div>
        <div class="step-line"></div>
        <div class="order-step"><div class="step-circle active">2</div><small>Checkout</small></div>
        <div class="step-line"></div>
        <div class="order-step"><div class="step-circle">3</div><small>Confirmation</small></div>
    </div>

    <form method="POST" action="">
        <div class="row g-4">
            <!-- ── Left: Shipping + Payment ───────────────────── -->
            <div class="col-lg-7">
                <!-- Shipping Address -->
                <div class="checkout-card">
                    <h5><i class="fas fa-map-marker-alt me-2 text-success"></i>Delivery Address</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Full Name *</label>
                            <input type="text" name="ship_name" class="form-control"
                                   value="<?= e($_POST['ship_name'] ?? trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?>"
                                   required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Phone Number *</label>
                            <input type="tel" name="ship_phone" class="form-control"
                                   value="<?= e($_POST['ship_phone'] ?? $profile['phone'] ?? '') ?>"
                                   placeholder="+65 9123 4567" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Street Address *</label>
                            <input type="text" name="ship_address" class="form-control"
                                   value="<?= e($_POST['ship_address'] ?? $profile['address'] ?? '') ?>"
                                   placeholder="Block/Unit, Street Name" required>
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-semibold small">City *</label>
                            <input type="text" name="ship_city" class="form-control"
                                   value="<?= e($_POST['ship_city'] ?? $profile['city'] ?? 'Singapore') ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold small">Postal Code</label>
                            <input type="text" name="ship_postal" class="form-control"
                                   value="<?= e($_POST['ship_postal'] ?? $profile['postal_code'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Delivery Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="e.g., Leave at door, Ring doorbell..."><?= e($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method (no COD) -->
                <div class="checkout-card">
                    <h5><i class="fas fa-credit-card me-2 text-success"></i>Payment Method</h5>

                    <?php
                    $selPay = $_POST['payment_method'] ?? 'card';
                    $methods = [
                        'card'          => ['💳', 'Credit / Debit Card',    'Visa, MasterCard, AMEX accepted'],
                        'paynow'        => ['📱', 'PayNow / PayLah!',        'Instant payment via Singapore PayNow'],
                        'bank_transfer' => ['🏦', 'Bank Transfer',           'IBG / FAST bank transfer to our DBS account'],
                    ];
                    foreach ($methods as $val => [$icon, $label, $desc]):
                        $isSelected = $selPay === $val;
                    ?>
                    <div class="payment-option <?= $isSelected ? 'selected' : '' ?>"
                         onclick="selectPayment('<?= $val ?>')">
                        <div class="d-flex align-items-center">
                            <input type="radio" name="payment_method" value="<?= $val ?>" id="pay_<?= $val ?>"
                                   class="form-check-input me-3" <?= $isSelected ? 'checked' : '' ?>>
                            <label for="pay_<?= $val ?>" class="d-flex align-items-center gap-3 cursor-pointer mb-0 w-100">
                                <span class="payment-icon"><?= $icon ?></span>
                                <div>
                                    <div class="fw-bold"><?= $label ?></div>
                                    <small class="text-muted"><?= $desc ?></small>
                                </div>
                            </label>
                        </div>

                        <?php if ($val === 'card'): ?>
                        <div id="cardFields" class="mt-3 <?= $isSelected ? '' : 'd-none' ?>">
                            <input type="text" class="form-control mb-2" placeholder="Card Number (demo only)" maxlength="19">
                            <div class="row g-2">
                                <div class="col-6"><input type="text" class="form-control" placeholder="MM/YY"></div>
                                <div class="col-6"><input type="text" class="form-control" placeholder="CVV"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">🔒 Demo only — no real card data is stored.</small>
                        </div>
                        <?php elseif ($val === 'paynow'): ?>
                        <div id="paynowFields" class="mt-3 <?= $isSelected ? '' : 'd-none' ?>">
                            <div class="text-center bg-light rounded p-3">
                                <div style="font-size:2.5rem;">📱</div>
                                <p class="mb-1 fw-bold">PayNow UEN: 202412345A</p>
                                <p class="text-muted small mb-0">Use PayNow or PayLah! to transfer to the above UEN.</p>
                            </div>
                        </div>
                        <?php elseif ($val === 'bank_transfer'): ?>
                        <div id="bankFields" class="mt-3 <?= $isSelected ? '' : 'd-none' ?>">
                            <div class="bg-light rounded p-3">
                                <p class="fw-bold mb-2">🏦 DBS Bank Transfer Details:</p>
                                <table class="table table-sm mb-0 small">
                                    <tr><td class="text-muted">Bank</td><td><strong>DBS Bank Singapore</strong></td></tr>
                                    <tr><td class="text-muted">Account Name</td><td><strong>SecondChance Mart Pte Ltd</strong></td></tr>
                                    <tr><td class="text-muted">Account No.</td><td><strong>012-345678-9</strong></td></tr>
                                    <tr><td class="text-muted">Reference</td><td><strong>Your Order Number</strong></td></tr>
                                </table>
                                <small class="text-muted mt-2 d-block">Demo only. Transfer details are for illustration.</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Right: Order Summary ────────────────────────── -->
            <div class="col-lg-5">
                <div class="order-summary">
                    <h5><i class="fas fa-receipt me-2"></i>Order Summary</h5>

                    <div class="mb-3">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= e(productImage($item['image_url'], $item['name'])) ?>"
                                     style="width:40px;height:40px;object-fit:cover;border-radius:5px;" alt="">
                                <div>
                                    <div class="small fw-semibold"><?= e($item['name']) ?></div>
                                    <div class="small text-muted">x<?= $item['quantity'] ?></div>
                                </div>
                            </div>
                            <span class="fw-bold small"><?= formatPrice($item['line_total']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span class="text-muted">Subtotal</span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="text-muted">Delivery Fee</span>
                        <span class="<?= $shipping == 0 ? 'text-success' : '' ?>">
                            <?= $shipping == 0 ? 'Free 🎉' : formatPrice($shipping) ?>
                        </span>
                    </div>
                    <?php if ($shipping > 0): ?>
                    <div class="small text-muted mb-2">
                        <i class="fas fa-info-circle me-1"></i>Add
                        <?= formatPrice(30 - $subtotal) ?> more for free delivery
                    </div>
                    <?php endif; ?>
                    <div class="summary-total summary-row">
                        <span class="fw-bold">Total</span>
                        <span class="text-green fw-bold fs-5"><?= formatPrice($total) ?></span>
                    </div>

                    <button type="submit" class="btn btn-success w-100 btn-lg fw-bold mt-3">
                        <i class="fas fa-check-circle me-2"></i>Place Order
                    </button>
                    <p class="text-center text-muted small mt-2 mb-0">
                        <i class="fas fa-shield-alt me-1 text-green"></i>Secure checkout. Your info is protected.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function selectPayment(method) {
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    const radio = document.getElementById('pay_' + method);
    if (radio) {
        radio.checked = true;
        radio.closest('.payment-option').classList.add('selected');
    }
    ['cardFields','paynowFields','bankFields'].forEach(id => {
        document.getElementById(id)?.classList.add('d-none');
    });
    const fieldMap = { card: 'cardFields', paynow: 'paynowFields', bank_transfer: 'bankFields' };
    document.getElementById(fieldMap[method])?.classList.remove('d-none');
}
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="payment_method"]:checked');
    if (checked) selectPayment(checked.value);
    else selectPayment('card');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
