<?php
// ============================================================
// SecondChance Mart - Shared HTML Header & Navigation
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$cartCount  = getCartCount();
$isLoggedIn = isLoggedIn();
$userRole   = userRole();
$currentPage = basename($_SERVER['PHP_SELF']);

// Determine dashboard link based on role
$dashboardLink = '#';
if ($userRole === 'admin')     $dashboardLink = SITE_URL . '/admin/dashboard.php';
if ($userRole === 'supplier')  $dashboardLink = SITE_URL . '/supplier/dashboard.php';
if ($userRole === 'delivery')  $dashboardLink = SITE_URL . '/warehouse/dashboard.php';
if ($userRole === 'customer')  $dashboardLink = SITE_URL . '/order-history.php';

// Page title - individual pages can override $pageTitle before including header
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ── Top Info Bar ─────────────────────────────────────── -->
<div class="top-bar bg-success text-white py-1">
    <div class="container d-flex justify-content-between align-items-center">
        <small><i class="fas fa-leaf me-1"></i> Reducing food waste since 2024 | Free delivery on orders over $30</small>
        <small>
            <?php if ($isLoggedIn): ?>
                <i class="fas fa-user-circle me-1"></i>
                Welcome, <?= e($_SESSION['name'] ?? 'User') ?>
                &nbsp;|&nbsp;
                <a href="<?= SITE_URL ?>/logout.php" class="text-white text-decoration-none">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/login.php" class="text-white text-decoration-none me-2">
                    <i class="fas fa-sign-in-alt me-1"></i>Login
                </a>
                <a href="<?= SITE_URL ?>/register.php" class="text-white text-decoration-none">
                    <i class="fas fa-user-plus me-1"></i>Register
                </a>
            <?php endif; ?>
        </small>
    </div>
</div>

<!-- ── Main Navbar ──────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand d-flex align-items-center" href="<?= SITE_URL ?>/">
            <span class="brand-icon me-2">🛒</span>
            <div>
                <span class="brand-name"><?= SITE_NAME ?></span>
                <small class="brand-tagline d-block"><?= SITE_TAGLINE ?></small>
            </div>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav Links -->
        <div class="collapse navbar-collapse" id="mainNav">
            <!-- Search Bar -->
            <form class="d-flex mx-auto my-2 my-lg-0" action="<?= SITE_URL ?>/products.php" method="GET" style="width:350px;">
                <div class="input-group">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search products..."
                           value="<?= e($_GET['search'] ?? '') ?>">
                    <button class="btn btn-warning" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <!-- Right Nav Items -->
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>"
                       href="<?= SITE_URL ?>/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>"
                       href="<?= SITE_URL ?>/products.php">Products</a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <!-- Dashboard link (varies by role) -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $dashboardLink ?>">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            <?= $userRole === 'customer' ? 'My Orders' : 'Dashboard' ?>
                        </a>
                    </li>

                    <!-- Cart (customers only) -->
                    <?php if ($userRole === 'customer'): ?>
                    <li class="nav-item">
                        <a class="nav-link cart-link position-relative" href="<?= SITE_URL ?>/cart.php">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                            <?php if ($cartCount > 0): ?>
                                <span class="cart-badge badge bg-warning text-dark position-absolute top-0 start-100 translate-middle">
                                    <?= $cartCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Login / Register buttons for guests -->
                    <li class="nav-item">
                        <button class="btn btn-outline-light btn-sm me-1" onclick="openAuthModal('login')">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="btn btn-warning btn-sm me-2" onclick="openAuthModal('register')">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </button>
                    </li>
                    <!-- Guest cart icon — opens auth modal -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="#" onclick="openAuthModal('login'); return false;">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Flash Messages ───────────────────────────────────── -->
<?php
$successMsg = getFlash('success');
$errorMsg   = getFlash('error');
$infoMsg    = getFlash('info');
?>
<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show mb-0 rounded-0" role="alert">
    <div class="container"><i class="fas fa-check-circle me-2"></i><?= e($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show mb-0 rounded-0" role="alert">
    <div class="container"><i class="fas fa-exclamation-circle me-2"></i><?= e($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
<?php if ($infoMsg): ?>
<div class="alert alert-info alert-dismissible fade show mb-0 rounded-0" role="alert">
    <div class="container"><i class="fas fa-info-circle me-2"></i><?= e($infoMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- ── Auth Modal (Login / Register for guests) ──────────── -->
<?php if (!$isLoggedIn): ?>
<div class="modal fade auth-modal" id="authModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title">
                    <span>🛒</span><?= SITE_NAME ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="authTabs">
                <li class="nav-item">
                    <button class="nav-link active" id="tab-login-btn"
                            onclick="switchAuthTab('login')">
                        <i class="fas fa-sign-in-alt me-1"></i>Sign In
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-register-btn"
                            onclick="switchAuthTab('register')">
                        <i class="fas fa-user-plus me-1"></i>Create Account
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ── Login Tab ─────────────────────────────── -->
                <div id="tab-login" class="tab-pane active">
                    <div id="auth-login-msg" class="alert py-2 small d-none mb-3"></div>

                    <form id="modalLoginForm" onsubmit="modalLogin(event)">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope text-success"></i></span>
                                <input type="email" id="login-email" class="form-control"
                                       placeholder="your@email.com" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                                <input type="password" id="login-password" class="form-control"
                                       placeholder="Enter your password" required>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="toggleModalPwd('login-password','login-eye')">
                                    <i class="fas fa-eye" id="login-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold" id="loginBtn">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </form>

                    <div class="text-center mt-3 small text-muted">
                        New here?
                        <a href="#" class="text-success fw-semibold" onclick="switchAuthTab('register')">Create a free account</a>
                    </div>
                    <hr class="my-3">
                    <div class="text-center small text-muted">
                        Are you staff?
                        <a href="<?= SITE_URL ?>/staff_portal.php" class="text-success">Staff Portal →</a>
                    </div>
                </div>

                <!-- ── Register Tab ──────────────────────────── -->
                <div id="tab-register" class="tab-pane d-none">
                    <div id="auth-reg-msg" class="alert py-2 small d-none mb-3"></div>

                    <form id="modalRegForm" onsubmit="modalRegister(event)">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">First Name *</label>
                                <input type="text" id="reg-first" class="form-control"
                                       placeholder="First name" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Last Name *</label>
                                <input type="text" id="reg-last" class="form-control"
                                       placeholder="Last name" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope text-success"></i></span>
                                    <input type="email" id="reg-email" class="form-control"
                                           placeholder="your@email.com" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Password * <small class="text-muted">(min 8 chars)</small></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                                    <input type="password" id="reg-password" class="form-control"
                                           placeholder="Create password" minlength="8" required>
                                    <button type="button" class="btn btn-outline-secondary"
                                            onclick="toggleModalPwd('reg-password','reg-eye')">
                                        <i class="fas fa-eye" id="reg-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                                    <input type="password" id="reg-confirm" class="form-control"
                                           placeholder="Repeat password" minlength="8" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success w-100 py-2 fw-bold" id="registerBtn">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="text-center mt-3 small text-muted">
                        Already have an account?
                        <a href="#" class="text-success fw-semibold" onclick="switchAuthTab('login')">Sign in here</a>
                    </div>
                    <p class="text-center text-muted mt-2 mb-0" style="font-size:.78rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        You can complete your address details at checkout.
                    </p>
                </div>

            </div><!-- /tab-content -->
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Wrapper -->
<main>
