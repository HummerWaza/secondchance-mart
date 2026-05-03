<?php
// ============================================================
// SecondChance Mart - Customer Registration Page
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(SITE_URL . '/');

$errors  = [];
$success = false;

// ── Handle POST Registration ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName  = trim($_POST['first_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$firstName) $errors[] = 'First name is required.';
    if (!$lastName)  $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPwd) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $pdo  = getDB();
        // Check if email already registered
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'customer'");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'This email is already registered as a customer. Please login instead.';
        } else {
            // Insert user and customer profile in a transaction
            try {
                $pdo->beginTransaction();

                $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'customer')")
                    ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO customers (user_id, first_name, last_name, phone, address, city, postal_code)
                               VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$userId, $firstName, $lastName, $phone, $address, $city, $postalCode]);

                $pdo->commit();

                flash('success', 'Registration successful! Please log in.');
                redirect(SITE_URL . '/login.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page" style="min-height:100vh; padding:40px 16px;">
    <div class="auth-card" style="max-width:520px;">
        <!-- Header -->
        <div class="auth-header">
            <div class="auth-logo">🛒</div>
            <h3>Create Account</h3>
            <p class="mb-0 opacity-75 small">Join SecondChance Mart and start saving!</p>
        </div>

        <!-- Form -->
        <div class="auth-body">
            <!-- Errors -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row g-3">
                    <!-- First Name -->
                    <div class="col-6">
                        <label class="form-label fw-semibold small">First Name *</label>
                        <input type="text" name="first_name" class="form-control"
                               value="<?= e($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <!-- Last Name -->
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Last Name *</label>
                        <input type="text" name="last_name" class="form-control"
                               value="<?= e($_POST['last_name'] ?? '') ?>" required>
                    </div>
                    <!-- Email -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope text-success"></i></span>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <!-- Phone -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone text-success"></i></span>
                            <input type="tel" name="phone" class="form-control"
                                   placeholder="+65 9123 4567"
                                   value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <!-- Address -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Delivery Address</label>
                        <input type="text" name="address" class="form-control"
                               placeholder="Block/Unit, Street Name"
                               value="<?= e($_POST['address'] ?? '') ?>">
                    </div>
                    <!-- City & Postal -->
                    <div class="col-7">
                        <label class="form-label fw-semibold small">City</label>
                        <input type="text" name="city" class="form-control"
                               placeholder="Singapore"
                               value="<?= e($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="col-5">
                        <label class="form-label fw-semibold small">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control"
                               placeholder="123456"
                               value="<?= e($_POST['postal_code'] ?? '') ?>">
                    </div>
                    <!-- Password -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Password * (min 8 chars)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                            <input type="password" name="password" class="form-control"
                                   minlength="8" required>
                        </div>
                    </div>
                    <!-- Confirm Password -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock text-success"></i></span>
                            <input type="password" name="confirm_password" class="form-control"
                                   minlength="8" required>
                        </div>
                    </div>
                    <!-- Submit -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </div>
                </div>
            </form>

            <hr class="my-3">
            <p class="text-center small mb-0">
                Already have an account?
                <a href="<?= SITE_URL ?>/login.php" class="text-success fw-semibold">Sign In</a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
