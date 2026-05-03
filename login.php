<?php
// ============================================================
// SecondChance Mart - Customer Login
// Same email can have multiple roles; each login page checks
// email + role so one Gmail address works for all 4 roles.
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in as customer — go home
if (isLoggedIn() && userRole() === 'customer') redirect(SITE_URL . '/');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $pdo  = getDB();
        // Check email + role='customer' — same email can have other roles too
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'customer' AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = 'customer';

            $c = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM customers WHERE user_id = ?");
            $c->execute([$user['id']]);
            $_SESSION['name'] = $c->fetchColumn() ?: 'Customer';

            $dest = $_GET['redirect'] ?? (SITE_URL . '/');
            redirect(urldecode($dest));
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$pageTitle = 'Customer Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">🛒</div>
            <h3><?= SITE_NAME ?></h3>
            <p class="mb-0 opacity-75 small">Customer Login</p>
        </div>
        <div class="auth-body">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small">
                <i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($flash = getFlash('success')): ?>
            <div class="alert alert-success py-2 small"><?= e($flash) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope text-success"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="your@email.com"
                               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                        <input type="password" name="password" id="passwordField"
                               class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <hr class="my-3">
            <p class="text-center small mb-3">
                New customer?
                <a href="<?= SITE_URL ?>/register.php" class="text-success fw-semibold">Create an account</a>
            </p>

            <!-- Demo Credentials -->
            <div class="p-3 bg-light rounded border mb-3">
                <p class="small fw-bold mb-2 text-muted">🔑 Demo Customer Login:</p>
                <div class="small text-muted">
                    <div><strong>Email:</strong> heinminthant325@gmail.com</div>
                    <div><strong>Password:</strong> Customer123</div>
                </div>
            </div>

            <!-- Staff Portal Link -->
            <div class="text-center">
                <a href="<?= SITE_URL ?>/staff_portal.php"
                   class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-users-cog me-2"></i>Staff Portal (Admin / Supplier / Delivery)
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') {
        f.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash');
    } else {
        f.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye');
    }
}
</script>
</body>
</html>
