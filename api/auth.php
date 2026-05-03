<?php
// ============================================================
// SecondChance Mart - AJAX Auth Endpoint
// Handles modal login and quick register for guest users
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($data['action'] ?? '');

// ── Modal Login ───────────────────────────────────────────────
if ($action === 'login') {
    $email    = trim($data['email']    ?? '');
    $password = $data['password']      ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please enter email and password.']);
        exit;
    }

    $pdo  = getDB();
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

        echo json_encode(['success' => true, 'name' => $_SESSION['name'], 'cart_count' => getCartCount()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect email or password. Please try again.']);
    }
    exit;
}

// ── Quick Register ────────────────────────────────────────────
if ($action === 'register') {
    $firstName  = trim($data['first_name']       ?? '');
    $lastName   = trim($data['last_name']        ?? '');
    $email      = trim($data['email']            ?? '');
    $password   = $data['password']              ?? '';
    $confirmPwd = $data['confirm_password']      ?? '';

    $errors = [];
    if (!$firstName)                              $errors[] = 'First name is required.';
    if (!$lastName)                               $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8)                    $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPwd)                $errors[] = 'Passwords do not match.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    $pdo = getDB();
    // Check if this email already registered as customer
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'customer'");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered. Please sign in instead.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'customer')")
            ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO customers (user_id, first_name, last_name) VALUES (?, ?, ?)")
            ->execute([$userId, $firstName, $lastName]);
        $pdo->commit();

        echo json_encode(['success' => true, 'registered' => true, 'message' => 'Account created! Please sign in.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
