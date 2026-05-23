<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/feature_bootstrap.php';
requireAdmin();
$pdo = getDBConnection();
ensureFeatureTables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['status'])) {
    $allowed = ['pending','paid','cancelled','error'];
    $status = in_array($_POST['status'], $allowed, true) ? $_POST['status'] : 'pending';
    $stmt = $pdo->prepare('UPDATE payments SET status = :status WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => (int)$_POST['payment_id']]);

    $userStmt = $pdo->prepare('SELECT p.invoice_number, u.id uid, u.email, u.full_name FROM payments p JOIN users u ON u.id = p.user_id WHERE p.id = :id');
    $userStmt->execute(['id' => (int)$_POST['payment_id']]);
    $row = $userStmt->fetch();
    if ($row) {
        createUserNotification($pdo, (int)$row['uid'], 'Статус платежа изменен', "Счет {$row['invoice_number']} обновлен: {$status}");
        sendPaymentEmail($row['email'], 'Статус платежа', "Здравствуйте, {$row['full_name']}!\nСтатус вашего счета {$row['invoice_number']}: {$status}");
    }
    header('Location: /admin/payments.php');
    exit;
}

$payments = $pdo->query('SELECT p.*, u.full_name, u.email FROM payments p JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC')->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/style.css"><title>Админка - платежи</title></head><body>
<div class="container" style="padding:24px 16px;">
<h1>Управление платежами</h1>
<a href="/admin/index.php">← В админ-панель</a>
<table style="width:100%;margin-top:16px;"><tr><th>Клиент</th><th>Счет</th><th>Сумма</th><th>Метод</th><th>Статус</th><th>Изменить</th></tr>
<?php foreach($payments as $p): ?><tr><td><?=htmlspecialchars($p['full_name'])?><br><small><?=htmlspecialchars($p['email'])?></small></td><td><?=htmlspecialchars($p['invoice_number'])?></td><td><?=$p['amount']?> ₽</td><td><?=htmlspecialchars($p['payment_method'])?></td><td><?=htmlspecialchars($p['status'])?></td><td><form method="post"><input type="hidden" name="payment_id" value="<?=$p['id']?>"><select name="status"><?php foreach(['pending'=>'Ожидает оплаты','paid'=>'Оплачено','cancelled'=>'Отменено','error'=>'Ошибка'] as $k=>$v):?><option value="<?=$k?>" <?=$p['status']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select><button type="submit">Сохранить</button></form></td></tr><?php endforeach; ?>
</table></div></body></html>
