<?php
// ============================================================
// SecondChance Mart - Delivery Staff Login
// Role: delivery (was: warehouse — renamed in final version)
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn() && userRole() === 'delivery') redirect(SITE_URL . '/warehouse/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'delivery' AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = 'delivery';
            $w = $pdo->prepare("SELECT name FROM warehouse_staff WHERE user_id = ?");
            $w->execute([$user['id']]);
            $_SESSION['name'] = $w->fetchColumn() ?: 'Delivery Staff';
            redirect(SITE_URL . '/warehouse/dashboard.php');
        } else {
            $error = 'Invalid delivery staff credentials.';
        }
    } else {
        $error = 'Please enter email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Login | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page" style="background:linear-gradient(135deg,#1a2a3a,#0d2b45);">
    <div class="auth-card" style="max-width:400px;">
        <div class="auth-header" style="background:linear-gradient(135deg,#1a2a3a,#2980b9);">
            <div class="auth-logo">🚚</div>
            <h3>Delivery Portal</h3>
            <p class="mb-0 opacity-75 small"><?= SITE_NAME ?> — Warehouse & Delivery</p>
        </div>
        <div class="auth-body">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Delivery Staff Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-truck text-success"></i></span>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold">
                    <i class="fas fa-sign-in-alt me-2"></i>Delivery Staff Login
                </button>
            </form>
            <hr class="my-3">
            <div class="p-3 bg-light rounded border small text-muted">
                <strong>Demo Delivery Staff:</strong><br>
                Email: heinminthant325@gmail.com<br>
                Password: Delivery123
            </div>
            <div class="text-center mt-3">
                <a href="<?= SITE_URL ?>/staff_portal.php" class="small text-muted me-3">
                    <i class="fas fa-users-cog me-1"></i>Staff Portal
                </a>
                <a href="<?= SITE_URL ?>/" class="small text-muted">
                    <i class="fas fa-arrow-left me-1"></i>Back to Store
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
