<?php
// ============================================================
// SecondChance Mart - Email Notification System
// Uses PHPMailer (SMTP) when available, falls back to mail()
//
// To enable real emails:
//   Option A (Composer): composer require phpmailer/phpmailer
//   Option B (Manual):   Download PHPMailer from GitHub, place the
//                        3 files in lib/phpmailer/:
//                          - PHPMailer.php
//                          - SMTP.php
//                          - Exception.php
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ── PHPMailer Loader ─────────────────────────────────────────
function phpMailerAvailable(): bool {
    // Composer autoload
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        return class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    // Manual install in lib/phpmailer/
    $manual = __DIR__ . '/../lib/phpmailer/PHPMailer.php';
    if (file_exists($manual)) {
        require_once $manual;
        require_once __DIR__ . '/../lib/phpmailer/SMTP.php';
        require_once __DIR__ . '/../lib/phpmailer/Exception.php';
        return true;
    }
    return false;
}

/**
 * Send an email using PHPMailer (SMTP) or fall back to PHP mail().
 * Logs every email to the email_notifications table.
 */
function sendNotification(
    int    $orderId,
    string $toEmail,
    string $toType,
    string $subject,
    string $body,
    string $triggerEvent
): bool {
    $pdo = getDB();

    // Log to DB first — record exists even if sending fails
    $stmt = $pdo->prepare("
        INSERT INTO email_notifications
            (order_id, recipient_email, recipient_type, subject, body, trigger_event, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$orderId, $toEmail, $toType, $subject, $body, $triggerEvent]);
    $notifId = $pdo->lastInsertId();

    $sent = false;

    if (phpMailerAvailable()) {
        // ── PHPMailer SMTP ────────────────────────────────────
        try {
            if (SMTP_PASS === 'your_gmail_app_password_here' ||
                SMTP_PASS === 'paste_mailtrap_password_here' ||
                SMTP_PASS === '') {
                throw new \Exception('SMTP_PASS not configured in config/config.php');
            }
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>'], "\n", $body));
            $mail->send();
            $sent = true;
        } catch (\Exception $e) {
            error_log("[SCM Email ERROR] order #{$orderId} to {$toEmail}: " . $e->getMessage());
            $sent = false;
        }
    } else {
        // ── No mailer available ───────────────────────────────
        // PHPMailer not found. Open http://localhost:8000/tools/download_phpmailer.php
        error_log("[SCM Email ERROR] PHPMailer not installed. Run /tools/download_phpmailer.php");
        $sent = false;
    }

    $status = $sent ? 'sent' : 'failed';
    $pdo->prepare("UPDATE email_notifications SET status = ?, sent_at = NOW() WHERE id = ?")
        ->execute([$status, $notifId]);

    usleep(1500000); // 1.5s gap between emails — avoids Mailtrap free-tier rate limit

    return $sent;
}

// ── Branded HTML Email Wrapper ───────────────────────────────
function emailWrapper(string $title, string $content): string {
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td align="center" style="padding:30px 15px;">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,.1);max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#27ae60,#2ecc71);padding:30px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:26px;letter-spacing:1px;">🛒 SecondChance Mart</h1>
          <p style="margin:6px 0 0;color:#d5f5e3;font-size:13px;">Save More, Waste Less</p>
        </td>
      </tr>
      <tr><td style="padding:35px 30px;">{$content}</td></tr>
      <tr>
        <td style="background:#f8f9fa;padding:20px;text-align:center;color:#aaa;font-size:12px;border-top:1px solid #eee;">
          <p style="margin:0;">© {$year} SecondChance Mart. All rights reserved.</p>
          <p style="margin:6px 0 0;">This is an automated email — please do not reply directly.</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
}

