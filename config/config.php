<?php
// ============================================================
// SecondChance Mart - Global Configuration
// ============================================================

// Site information
define('SITE_NAME',    'SecondChance Mart');
define('SITE_URL',     'http://localhost:8000');
define('SITE_TAGLINE', 'Save More, Waste Less');

// ── Email Addresses ──────────────────────────────────────────
// All demo accounts use the same real email for testing
define('EMAIL_CUSTOMER',  'heinminthant325@gmail.com');
define('EMAIL_ADMIN',     'heinminthant325@gmail.com');
define('EMAIL_SUPPLIER',  'heinminthant325@gmail.com');
define('EMAIL_WAREHOUSE', 'heinminthant325@gmail.com');

// ── PHPMailer / SMTP Settings ────────────────────────────────
// STEP 1: Open http://localhost:8000/tools/download_phpmailer.php to install PHPMailer
// STEP 2: Choose ONE email option below (uncomment ONE block)

// ── OPTION A: Mailtrap (ACTIVE)
define('SMTP_HOST',      'sandbox.smtp.mailtrap.io');
define('SMTP_PORT',      2525);
define('SMTP_USER',      'b0ddbdda4343b7');
define('SMTP_PASS',      '81fa264cf8023a');
define('SMTP_FROM',      'noreply@secondchancemart.com');
define('SMTP_FROM_NAME', 'SecondChance Mart');

// ── OPTION B: Real Gmail delivery (commented out)
// define('SMTP_HOST',      'smtp.gmail.com');
// define('SMTP_PORT',      587);
// define('SMTP_USER',      'heinminthant325@gmail.com');
// define('SMTP_PASS',      'your_gmail_app_password_here');
// define('SMTP_FROM',      'heinminthant325@gmail.com');
// define('SMTP_FROM_NAME', 'SecondChance Mart');

// ── OPTION C: SendGrid (uncomment to use)
// define('SMTP_HOST',     'smtp.sendgrid.net');
// define('SMTP_PORT',     587);
// define('SMTP_USER',     'apikey');
// define('SMTP_PASS',     'SG.your_sendgrid_api_key_here');
// define('SMTP_FROM',     'heinminthant325@gmail.com');
// define('SMTP_FROM_NAME','SecondChance Mart');

// ── App Settings ─────────────────────────────────────────────
define('SESSION_LIFETIME',    7200);  // 2 hours
define('PRODUCTS_PER_PAGE',   12);
define('LOW_STOCK_THRESHOLD', 5);
define('CURRENCY',            '$');

// Upload path for product images (local uploads)
define('UPLOAD_PATH', __DIR__ . '/../assets/images/products/');
define('UPLOAD_URL',  SITE_URL . '/assets/images/products/');

// ── Session Bootstrap ────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}
