<?php
// ============================================================
// SecondChance Mart - Helper Functions
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ── Authentication ──────────────────────────────────────────

/**
 * Check if a user is logged in and optionally enforce a role.
 * Redirects to login page if not authenticated.
 */
function requireLogin(string $role = ''): array {
    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . SITE_URL . '/login.php?redirect=' . $redirect);
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . SITE_URL . '/login.php?error=unauthorized');
        exit;
    }
    return $_SESSION;
}

/** Returns true if user is logged in */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/** Returns current user role or empty string */
function userRole(): string {
    return $_SESSION['role'] ?? '';
}

/** Returns current user ID or 0 */
function userId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

// ── Cart Helpers ────────────────────────────────────────────

/**
 * Get total item count in the logged-in user's cart.
 * Used for the cart badge in the navbar.
 */
function getCartCount(): int {
    if (!isLoggedIn() || userRole() !== 'customer') return 0;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?");
    $stmt->execute([userId()]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get all cart items for the current user with product details.
 * Returns an array of rows with product info and line totals.
 */
function getCartItems(): array {
    if (!isLoggedIn()) return [];
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT c.id AS cart_id, c.quantity,
               p.id AS product_id, p.name, p.discount_price, p.original_price,
               p.image_url, p.stock_quantity,
               (c.quantity * p.discount_price) AS line_total
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ? AND p.status = 'active'
        ORDER BY c.added_at DESC
    ");
    $stmt->execute([userId()]);
    return $stmt->fetchAll();
}

/**
 * Get cart subtotal for the current user.
 */
function getCartTotal(): float {
    $items = getCartItems();
    return array_sum(array_column($items, 'line_total'));
}

// ── Product Helpers ─────────────────────────────────────────

/**
 * Returns a badge HTML string for the product's deal type.
 */
function getDealBadge(string $dealType, int $discountPct): string {
    $badges = [
        'near_expiry'  => ['danger',  '⏰ Near Expiry'],
        'overstock'    => ['warning', '📦 Overstock'],
        'damaged_pkg'  => ['info',    '📋 Pkg Damaged'],
        'seasonal'     => ['primary', '🍂 Seasonal'],
        'general'      => ['success', '💰 Sale'],
    ];
    $b = $badges[$dealType] ?? $badges['general'];
    $pct = $discountPct > 0 ? " -{$discountPct}%" : '';
    return '<span class="badge bg-' . $b[0] . '">' . $b[1] . $pct . '</span>';
}

/**
 * Calculate how many days until a product expires.
 * Returns null if no expiry date.
 */
function daysUntilExpiry(?string $expiryDate): ?int {
    if (!$expiryDate) return null;
    $today  = new DateTime('today');
    $expiry = new DateTime($expiryDate);
    $diff   = $today->diff($expiry);
    return $diff->invert ? -$diff->days : $diff->days;  // negative = already expired
}

/**
 * Format a date string nicely.
 */
function formatDate(string $date): string {
    return date('d M Y', strtotime($date));
}

/**
 * Format currency with the site symbol.
 */
function formatPrice(float $price): string {
    return CURRENCY . number_format($price, 2);
}

// ── Order Helpers ───────────────────────────────────────────

/**
 * Generate a unique order number like SCM-20240001.
 */
function generateOrderNumber(): string {
    $pdo  = getDB();
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return 'SCM-' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Map order status to a Bootstrap color class.
 */
function statusColor(string $status): string {
    $map = [
        'pending'          => 'warning',
        'confirmed'        => 'primary',
        'packed'           => 'info',
        'out_for_delivery' => 'orange',
        'delivered'        => 'success',
        'cancelled'        => 'danger',
    ];
    return $map[$status] ?? 'secondary';
}

/**
 * Returns human-readable status label.
 */
function statusLabel(string $status): string {
    $map = [
        'pending'          => 'Pending',
        'confirmed'        => 'Confirmed',
        'packed'           => 'Packed',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'cancelled'        => 'Cancelled',
    ];
    return $map[$status] ?? ucfirst($status);
}

// ── Security Helpers ────────────────────────────────────────

/** Safely output a variable as HTML (prevents XSS) */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Generate a CSRF token and store in session */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify CSRF token from POST request */
function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/** Redirect to a URL */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/** Store a flash message in session */
function flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

/** Retrieve and clear a flash message */
function getFlash(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ── Image Helper ────────────────────────────────────────────

/** Returns a fallback image URL if product has no image */
function productImage(string $url, string $altText = ''): string {
    if (empty($url)) {
        $encoded = urlencode($altText ?: 'Product');
        return "https://placehold.co/400x300/28a745/ffffff?text=" . $encoded;
    }
    return $url;
}
