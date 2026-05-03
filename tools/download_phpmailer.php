<?php
// ============================================================
// SecondChance Mart — PHPMailer Auto-Installer
// Run this ONCE in your browser: http://localhost:8000/tools/download_phpmailer.php
// It downloads the 3 required PHPMailer files from GitHub.
// ============================================================
if (PHP_SAPI === 'cli') { die("Run in browser, not CLI.\n"); }

$targetDir = __DIR__ . '/../lib/phpmailer/';
$files = [
    'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
    'SMTP.php'      => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
    'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php',
];

$results = [];
$allOk   = true;

if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        die('<p style="color:red">ERROR: Cannot create directory ' . htmlspecialchars($targetDir) . '</p>');
    }
}

foreach ($files as $filename => $url) {
    $dest = $targetDir . $filename;
    if (file_exists($dest) && filesize($dest) > 1000) {
        $results[$filename] = ['status' => 'already_exists', 'size' => filesize($dest)];
        continue;
    }
    $context = stream_context_create([
        'http' => [
            'timeout'     => 30,
            'user_agent'  => 'PHP/SecondChanceMart-Setup',
            'follow_location' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $content = @file_get_contents($url, false, $context);
    if ($content === false || strlen($content) < 500) {
        $results[$filename] = ['status' => 'error', 'msg' => 'Download failed — check internet connection'];
        $allOk = false;
        continue;
    }
    if (file_put_contents($dest, $content) === false) {
        $results[$filename] = ['status' => 'error', 'msg' => 'Could not write file'];
        $allOk = false;
        continue;
    }
    $results[$filename] = ['status' => 'downloaded', 'size' => strlen($content)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PHPMailer Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:640px;">
    <h3 class="mb-4">🔧 PHPMailer Setup</h3>

    <?php foreach ($results as $file => $r): ?>
    <div class="d-flex align-items-center mb-2 p-3 rounded border <?= $r['status']==='error' ? 'bg-danger text-white' : 'bg-white' ?>">
        <?php if ($r['status'] === 'downloaded'): ?>
            <span class="text-success me-2 fs-5">✅</span>
            <div><strong><?= $file ?></strong> — downloaded (<?= number_format($r['size']) ?> bytes)</div>
        <?php elseif ($r['status'] === 'already_exists'): ?>
            <span class="text-secondary me-2 fs-5">☑️</span>
            <div><strong><?= $file ?></strong> — already present (<?= number_format($r['size']) ?> bytes)</div>
        <?php else: ?>
            <span class="me-2 fs-5">❌</span>
            <div><strong><?= $file ?></strong> — <?= htmlspecialchars($r['msg']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($allOk): ?>
    <div class="alert alert-success mt-3">
        <strong>✅ PHPMailer is ready!</strong><br>
        Files saved to <code>lib/phpmailer/</code>.<br>
        Now set your SMTP password in <strong>config/config.php</strong> and emails will work.
    </div>
    <div class="card mt-3">
        <div class="card-header bg-warning"><strong>Next step — choose ONE email option:</strong></div>
        <div class="card-body small">
            <p><strong>Option A — Mailtrap (easiest for localhost testing):</strong></p>
            <ol>
                <li>Go to <a href="https://mailtrap.io" target="_blank">mailtrap.io</a> → Free signup</li>
                <li>Click <strong>Email Testing → Inboxes → My Inbox → SMTP Settings</strong></li>
                <li>Choose <strong>PHP &gt; PHPMailer</strong> integration — copy Host, Port, Username, Password</li>
                <li>Paste into <code>config/config.php</code> (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS)</li>
                <li>All test emails will appear in your Mailtrap inbox — no real delivery</li>
            </ol>
            <hr>
            <p><strong>Option B — Real Gmail delivery:</strong></p>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account → Security</a></li>
                <li>Enable <strong>2-Step Verification</strong> if not already on</li>
                <li>Search <strong>"App Passwords"</strong> → Generate for "Mail" → copy the 16-char password</li>
                <li>In <code>config/config.php</code>, replace <code>your_gmail_app_password_here</code> with it</li>
            </ol>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger mt-3">
        <strong>❌ Some files failed to download.</strong><br>
        Make sure your server has internet access, or download the files manually from
        <a href="https://github.com/PHPMailer/PHPMailer/tree/master/src" target="_blank">GitHub</a>
        and place them in <code>lib/phpmailer/</code>.
    </div>
    <?php endif; ?>

    <a href="http://localhost:8000/" class="btn btn-success mt-3">← Back to Site</a>
</div>
</body>
</html>
