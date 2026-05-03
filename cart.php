<?php
// ============================================================
// SecondChance Mart - Shopping Cart Page
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Cart is only for logged-in customers
requireLogin('customer');

$cartItems = getCartItems();
$subtotal  = getCartTotal();
$shipping  = $subtotal >= 30 ? 0 : 3.99;
$total     = $subtotal + $shipping;

$pageTitle = 'Shopping Cart';
include __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1 class="mb-1"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/">Home</a></li>
                <li class="breadcrumb-item active">Cart</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container pb-5">
    <?php if (empty($cartItems)): ?>
    <!-- Empty Cart State -->
    <div id="cart-empty" class="text-center py-5 bg-white rounded-10 shadow-sm">
        <div style="font-size:5rem;">🛒</div>
        <h3 class="mt-3 fw-bold">Your cart is empty</h3>
        <p class="text-muted">Looks like you haven't added anything yet.<br>Explore our clearance deals!</p>
        <a href="<?= SITE_URL ?>/products.php" class="btn btn-success btn-lg px-5 mt-2">
            <i class="fas fa-shopping-bag me-2"></i>Start Shopping
        </a>
    </div>

    <?php else: ?>
    <div id="cart-content">
        <div class="row g-4">
            <!-- ── Cart Items Table ─────────────────────── -->
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="table-header">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-green"></i>Your Items (<?= count($cartItems) ?>)</h5>
                        <a href="<?= SITE_URL ?>/products.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-plus me-1"></i>Add More
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table cart-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:50%">Product</th>
                                    <th class="text-center">Price</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-center">Subtotal</th>
                                    <th class="text-center">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                <tr data-cart="<?= $item['cart_id'] ?>">
                                    <!-- Product Info -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= e(productImage($item['image_url'], $item['name'])) ?>"
                                                 alt="<?= e($item['name']) ?>"
                                                 class="cart-item-img"
                                                 onerror="this.src='https://placehold.co/70x70/27ae60/fff?text=?'">
                                            <div>
                                                <div class="fw-semibold"><?= e($item['name']) ?></div>
                                                <?php if ($item['stock_quantity'] <= LOW_STOCK_THRESHOLD): ?>
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                    Only <?= $item['stock_quantity'] ?> left in stock!
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Price -->
                                    <td class="text-center">
                                        <span class="text-green fw-bold"><?= formatPrice($item['discount_price']) ?></span>
                                    </td>
                                    <!-- Quantity -->
                                    <td class="text-center">
                                        <div class="qty-control justify-content-center">
                                            <button class="qty-btn" data-action="minus">−</button>
                                            <input type="number"
                                                   class="qty-input"
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1"
                                                   max="<?= $item['stock_quantity'] ?>"
                                                   data-cart-id="<?= $item['cart_id'] ?>">
                                            <button class="qty-btn" data-action="plus">+</button>
                                        </div>
                                    </td>
                                    <!-- Line Total -->
                                    <td class="text-center">
                                        <strong class="text-green" data-line="<?= $item['cart_id'] ?>">
                                            <?= formatPrice($item['line_total']) ?>
                                        </strong>
                                    </td>
                                    <!-- Remove -->
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-danger"
                                                onclick="removeFromCart(<?= $item['cart_id'] ?>)"
                                                title="Remove item">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Continue Shopping -->
                <div class="mt-3">
                    <a href="<?= SITE_URL ?>/products.php" class="btn btn-outline-success">
                        <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>

            <!-- ── Order Summary ────────────────────────── -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h5><i class="fas fa-receipt me-2"></i>Order Summary</h5>

                    <div class="summary-row">
                        <span class="text-muted">Subtotal</span>
                        <span id="cart-subtotal"><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="text-muted">Delivery Fee</span>
                        <span class="<?= $shipping == 0 ? 'text-success' : '' ?>">
                            <?= $shipping == 0 ? '🎉 Free' : formatPrice($shipping) ?>
                        </span>
                    </div>

                    <?php if ($subtotal < 30): ?>
                    <div class="alert alert-info py-2 small">
                        <i class="fas fa-info-circle me-1"></i>
                        Add <?= formatPrice(30 - $subtotal) ?> more for FREE delivery!
                    </div>
                    <?php endif; ?>

                    <div class="summary-total summary-row">
                        <span>Total</span>
                        <span><?= formatPrice($total) ?></span>
                    </div>

                    <a href="<?= SITE_URL ?>/checkout.php"
                       class="btn btn-success w-100 btn-lg fw-bold mt-3">
                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                    </a>

                    <!-- Payment Icons -->
                    <div class="text-center mt-3">
                        <small class="text-muted d-block mb-2">We accept:</small>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-light text-dark border px-3 py-2">💵 COD</span>
                            <span class="badge bg-light text-dark border px-3 py-2">💳 Card</span>
                            <span class="badge bg-light text-dark border px-3 py-2">📱 PayNow</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
