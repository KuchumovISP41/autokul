<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAuth('admin');

$page_title = 'Платежи — Панель управления';
$pdo = getDBConnection();

$statuses = [
    'pending' => 'Ожидает оплаты',
    'paid' => 'Оплачено',
    'cancelled' => 'Отменено',
    'error' => 'Ошибка'
];

$paymentsTableExists = (bool)$pdo->query("SHOW TABLES LIKE 'payments'")->fetchColumn();

if ($paymentsTableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['status'])) {
    $status = $_POST['status'];
    if (!array_key_exists($status, $statuses)) {
        $status = 'pending';
    }

    $stmt = $pdo->prepare('UPDATE payments SET status = :status WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'id' => (int)$_POST['payment_id']
    ]);

    header('Location: /admin/payments.php');
    exit;
}

$payments = [];
if ($paymentsTableExists) {
    $sql = "SELECT p.*, u.full_name, u.email
            FROM payments p
            LEFT JOIN users u ON u.id = p.user_id
            ORDER BY p.created_at DESC";
    $payments = $pdo->query($sql)->fetchAll();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title><?php echo $page_title; ?></title>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="container" style="padding: 24px 16px;">
    <h1>Платежи</h1>
    <p><a href="/admin/index.php">← Назад в админ-панель</a></p>

    <?php if (!$paymentsTableExists): ?>
        <div style="padding:12px;border:1px solid #ddd;border-radius:8px;">
            Таблица <code>payments</code> не найдена в базе данных.
        </div>
    <?php elseif (empty($payments)): ?>
        <div style="padding:12px;border:1px solid #ddd;border-radius:8px;">Платежей пока нет.</div>
    <?php else: ?>
        <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid #eee;">Клиент</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid #eee;">Счёт</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid #eee;">Сумма</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid #eee;">Метод</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid #eee;">Статус</th>
                    <th style="text-align:left; padding:10px; border-bottom:1px solid #eee;">Изменить</th>
                </tr>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;">
                            <?php echo htmlspecialchars($payment['full_name'] ?? '—'); ?><br>
                            <small><?php echo htmlspecialchars($payment['email'] ?? ''); ?></small>
                        </td>
                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;"><?php echo htmlspecialchars($payment['invoice_number'] ?? '—'); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;"><?php echo number_format((float)($payment['amount'] ?? 0), 2, '.', ' '); ?> ₽</td>
                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;"><?php echo htmlspecialchars($payment['payment_method'] ?? '—'); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;"><?php echo htmlspecialchars($statuses[$payment['status']] ?? $payment['status']); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f3f3f3;">
                            <form method="post" style="display:flex; gap:8px;">
                                <input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                                <select name="status">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($payment['status'] ?? '') === $key) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Сохранить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
