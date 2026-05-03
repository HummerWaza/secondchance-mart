<?php
// ============================================================
// SecondChance Mart - Staff Portal
// Central hub for Admin, Supplier, and Delivery staff login
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect logged-in staff to their dashboard
if (isLoggedIn()) {
    $map = [
        'admin'    => SITE_URL . '/admin/dashboard.php',
        'supplier' => SITE_URL . '/supplier/dashboard.php',
        'delivery' => SITE_URL . '/warehouse/dashboard.php',
    ];
    if (isset($map[userRole()])) redirect($map[userRole()]);
}

$pageTitle = 'Staff Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a2a1a 0%, #1e4d2b 50%, #0d2b1a 100%); min-height: 100vh; }
        .portal-header { text-align: center; color: #fff; padding: 50px 20px 30px; }
        .portal-header h1 { font-size: 2.2rem; font-weight: 800; letter-spacing: 1px; }
        .portal-header p  { opacity: .75; font-size: 1.05rem; }
        .role-card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 28px;
            text-align: center;
            transition: transform .2s, box-shadow .2s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .role-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
        }
        .role-card.card-admin::before    { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .role-card.card-supplier::before { background: linear-gradient(90deg, #e67e22, #f39c12); }
        .role-card.card-delivery::before { background: linear-gradient(90deg, #2980b9, #3498db); }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 12px 35px rgba(0,0,0,.15); }
        .role-icon { font-size: 3.5rem; margin-bottom: 16px; }
        .role-card h4 { font-weight: 700; color: #1a2a1a; margin-bottom: 8px; }
        .role-card p  { color: #666; font-size: .9rem; line-height: 1.6; min-height: 60px; }
        .btn-role {
            display: block;
            width: 100%;
            padding: 11px 0;
            border-radius: 8px;
            font-weight: 700;
            font-size: .95rem;
            text-decoration: none;
            transition: opacity .15s;
            border: none;
            cursor: pointer;
            color: #fff;
        }
        .btn-role:hover { opacity: .9; color: #fff; }
        .btn-admin    { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .btn-supplier { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .btn-delivery { background: linear-gradient(135deg, #2980b9, #3498db); }
        .register-link { font-size: .82rem; color: #999; display: block; margin-top: 10px; }
        .register-link a { color: #27ae60; font-weight: 600; }
        .demo-box {
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 12px;
            padding: 20px 28px;
            color: #fff;
            max-width: 680px;
            margin: 0 auto 40px;
        }
        .demo-box h6 { color: #a8e6bf; margin-bottom: 12px; }
        .demo-box table { font-size: .85rem; }
        .demo-box td { padding: 4px 14px 4px 0; }
        .demo-box td:first-child { color: #a8e6bf; white-space: nowrap; }
        .demo-box td code { background: rgba(255,255,255,.15); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: .82rem; }
    </style>
</head>
<body>

<!-- Header -->
<div class="portal-header">
    <div style="font-size:3rem; margin-bottom:12px;">🛒</div>
    <h1><?= SITE_NAME ?></h1>
    <p>Staff Portal — Select your role to sign in</p>
    <a href="<?= SITE_URL ?>/" class="btn btn-outline-light btn-sm mt-2">
        <i class="fas fa-arrow-left me-2"></i>Back to Customer Store
    </a>
</div>

<!-- Role Cards -->
<div class="container pb-5">
    <div class="row g-4 justify-content-center mb-5">

        <!-- Admin -->
        <div class="col-md-4">
            <div class="role-card card-admin">
                <div class="role-icon">🔑</div>
                <h4>Admin</h4>
                <p>Manage products, orders, customers, suppliers, and view email notifications.</p>
                <a href="<?= SITE_URL ?>/admin/login.php" class="btn-role btn-admin">
                    <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                </a>
                <span class="register-link">
                    New admin? <a href="<?= SITE_URL ?>/admin/login.php">Contact system owner</a>
                </span>
            </div>
        </div>

        <!-- Supplier -->
        <div class="col-md-4">
            <div class="role-card card-supplier">
                <div class="role-icon">🏭</div>
                <h4>Supplier</h4>
                <p>View confirmed orders, manage product stock, and receive preparation notifications.</p>
                <a href="<?= SITE_URL ?>/supplier/login.php" class="btn-role btn-supplier">
                    <i class="fas fa-sign-in-alt me-2"></i>Supplier Login
                </a>
                <span class="register-link">
                    New supplier? <a href="<?= SITE_URL ?>/admin/suppliers.php">Register via Admin</a>
                </span>
            </div>
        </div>

        <!-- Delivery -->
        <div class="col-md-4">
            <div class="role-card card-delivery">
                <div class="role-icon">🚚</div>
                <h4>Delivery Staff</h4>
                <p>View delivery queue, customer addresses, and update real-time order delivery status.</p>
                <a href="<?= SITE_URL ?>/warehouse/login.php" class="btn-role btn-delivery">
                    <i class="fas fa-sign-in-alt me-2"></i>Delivery Login
                </a>
                <span class="register-link">
                    New staff? <a href="<?= SITE_URL ?>/admin/login.php">Contact your admin</a>
                </span>
            </div>
        </div>
    </div>

    <!-- Demo Credentials Box -->
    <div class="demo-box">
        <h6><i class="fas fa-key me-2"></i>Demo Login Credentials (all use the same email)</h6>
        <table>
            <tr>
                <td>👤 Customer</td>
                <td>heinminthant325@gmail.com</td>
                <td><code>Customer123</code></td>
                <td><a href="<?= SITE_URL ?>/login.php" style="color:#a8e6bf;">→ Customer Login</a></td>
            </tr>
            <tr>
                <td>🔑 Admin</td>
                <td>heinminthant325@gmail.com</td>
                <td><code>Admin123</code></td>
                <td><a href="<?= SITE_URL ?>/admin/login.php" style="color:#a8e6bf;">→ Admin Login</a></td>
            </tr>
            <tr>
                <td>🏭 Supplier</td>
                <td>heinminthant325@gmail.com</td>
                <td><code>Supplier123</code></td>
                <td><a href="<?= SITE_URL ?>/supplier/login.php" style="color:#a8e6bf;">→ Supplier Login</a></td>
            </tr>
            <tr>
                <td>🚚 Delivery</td>
                <td>heinminthant325@gmail.com</td>
                <td><code>Delivery123</code></td>
                <td><a href="<?= SITE_URL ?>/warehouse/login.php" style="color:#a8e6bf;">→ Delivery Login</a></td>
            </tr>
        </table>
        <p style="margin-top:12px;margin-bottom:0;font-size:.8rem;opacity:.7;">
            <i class="fas fa-info-circle me-1"></i>
            One email is used for all roles — each login page verifies email + role separately.
        </p>
    </div>

    <!-- Features Summary -->
    <div class="row g-3 text-white text-center">
        <div class="col-md-4">
            <div style="background:rgba(255,255,255,.08);border-radius:10px;padding:20px;">
                <div style="font-size:1.8rem;">📊</div>
                <h6 class="mt-2">Admin Features</h6>
                <ul class="list-unstyled small" style="color:rgba(255,255,255,.75);">
                    <li>Dashboard analytics</li>
                    <li>Product management (CRUD)</li>
                    <li>Order confirmation + emails</li>
                    <li>Customer & supplier management</li>
                    <li>Email notification logs</li>
                </ul>
            </div>
        </div>
        <div class="col-md-4">
            <div style="background:rgba(255,255,255,.08);border-radius:10px;padding:20px;">
                <div style="font-size:1.8rem;">📦</div>
                <h6 class="mt-2">Supplier Features</h6>
                <ul class="list-unstyled small" style="color:rgba(255,255,255,.75);">
                    <li>View confirmed orders</li>
                    <li>See items to prepare</li>
                    <li>Update stock quantities</li>
                    <li>Email alerts on orders</li>
                </ul>
            </div>
        </div>
        <div class="col-md-4">
            <div style="background:rgba(255,255,255,.08);border-radius:10px;padding:20px;">
                <div style="font-size:1.8rem;">🗺️</div>
                <h6 class="mt-2">Delivery Features</h6>
                <ul class="list-unstyled small" style="color:rgba(255,255,255,.75);">
                    <li>View delivery queue</li>
                    <li>Customer address & phone</li>
                    <li>Update delivery status</li>
                    <li>Email alerts on assignments</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