// ── Helper: Build Items Table HTML ───────────────────────────
function buildItemsTable(array $items, float $total): string {
    $rows = '';
    foreach ($items as $item) {
        $lineTotal = '$' . number_format($item['quantity'] * $item['unit_price'], 2);
        $rows .= '<tr style="border-bottom:1px solid #f0f0f0;">
            <td style="padding:10px 8px;">' . htmlspecialchars($item['product_name']) . '</td>
            <td style="padding:10px 8px;text-align:center;color:#666;">' . $item['quantity'] . '</td>
            <td style="padding:10px 8px;text-align:right;font-weight:bold;">' . $lineTotal . '</td>
        </tr>';
    }
    return '
    <table width="100%" style="border-collapse:collapse;margin:15px 0;font-size:14px;">
      <tr style="background:#27ae60;color:#fff;">
        <th style="padding:10px 8px;text-align:left;">Product</th>
        <th style="padding:10px 8px;text-align:center;">Qty</th>
        <th style="padding:10px 8px;text-align:right;">Amount</th>
      </tr>
      ' . $rows . '
      <tr style="background:#f8fff8;">
        <td colspan="2" style="padding:12px 8px;text-align:right;font-weight:bold;">Total:</td>
        <td style="padding:12px 8px;text-align:right;font-weight:bold;color:#27ae60;font-size:16px;">$' . number_format($total, 2) . '</td>
      </tr>
    </table>';
}

// ── Trigger: Order Placed ────────────────────────────────────
/**
 * Emails sent when a customer places an order:
 *   1. Customer  — order received confirmation
 *   2. Admin     — new order alert
 */
