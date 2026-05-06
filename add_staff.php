<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$pdo = getDB();
$email = 'heinminthant325@gmail.com';

$accounts = [
    ['role' => 'customer',  'password' => 'Customer123',  'name' => 'Demo Customer'],
    ['role' => 'admin',     'password' => 'Admin123',     'name' => 'Demo Admin'],
    ['role' => 'supplier',  'password' => 'Supplier123',  'name' => 'Demo Supplier'],
    ['role' => 'delivery',  'password' => 'Delivery123',  'name' => 'Demo Delivery'],
];

foreach ($accounts as $acc) {
    $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (email, password, role, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$email, $hash, $acc['role']]);
    $userId = $pdo->lastInsertId();

    if (!$userId) {
        // Update existing
        $pdo->prepare("UPDATE users SET password=?, is_active=1 WHERE email=? AND role=?")
            ->execute([$hash, $email, $acc['role']]);
        $userId = $pdo->query("SELECT id FROM users WHERE email='$email' AND role='{$acc['role']}'")->fetchColumn();
    }

    // Add to role-specific table
    if ($acc['role'] === 'admin') {
        $pdo->prepare("INSERT IGNORE INTO admins (user_id, name) VALUES (?,?)")->execute([$userId, $acc['name']]);
    } elseif ($acc['role'] === 'supplier') {
        $pdo->prepare("INSERT IGNORE INTO suppliers (user_id, company_name, contact_person) VALUES (?,?,?)")->execute([$userId, 'Demo Supplier Co', $acc['name']]);
    } elseif ($acc['role'] === 'delivery') {
        $pdo->prepare("INSERT IGNORE INTO warehouse_staff (user_id, name) VALUES (?,?)")->execute([$userId, $acc['name']]);
    } elseif ($acc['role'] === 'customer') {
        $pdo->prepare("INSERT IGNORE INTO customers (user_id, first_name, last_name) VALUES (?,?,?)")->execute([$userId, 'Demo', 'Customer']);
    }

    echo "✅ {$acc['role']} — {$email} / {$acc['password']}<br>";
}

echo "<br><strong>Done! All demo accounts ready.</strong><br>";
echo "<a href='/staff_portal.php'>Go to Staff Portal</a> | <a href='/login.php'>Customer Login</a><br><br>";
echo "<strong style='color:red'>Delete this file from the server after running!</strong>";
?>
