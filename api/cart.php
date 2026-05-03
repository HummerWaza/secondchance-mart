<?php
// ============================================================
// SecondChance Mart - Cart AJAX API Endpoint
// Accepts JSON POST requests from the frontend JavaScript
// Returns JSON responses
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Only logged-in customers can use the cart
if (!isLoggedIn() || userRole() !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please login to use the cart.', 'redirect' => SITE_URL . '/login.php']);
    exit;
}

$pdo  = getDB();
$uid  = userId();

// Parse JSON body
$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';

switch ($action) {

    // ── Add item to cart ─────────────────────────────────────
    case 'add':
        $productId = (int)($data['product_id'] ?? 0);
        $quantity  = max(1, (int)($data['quantity'] ?? 1));

        if (!$productId) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }

        // Verify product exists and has stock
        $product = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ? AND status = 'active'");
        $product->execute([$productId]);
        $p = $product->fetch();

        if (!$p) {
            echo json_encode(['success' => false, 'message' => 'Product not found or unavailable.']);
            exit;
        }
        if ($p['stock_quantity'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available. Only ' . $p['stock_quantity'] . ' left.']);
            exit;
        }

        // Check if already in cart — update quantity, or insert new row
        $existing = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $existing->execute([$uid, $productId]);
        $cartRow = $existing->fetch();

        if ($cartRow) {
            // Update quantity (don't exceed stock)
            $newQty = min($cartRow['quantity'] + $quantity, $p['stock_quantity']);
            $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")
                ->execute([$newQty, $cartRow['id']]);
        } else {
            $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,?)")
                ->execute([$uid, $productId, $quantity]);
        }

        echo json_encode([
            'success'    => true,
            'message'    => htmlspecialchars($p['name']) . ' added to cart!',
            'cart_count' => getCartCount(),
        ]);
        break;

    // ── Update item quantity ─────────────────────────────────
    case 'update':
        $cartId  = (int)($data['cart_id']  ?? 0);
        $qty     = (int)($data['quantity'] ?? 1);

        if ($qty < 1) {
            // Remove if quantity set to 0 or less
            $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")
                ->execute([$cartId, $uid]);
            echo json_encode(['success' => true, 'cart_count' => getCartCount()]);
            exit;
        }

        // Get cart item with product info
        $item = $pdo->prepare("
            SELECT c.id, c.product_id, p.stock_quantity, p.discount_price
            FROM cart c JOIN products p ON c.product_id = p.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $item->execute([$cartId, $uid]);
        $row = $item->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
            exit;
        }

        if ($qty > $row['stock_quantity']) {
            echo json_encode(['success' => false, 'message' => 'Only ' . $row['stock_quantity'] . ' available.']);
            exit;
        }

        $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")
            ->execute([$qty, $cartId, $uid]);

        $lineTotal = $qty * $row['discount_price'];
        $subtotal  = getCartTotal();

        echo json_encode([
            'success'    => true,
            'line_total' => CURRENCY . number_format($lineTotal, 2),
            'subtotal'   => CURRENCY . number_format($subtotal, 2),
            'cart_count' => getCartCount(),
        ]);
        break;

    // ── Remove item from cart ────────────────────────────────
    case 'remove':
        $cartId = (int)($data['cart_id'] ?? 0);
        $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")
            ->execute([$cartId, $uid]);
        echo json_encode(['success' => true, 'cart_count' => getCartCount()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
