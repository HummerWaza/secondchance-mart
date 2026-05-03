<?php
// ============================================================
// Admin Panel Shared Header & Sidebar
// Included at the top of every admin page
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('admin');

$currentAdminPage = basename($_SERVER['PHP_SELF']);
$pageTitle = isset($pageTitle) ? $pageTitle . ' | Admin' : 'Admin Panel';

// Get low stock count for sidebar badge
$pdo = getDB();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= " . LOW_STOCK_THRESHOLD . " AND status='active'")->fetchColumn();
$pendingOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ── Admin Sidebar ─────────────────────────────────────── -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <h6>🛒 SecondChance Mart</h6>
        <small>Admin Panel</small>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="<?= SITE_URL ?>/admin/dashboard.php"
           class="<?= $currentAdminPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <div class="nav-section">Catalog</div>
        <a href="<?= SITE_URL ?>/admin/products.php"
           class="<?= in_array($currentAdminPage, ['products.php','add-product.php','edit-product.php']) ? 'active' : '' ?>">
            <i class="fas fa-box-open"></i> Products
            <?php if ($lowStockCount > 0): ?>
            <span class="badge bg-warning text-dark ms-auto"><?= $lowStockCount ?> low</span>
            <?php endif; ?>
        </a>
        <a href="<?= SITE_URL ?>/admin/add-product.php"
           class="<?= $currentAdminPage === 'add-product.php' ? 'active' : '' ?> ps-4"
           style="font-size:13px;">
            <i class="fas fa-plus-circle"></i> Add Product
        </a>

        <div class="nav-section">Orders</div>
        <a href="<?= SITE_URL ?>/admin/orders.php"
           class="<?= in_array($currentAdminPage, ['orders.php','order-detail.php']) ? 'active' : '' ?>">
            <i class="fas fa-shopping-bag"></i> All Orders
            <?php if ($pendingOrders > 0): ?>
            <span class="badge bg-danger ms-auto"><?= $pendingOrders ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Users</div>
        <a href="<?= SITE_URL ?>/admin/customers.php"
           class="<?= $currentAdminPage === 'customers.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="<?= SITE_URL ?>/admin/suppliers.php"
           class="<?= $currentAdminPage === 'suppliers.php' ? 'active' : '' ?>">
            <i class="fas fa-industry"></i> Suppliers
        </a>

        <div class="nav-section">Reports</div>
        <a href="<?= SITE_URL ?>/admin/emails.php"
           class="<?= $currentAdminPage === 'emails.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Email Logs
        </a>

        <div class="nav-section">Account</div>
        <a href="<?= SITE_URL ?>/" target="_blank">
            <i class="fas fa-store"></i> View Store
        </a>
        <a href="<?= SITE_URL ?>/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ── Admin Main Content ─────────────────────────────────── -->
<div class="admin-main">
    <!-- Top Bar -->
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h6 class="mb-0 text-muted"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></h6>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="small text-muted d-none d-md-inline">
                <i class="fas fa-user-shield text-success me-1"></i>
                <?= e($_SESSION['name'] ?? 'Admin') ?>
            </span>
            <a href="<?= SITE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <div class="admin-content pb-0 pt-3">
        <?php
        $successMsg = getFlash('success');
        $errorMsg   = getFlash('error');
        if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= e($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= e($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    </div>

    <div class="admin-content">
