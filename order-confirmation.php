<?php
// ============================================================
// SecondChance Mart - Order Confirmation Page
// Shown after successful checkout
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin('customer');

$pdo     = getDB();
$orderId = (int)($_GET['id'] ?? 0);

// Fetch the order (must belong to logged-in user)
$stmt = $pdo->prepare("
    SELECT o.*, u.email AS customer_email,
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON u.id = c.user_id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, userId()]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect(SITE_URL . '/order-history.php');
}

// Fetch order items
$items = $pdo->prepare("
    SELECT oi.*, p.image_url
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items->execute([$orderId]);
$orderItems = $items->fetchAll();

$pageTitle = 'Order Confirmed — ' . $order['order_number'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Banner -->
            <div class="bg-white rounded-10 shadow-sm p-5 text-center mb-4">
                <div class="order-success-icon">🎉</div>
                <h2 class="fw-bold text-green mb-2">Order Placed Successfully!</h2>
                <p class="text-muted fs-5 mb-3">
                    Thank you, <strong><?= e($order['customer_name'] ?: 'Customer') ?></strong>!<br>
                    Your order has been received and is being reviewed.
                </p>

                <!-- Order Number Highlight -->
                <div class="d-inline-block bg-green text-white rounded-10 px-4 py-3 mb-4">
                    <div class="small mb-1">Your Order Number</div>
                    <div class="fs-3 fw-bold"><?= e($order['order_number']) ?></div>
                </div>

                <!-- Order Progress Steps -->
                <div class="order-steps justify-content-center">
                    <div class="order-step">
                        <div class="step-circle active">✓</div>
                        <small>Order Placed</small>
                    </div>
                    <div class="step-line"></div>
                    <div class="order-step">
                        <div class="step-circle">2</div>
                        <small>Confirmed</small>
                    </div>
                    <div class="step-line"></div>
                    <div class="order-step">
                        <div class="step-circle">3</div>
                        <small>Packed</small>
                    </div>
                    <div class="step-line"></div>
                    <div class="order-step">
                        <div class="step-circle">4</div>
                        <small>On the Way</small>
                    </div>
                    <div class="step-line"></div>
                    <div class="order-step">
                        <div class="step-circle">5</div>
                        <small>Delivered</small>
                    </div>
                </div>

                <!-- Email Notification Notice -->
                <div class="alert alert-success py-2 mt-3 text-start">
                    <i class="fas fa-envelope me-2"></i>
                    A confirmation email has been sent to <strong><?= e($order['customer_email']) ?></strong>.
                </div>
            </div>

            <!-- Order Details -->
            <div class="bg-white rounded-10 shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3 text-green"><i class="fas fa-info-circle me-2"></i>Order Details</h5>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Order Number</div>
                        <div class="fw-bold"><?= e($order['order_number']) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Order Date</div>
                        <div class="fw-bold"><?= date('d M Y', strtotime($order['created_at'])) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Payment</div>
                        <div class="fw-bold text-uppercase"><?= e($order['payment_method']) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Status</div>
                        <span class="status-badge status-pending">⏳ Pending</span>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="border-top pt-3 mb-3">
                    <h6 class="fw-bold"><i class="fas fa-map-marker-alt text-green me-2"></i>Delivery Address</h6>
                    <address class="mb-0">
                        <strong><?= e($order['shipping_name']) ?></strong><br>
                        <?= e($order['shipping_phone']) ?><br>
                        <?= e($order['shipping_address']) ?><br>
                        <?= e($order['shipping_city']) ?> <?= e($order['shipping_postal']) ?>
                    </address>
                </div>

                <!-- Items ordered -->
                <div class="border-top pt-3">
                    <h6 class="fw-bold"><i class="fas fa-box text-green me-2"></i>Items Ordered</h6>
                    <?php foreach ($orderItems as $item): ?>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= e(productImage($item['image_url'] ?? '', $item['product_name'])) ?>"
                                 style="width:50px;height:50px;object-fit:cover;border-radius:6px;" alt="">
                            <div>
                                <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                <small class="text-muted">
                                    <?= e($item['quantity']) ?> x <?= formatPrice($item['unit_price']) ?>
                                </small>
                            </div>
                        </div>
                        <strong><?= formatPrice($item['quantity'] * $item['unit_price']) ?></strong>
                    </div>
                    <?php endforeach; ?>

                    <!-- Totals -->
                    <div class="pt-2">
                        <?php
                        $subtotal = array_sum(array_map(
                            fn($i) => $i['quantity'] * $i['unit_price'],
                            $orderItems
                        ));
                        $shipping = $order['total_amount'] - $subtotal;
                        ?>
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span>Subtotal</span><span><?= formatPrice($subtotal) ?></span>
                        </div>
                        <?php if ($shipping > 0): ?>
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span>Delivery Fee</span><span><?= formatPrice($shipping) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top">
                            <span>Total</span>
                            <span class="text-green"><?= formatPrice($order['total_amount']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                <a href="<?= SITE_URL ?>/order-history.php" class="btn btn-success px-4">
                    <i class="fas fa-list me-2"></i>View My Orders
                </a>
                <a href="<?= SITE_URL ?>/products.php" class="btn btn-outline-success px-4">
                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                </a>
                <a href="<?= SITE_URL ?>/" class="btn btn-outline-secondary px-4">
                    <i class="fas fa-home me-2"></i>Go Home
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
