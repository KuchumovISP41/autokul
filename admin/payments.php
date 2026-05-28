<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/feature_bootstrap.php';
requireAuth('admin');

$page_title = 'Платежи — Панель управления';
$pdo = getDBConnection();
ensureFeatureTables($pdo);

$statuses = getPaymentStatuses();
$methods = getPaymentMethods();
$documentTypes = getDocumentTypes();
$statusClasses = [
    'pending' => 'admin-pay-status-pending',
    'paid' => 'admin-pay-status-paid',
    'cancelled' => 'admin-pay-status-cancelled',
    'error' => 'admin-pay-status-error',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['status'])) {
    $paymentId = (int)$_POST['payment_id'];
    $status = $_POST['status'];
    if (!array_key_exists($status, $statuses)) {
        $status = 'pending';
    }

    $oldStmt = $pdo->prepare('SELECT p.*, u.full_name, u.email FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = :id');
    $oldStmt->execute(['id' => $paymentId]);
    $payment = $oldStmt->fetch();

    if ($payment) {
        $stmt = $pdo->prepare('UPDATE payments SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $paymentId]);

        $message = 'Статус документа ' . $payment['invoice_number'] . ' изменён на «' . $statuses[$status] . '».';
        createUserNotification($pdo, (int)$payment['user_id'], 'Статус оплаты обновлён', $message);
        sendPaymentEmail(
            $payment['email'] ?? '',
            'Автокул СТО: статус оплаты обновлён',
            "Здравствуйте, {$payment['full_name']}!\n{$message}"
        );
    }

    header('Location: /admin/payments.php');
    exit;
}

if (isset($_GET['download']) && ctype_digit($_GET['download'])) {
    $paymentId = (int)$_GET['download'];
    $stmt = $pdo->prepare('SELECT p.*, u.full_name, u.email FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = :id');
    $stmt->execute(['id' => $paymentId]);
    $payment = $stmt->fetch();

    if ($payment) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="admin-document-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $payment['invoice_number']) . '.txt"');
        echo renderPaymentDocument($payment, $payment, $statuses, $methods, $documentTypes);
        exit;
    }
}

$sql = "SELECT p.*, u.full_name, u.email
        FROM payments p
        LEFT JOIN users u ON u.id = p.user_id
        ORDER BY p.created_at DESC";
$payments = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .admin-payments-page { padding: 34px 16px 54px; }
    .admin-payments-head { display:flex; justify-content:space-between; gap:18px; align-items:flex-end; margin-bottom:22px; }
    .admin-payments-head h1 { font-size: clamp(30px, 4vw, 44px); margin-bottom:8px; }
    .admin-payments-head p { color:#666; max-width:760px; }
    .admin-payments-summary { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; margin-bottom:22px; }
    .admin-summary-card { background:#fff; border:1px solid #eee; border-radius:18px; padding:18px; box-shadow:0 10px 26px rgba(0,0,0,.05); }
    .admin-summary-card span { color:#777; font-size:13px; }
    .admin-summary-card strong { display:block; font-size:28px; margin-top:4px; }
    .admin-payments-table-wrap { background:#fff; border:1px solid #eee; border-radius:22px; overflow:auto; box-shadow:0 18px 45px rgba(33,33,33,.08); }
    .admin-payments-table { width:100%; border-collapse:collapse; min-width: 920px; }
    .admin-payments-table th { text-align:left; padding:15px; background:#fafafa; border-bottom:1px solid #eee; color:#444; font-size:13px; text-transform:uppercase; letter-spacing:.04em; }
    .admin-payments-table td { padding:15px; border-bottom:1px solid #f2f2f2; vertical-align:middle; }
    .admin-payments-table tr:last-child td { border-bottom:none; }
    .admin-pay-status { display:inline-flex; border-radius:999px; padding:7px 11px; font-weight:700; font-size:13px; white-space:nowrap; }
    .admin-pay-status-pending { background:#fff8e1; color:#9a6400; }
    .admin-pay-status-paid { background:#e8f5e9; color:#1b7f35; }
    .admin-pay-status-cancelled { background:#f1f1f1; color:#666; }
    .admin-pay-status-error { background:#ffebee; color:#b71c1c; }
    .status-form { display:flex; gap:8px; align-items:center; }
    .status-form select { border:1px solid #ddd; border-radius:10px; padding:10px; background:#fbfbfb; }
    .status-form button { border:0; border-radius:10px; padding:10px 14px; background:#d32f2f; color:#fff; font-weight:700; cursor:pointer; }
    .admin-document-link { color:#d32f2f; font-weight:700; }
    .empty-state { border:1px dashed #ddd; border-radius:18px; padding:24px; color:#777; background:#fafafa; }
    @media (max-width: 860px) { .admin-payments-head { display:block; } .admin-payments-summary { grid-template-columns:1fr 1fr; } }
</style>
<div class="container admin-payments-page">
    <div class="admin-payments-head">
        <div>
            <h1>Платежи и документы</h1>
            <p>Администратор видит все платежи пользователей, скачивает документы и вручную меняет статус: «Ожидает оплаты», «Оплачено», «Отменено» или «Ошибка».</p>
        </div>
        <a class="btn btn-outline" href="/admin/index.php">← В админ-панель</a>
    </div>

    <?php
    $summary = array_fill_keys(array_keys($statuses), 0);
    foreach ($payments as $payment) {
        $summary[$payment['status']] = ($summary[$payment['status']] ?? 0) + 1;
    }
    ?>
    <div class="admin-payments-summary">
        <?php foreach ($statuses as $key => $label): ?>
            <div class="admin-summary-card">
                <span><?php echo htmlspecialchars($label); ?></span>
                <strong><?php echo (int)($summary[$key] ?? 0); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($payments)): ?>
        <div class="empty-state">Платежей пока нет. Когда клиент создаст платёж, он появится здесь.</div>
    <?php else: ?>
        <div class="admin-payments-table-wrap">
            <table class="admin-payments-table">
                <thead>
                    <tr>
                        <th>Клиент</th>
                        <th>Документ</th>
                        <th>Сумма</th>
                        <th>Способ</th>
                        <th>Статус</th>
                        <th>Управление</th>
                        <th>Файл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['full_name'] ?? '—'); ?></strong><br>
                                <small><?php echo htmlspecialchars($payment['email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['invoice_number'] ?? '—'); ?></strong><br>
                                <small><?php echo htmlspecialchars($documentTypes[$payment['document_type']] ?? 'Документ'); ?></small>
                            </td>
                            <td><?php echo number_format((float)($payment['amount'] ?? 0), 2, ',', ' '); ?> ₽</td>
                            <td><?php echo htmlspecialchars($methods[$payment['payment_method']] ?? $payment['payment_method'] ?? '—'); ?></td>
                            <td><span class="admin-pay-status <?php echo $statusClasses[$payment['status']] ?? 'admin-pay-status-pending'; ?>"><?php echo htmlspecialchars($statuses[$payment['status']] ?? $payment['status']); ?></span></td>
                            <td>
                                <form method="post" class="status-form">
                                    <input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                                    <select name="status" aria-label="Статус платежа">
                                        <?php foreach ($statuses as $key => $label): ?>
                                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($payment['status'] ?? '') === $key) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Сохранить</button>
                                </form>
                            </td>
                            <td><a class="admin-document-link" href="/admin/payments.php?download=<?php echo (int)$payment['id']; ?>">Скачать</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
