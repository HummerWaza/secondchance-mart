<?php
// ============================================================
// Admin - Email Notification Logs
// ============================================================
$pageTitle = 'Email Logs';
require_once __DIR__ . '/includes/admin_header.php';

$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * 20;

$total = $pdo->query("SELECT COUNT(*) FROM email_notifications")->fetchColumn();
$totalPages = ceil($total / 20);

$emails = $pdo->prepare("
    SELECT en.*, o.order_number
    FROM email_notifications en
    LEFT JOIN orders o ON en.order_id = o.id
    ORDER BY en.created_at DESC
    LIMIT 20 OFFSET $offset
");
$emails->execute();
$logs = $emails->fetchAll();

$statusCounts = $pdo->query("SELECT status, COUNT(*) FROM email_notifications GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">📧 Email Notification Logs</h4>
    <div class="d-flex gap-2">
        <span class="badge bg-success px-3 py-2">Sent: <?= $statusCounts['sent'] ?? 0 ?></span>
        <span class="badge bg-danger px-3 py-2">Failed: <?= $statusCounts['failed'] ?? 0 ?></span>
        <span class="badge bg-warning text-dark px-3 py-2">Pending: <?= $statusCounts['pending'] ?? 0 ?></span>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No email logs yet</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <?php if ($log['order_number']): ?>
                        <a href="order-detail.php?id=<?= $log['order_id'] ?>" class="text-green fw-bold text-decoration-none">
                            <?= e($log['order_number']) ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="small"><?= e($log['recipient_email']) ?></td>
                    <td>
                        <?php $typeColors = ['customer'=>'primary','admin'=>'warning','supplier'=>'info','warehouse'=>'secondary']; ?>
                        <span class="badge bg-<?= $typeColors[$log['recipient_type']] ?? 'dark' ?>">
                            <?= e($log['recipient_type']) ?>
                        </span>
                    </td>
                    <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e($log['subject']) ?>
                    </td>
                    <td><span class="badge bg-light text-dark"><?= e($log['trigger_event'] ?: '—') ?></span></td>
                    <td>
                        <span class="badge bg-<?= $log['status']==='sent' ? 'success' : ($log['status']==='failed' ? 'danger' : 'warning text-dark') ?>">
                            <?= $log['status'] ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= date('d/m/y g:ia', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="p-3 border-top">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Note:</strong> Email sending uses PHP's <code>mail()</code> function.
    To enable real email delivery, configure SMTP settings in <code>config/config.php</code>
    and use PHPMailer (via Composer). See <code>includes/email.php</code> for PHPMailer setup instructions.
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