function notifyOrderPlaced(int $orderId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT o.*, u.email AS customer_email,
               CONCAT(c.first_name, ' ', c.last_name) AS customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN customers c ON u.id = c.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return;

    // Fetch order items
    $itemStmt = $pdo->prepare("SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = ?");
    $itemStmt->execute([$orderId]);
    $items = $itemStmt->fetchAll();

    $orderNum = $order['order_number'];
    $custName = $order['customer_name'] ?: 'Valued Customer';
    $total    = (float)$order['total_amount'];
    $totalFmt = '$' . number_format($total, 2);
    $payLabel = strtoupper(str_replace('_', ' ', $order['payment_method']));
    $itemsHtml = buildItemsTable($items, $total);

    // 1. Customer — Order Received
    $custBody = emailWrapper("Order Received – {$orderNum}", "
        <h2 style='color:#27ae60;margin-top:0;'>Thank you, {$custName}! 🎉</h2>
        <p style='color:#555;line-height:1.7;'>Your order has been received and is currently being reviewed by our team.
           We'll send you another email once it's confirmed.</p>
        <div style='background:#f0fff4;border:1px solid #c3e6cb;border-radius:8px;padding:20px;margin:20px 0;'>
            <table width='100%' style='font-size:14px;'>
                <tr><td style='color:#666;padding:4px 0;'>Order Number</td><td style='font-weight:bold;text-align:right;'>{$orderNum}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Order Total</td><td style='font-weight:bold;text-align:right;color:#27ae60;'>{$totalFmt}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Payment Method</td><td style='font-weight:bold;text-align:right;'>{$payLabel}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Status</td><td style='font-weight:bold;text-align:right;'><span style='background:#fff3cd;color:#856404;padding:3px 10px;border-radius:20px;font-size:12px;'>⏳ Pending Review</span></td></tr>
            </table>
        </div>
        <h3 style='color:#333;'>Your Items:</h3>
        {$itemsHtml}
        <p style='color:#27ae60;font-size:13px;margin-top:25px;'>🌱 Thank you for helping reduce food waste in Singapore!</p>
    ");
    sendNotification($orderId, $order['customer_email'], 'customer',
        "Order Received – {$orderNum} | SecondChance Mart", $custBody, 'order_placed');

    // 2. Admin — New Order Alert
    $adminBody = emailWrapper("New Order Alert – {$orderNum}", "
        <h2 style='color:#e67e22;margin-top:0;'>📦 New Order Received</h2>
        <p style='color:#555;'>A new customer order is awaiting your confirmation.</p>
        <div style='background:#fff8e1;border:1px solid #ffc107;border-radius:8px;padding:20px;margin:20px 0;'>
            <table width='100%' style='font-size:14px;'>
                <tr><td style='color:#666;padding:4px 0;'>Order Number</td><td style='font-weight:bold;text-align:right;'>{$orderNum}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Customer</td><td style='font-weight:bold;text-align:right;'>{$custName}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Customer Email</td><td style='font-weight:bold;text-align:right;'>{$order['customer_email']}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Order Total</td><td style='font-weight:bold;text-align:right;color:#27ae60;'>{$totalFmt}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Payment Method</td><td style='font-weight:bold;text-align:right;'>{$payLabel}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Delivery Address</td><td style='font-weight:bold;text-align:right;'>{$order['shipping_address']}, {$order['shipping_city']}</td></tr>
            </table>
        </div>
        {$itemsHtml}
        <p style='margin-top:25px;'>
            <a href='" . SITE_URL . "/admin/orders.php'
               style='background:#27ae60;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;'>
               Review Order in Admin Dashboard →
            </a>
        </p>
    ");
    sendNotification($orderId, EMAIL_ADMIN, 'admin',
        "New Order – {$orderNum} | SecondChance Mart", $adminBody, 'order_placed');
}

// ── Trigger: Order Confirmed ─────────────────────────────────
/**
 * Emails sent when admin confirms an order:
 *   1. Customer   — order confirmed with full details
 *   2. Supplier   — prepare items request
 *   3. Warehouse  — delivery assignment
 *   4. Admin      — confirmation summary
 */
function notifyOrderConfirmed(int $orderId): void {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT o.*, u.email AS customer_email,
               CONCAT(c.first_name, ' ', c.last_name) AS customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN customers c ON u.id = c.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return;

    $itemStmt = $pdo->prepare("
        SELECT oi.product_name, oi.quantity, oi.unit_price
        FROM order_items oi
        WHERE oi.order_id = ?
    ");
    $itemStmt->execute([$orderId]);
    $items = $itemStmt->fetchAll();

    $orderNum = $order['order_number'];
    $custName = $order['customer_name'] ?: 'Valued Customer';
    $total    = (float)$order['total_amount'];
    $totalFmt = '$' . number_format($total, 2);
    $payLabel = strtoupper(str_replace('_', ' ', $order['payment_method']));
    $itemsHtml = buildItemsTable($items, $total);
    $address   = "{$order['shipping_address']}, {$order['shipping_city']} {$order['shipping_postal']}";

    // 1. Customer — Confirmed
    $custBody = emailWrapper("Order Confirmed – {$orderNum}", "
        <h2 style='color:#27ae60;margin-top:0;'>🎉 Your Order is Confirmed!</h2>
        <p style='color:#555;line-height:1.7;'>Great news, <strong>{$custName}</strong>! Your order has been confirmed
           and our supplier is now preparing your items for delivery.</p>
        <div style='background:#f0fff4;border:1px solid #c3e6cb;border-radius:8px;padding:20px;margin:20px 0;'>
            <table width='100%' style='font-size:14px;'>
                <tr><td style='color:#666;padding:4px 0;'>Order Number</td><td style='font-weight:bold;text-align:right;'>{$orderNum}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Order Total</td><td style='font-weight:bold;text-align:right;color:#27ae60;'>{$totalFmt}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Payment Method</td><td style='font-weight:bold;text-align:right;'>{$payLabel}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Status</td><td style='font-weight:bold;text-align:right;'><span style='background:#d4edda;color:#155724;padding:3px 10px;border-radius:20px;font-size:12px;'>✅ Confirmed</span></td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Delivery To</td><td style='font-weight:bold;text-align:right;'>{$order['shipping_name']}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Address</td><td style='font-weight:bold;text-align:right;'>{$address}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Phone</td><td style='font-weight:bold;text-align:right;'>{$order['shipping_phone']}</td></tr>
            </table>
        </div>
        <h3 style='color:#333;'>Items in Your Order:</h3>
        {$itemsHtml}
        <p style='color:#555;margin-top:20px;'>You will receive further updates as your order is packed and dispatched.</p>
        <p style='color:#27ae60;font-size:13px;'>🌱 Thank you for shopping sustainably at SecondChance Mart!</p>
    ");
    sendNotification($orderId, $order['customer_email'], 'customer',
        "Order Confirmed – {$orderNum} | SecondChance Mart", $custBody, 'order_confirmed');

    // 2. Supplier — Prepare Items
    $suppBody = emailWrapper("Prepare Items – Order {$orderNum}", "
        <h2 style='color:#e67e22;margin-top:0;'>📋 Please Prepare Items for Delivery</h2>
        <p style='color:#555;line-height:1.7;'>A confirmed order contains your products. Please prepare and pack the
           following items ready for warehouse collection.</p>
        <div style='background:#fff8e1;border:1px solid #ffc107;border-radius:8px;padding:20px;margin:20px 0;'>
            <table width='100%' style='font-size:14px;'>
                <tr><td style='color:#666;padding:4px 0;'>Order Number</td><td style='font-weight:bold;text-align:right;'>{$orderNum}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Order Total</td><td style='font-weight:bold;text-align:right;'>{$totalFmt}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Action Required</td><td style='font-weight:bold;text-align:right;color:#e67e22;'>Pack items for pickup</td></tr>
            </table>
        </div>
        <h3 style='color:#333;'>Items to Prepare:</h3>
        {$itemsHtml}
        <p style='color:#555;margin-top:20px;'>The delivery team will contact you for pickup. Please update stock
           quantities in your <a href='" . SITE_URL . "/supplier/dashboard.php' style='color:#27ae60;'>Supplier Dashboard</a>
           once items are dispatched.</p>
    ");
    sendNotification($orderId, EMAIL_SUPPLIER, 'supplier',
        "Prepare Items – Order {$orderNum} | SecondChance Mart", $suppBody, 'order_confirmed');

    // 3. Warehouse/Delivery — New Assignment
    $warehouseBody = emailWrapper("Delivery Assignment – {$orderNum}", "
        <h2 style='color:#2980b9;margin-top:0;'>🚚 New Delivery Assignment</h2>
        <p style='color:#555;line-height:1.7;'>A new order has been confirmed and is ready for collection and delivery.
           Please collect from the supplier and deliver to the customer.</p>
        <div style='background:#e8f4fd;border:1px solid #bee5eb;border-radius:8px;padding:20px;margin:20px 0;'>
            <table width='100%' style='font-size:14px;'>
                <tr><td style='color:#666;padding:4px 0;'>Order Number</td><td style='font-weight:bold;text-align:right;'>{$orderNum}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Customer Name</td><td style='font-weight:bold;text-align:right;'>{$order['shipping_name']}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Contact Phone</td><td style='font-weight:bold;text-align:right;'>{$order['shipping_phone']}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Delivery Address</td><td style='font-weight:bold;text-align:right;'>{$address}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Order Total</td><td style='font-weight:bold;text-align:right;color:#27ae60;'>{$totalFmt}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Payment Method</td><td style='font-weight:bold;text-align:right;'>{$payLabel}</td></tr>
                " . ($order['notes'] ? "<tr><td style='color:#666;padding:4px 0;'>Delivery Notes</td><td style='font-weight:bold;text-align:right;color:#e67e22;'>{$order['notes']}</td></tr>" : '') . "
            </table>
        </div>
        <h3 style='color:#333;'>Items to Deliver:</h3>
        {$itemsHtml}
        <p style='margin-top:20px;'>
            <a href='" . SITE_URL . "/warehouse/dashboard.php'
               style='background:#2980b9;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;'>
               Update Delivery Status →
            </a>
        </p>
    ");
    sendNotification($orderId, EMAIL_WAREHOUSE, 'warehouse',
        "Delivery Assignment – {$orderNum} | SecondChance Mart", $warehouseBody, 'order_confirmed');

    // 4. Admin — Summary
    $adminBody = emailWrapper("Order Confirmed – {$orderNum}", "
        <h2 style='color:#27ae60;margin-top:0;'>✅ Order Confirmation Complete</h2>
        <p style='color:#555;'>Order <strong>{$orderNum}</strong> has been confirmed and all parties notified.</p>
        <div style='background:#f0fff4;border:1px solid #c3e6cb;border-radius:8px;padding:20px;margin:20px 0;'>
            <table width='100%' style='font-size:14px;'>
                <tr><td style='color:#666;padding:4px 0;'>Order Number</td><td style='font-weight:bold;text-align:right;'>{$orderNum}</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Customer</td><td style='font-weight:bold;text-align:right;'>{$custName} ({$order['customer_email']})</td></tr>
                <tr><td style='color:#666;padding:4px 0;'>Total</td><td style='font-weight:bold;text-align:right;color:#27ae60;'>{$totalFmt}</td></tr>
            </table>
        </div>
        <p style='color:#555;'><strong>Notifications sent to:</strong></p>
        <ul style='color:#555;line-height:2;'>
            <li>✅ Customer ({$order['customer_email']}) — order confirmation</li>
            <li>✅ Supplier (" . EMAIL_SUPPLIER . ") — preparation request</li>
            <li>✅ Warehouse (" . EMAIL_WAREHOUSE . ") — delivery assignment</li>
        </ul>
    ");
    sendNotification($orderId, EMAIL_ADMIN, 'admin',
        "Order Confirmed – {$orderNum} | SecondChance Mart", $adminBody, 'order_confirmed');
}
