<?php
require_once __DIR__ . '/../config/config.php';

echo '<pre>';
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USER: " . SMTP_USER . "\n";
echo "SMTP_PASS: " . (SMTP_PASS ? str_repeat('*', strlen(SMTP_PASS)-4) . substr(SMTP_PASS,-4) : 'NOT SET') . "\n\n";

// Check PHPMailer files
$f1 = __DIR__ . '/../lib/phpmailer/PHPMailer.php';
$f2 = __DIR__ . '/../lib/phpmailer/SMTP.php';
$f3 = __DIR__ . '/../lib/phpmailer/Exception.php';
echo "PHPMailer.php exists: " . (file_exists($f1) ? 'YES ('.filesize($f1).' bytes)' : 'NO') . "\n";
echo "SMTP.php exists: "      . (file_exists($f2) ? 'YES ('.filesize($f2).' bytes)' : 'NO') . "\n";
echo "Exception.php exists: " . (file_exists($f3) ? 'YES ('.filesize($f3).' bytes)' : 'NO') . "\n\n";

// Load PHPMailer
require_once $f1;
require_once $f2;
require_once $f3;

echo "PHPMailer class exists: " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'YES' : 'NO') . "\n\n";

// Try sending
echo "Attempting to send test email to " . SMTP_USER . " ...\n\n";

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPDebug  = 2; // Full debug output
    $mail->Debugoutput = 'echo';
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress('heinminthant325@gmail.com');
    $mail->isHTML(true);
    $mail->Subject = 'SCM Test Email - ' . date('H:i:s');
    $mail->Body    = '<h2>✅ Email is working!</h2><p>SecondChance Mart email system is configured correctly.</p>';
    $mail->send();
    echo "\n\n✅ SUCCESS — Email sent! Check your Mailtrap inbox.\n";
} catch (PHPMailer\PHPMailer\Exception $e) {
    echo "\n\n❌ FAILED: " . $e->getMessage() . "\n";
}
echo '</pre>';
