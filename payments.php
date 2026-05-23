<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/feature_bootstrap.php';
requireLogin();

$page_title = 'Оплата и документы';
$pdo = getDBConnection();
ensureFeatureTables($pdo);

$userId = (int)$_SESSION['user_id'];
$userStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :id');
$userStmt->execute(['id' => $userId]);
$user = $userStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    $method = $_POST['payment_method'] ?? 'card';
    $methods = ['card', 'yoomoney', 'invoice'];
    if (!in_array($method, $methods, true)) $method = 'card';

    $amount = max(0, (float)($_POST['amount'] ?? 0));
    if ($amount > 0) {
        $invoice = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('INSERT INTO payments (user_id, amount, payment_method, status, invoice_number) VALUES (:uid, :amount, :method, :status, :invoice)');
        $stmt->execute(['uid' => $userId, 'amount' => $amount, 'method' => $method, 'status' => 'pending', 'invoice' => $invoice]);

        createUserNotification($pdo, $userId, 'Платеж создан', "Счет {$invoice} создан и ожидает оплаты.");
        sendPaymentEmail($user['email'], 'Создан новый счет', "Здравствуйте, {$user['full_name']}!\nВаш счет {$invoice} на сумму {$amount} ₽ создан.");
    }
    header('Location: /payments.php');
    exit;
}

if (isset($_GET['download']) && ctype_digit($_GET['download'])) {
    $pid = (int)$_GET['download'];
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = :id AND user_id = :uid');
    $st->execute(['id' => $pid, 'uid' => $userId]);
    $payment = $st->fetch();
    if ($payment) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="document-' . $payment['invoice_number'] . '.txt"');
        echo "Документ оплаты\n";
        echo "Пользователь: {$user['full_name']}\n";
        echo "Счет: {$payment['invoice_number']}\n";
        echo "Сумма: {$payment['amount']} ₽\n";
        echo "Метод: {$payment['payment_method']}\n";
        echo "Статус: {$payment['status']}\n";
        echo "Дата: {$payment['created_at']}\n";
        exit;
    }
}

$pays = $pdo->prepare('SELECT * FROM payments WHERE user_id = :uid ORDER BY created_at DESC');
$pays->execute(['uid' => $userId]);
$payments = $pays->fetchAll();

$not = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10');
$not->execute(['uid' => $userId]);
$notifications = $not->fetchAll();

require_once 'includes/header.php';
?>
<div class="container" style="padding:24px 16px;">
<h1>Оплата, уведомления и документы</h1>
<form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
<input type="hidden" name="create_payment" value="1">
<div><label>Сумма</label><input type="number" name="amount" min="1" step="1" required style="width:100%"></div>
<div><label>Способ оплаты</label><select name="payment_method" style="width:100%"><option value="card">Карта</option><option value="yoomoney">ЮMoney</option><option value="invoice">Оплата по счету</option></select></div>
<button class="btn btn-primary" type="submit">Создать платеж</button>
</form>

<h2 style="margin-top:24px;">Мои платежи</h2>
<div style="overflow:auto"><table class="appointments-table" style="width:100%"><tr><th>Счет</th><th>Сумма</th><th>Метод</th><th>Статус</th><th>Документ</th></tr>
<?php foreach($payments as $p): ?><tr><td><?=htmlspecialchars($p['invoice_number'])?></td><td><?=$p['amount']?> ₽</td><td><?=htmlspecialchars($p['payment_method'])?></td><td><?=htmlspecialchars($p['status'])?></td><td><a href="/payments.php?download=<?=$p['id']?>">Скачать чек/отчет</a></td></tr><?php endforeach; ?>
</table></div>

<h2 style="margin-top:24px;">Уведомления на сайте</h2>
<?php foreach($notifications as $n): ?><div style="padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px;"><strong><?=htmlspecialchars($n['title'])?></strong><br><?=htmlspecialchars($n['message'])?></div><?php endforeach; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
