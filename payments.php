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
$user = $userStmt->fetch() ?: ['full_name' => $_SESSION['user_name'] ?? 'Клиент', 'email' => $_SESSION['user_email'] ?? ''];

$statuses = getPaymentStatuses();
$methods = getPaymentMethods();
$documentTypes = getDocumentTypes();
$statusClasses = [
    'pending' => 'pay-status-pending',
    'paid' => 'pay-status-paid',
    'cancelled' => 'pay-status-cancelled',
    'error' => 'pay-status-error',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    $method = $_POST['payment_method'] ?? 'card';
    if (!array_key_exists($method, $methods)) {
        $method = 'card';
    }

    $documentType = $_POST['document_type'] ?? 'receipt';
    if (!array_key_exists($documentType, $documentTypes)) {
        $documentType = 'receipt';
    }

    $amount = max(0, (float)($_POST['amount'] ?? 0));
    if ($amount > 0) {
        $invoice = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('INSERT INTO payments (user_id, amount, payment_method, status, invoice_number, document_type) VALUES (:uid, :amount, :method, :status, :invoice, :document_type)');
        $stmt->execute([
            'uid' => $userId,
            'amount' => $amount,
            'method' => $method,
            'status' => 'pending',
            'invoice' => $invoice,
            'document_type' => $documentType,
        ]);

        $formattedAmount = number_format($amount, 2, ',', ' ');
        createUserNotification($pdo, $userId, 'Платёж создан', "Документ {$invoice} на сумму {$formattedAmount} ₽ создан. Статус: Ожидает оплаты.");
        sendPaymentEmail(
            $user['email'],
            'Автокул СТО: создан платёж',
            "Здравствуйте, {$user['full_name']}!\nДокумент {$invoice} на сумму {$formattedAmount} ₽ создан.\nСпособ оплаты: {$methods[$method]}.\nТекущий статус: Ожидает оплаты."
        );
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
        header('Content-Disposition: attachment; filename="document-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $payment['invoice_number']) . '.txt"');
        echo renderPaymentDocument($payment, $user, $statuses, $methods, $documentTypes);
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
<style>
    .payments-page { padding: 34px 16px 52px; }
    .payments-hero { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(300px, .8fr); gap: 24px; align-items: stretch; margin-bottom: 28px; }
    .payments-panel { background: #fff; border: 1px solid #ececec; border-radius: 22px; box-shadow: 0 18px 45px rgba(33, 33, 33, .08); padding: 26px; }
    .payments-intro { background: linear-gradient(135deg, #1f1f1f 0%, #3a1717 52%, #d32f2f 100%); color: #fff; position: relative; overflow: hidden; }
    .payments-intro::after { content: ''; position: absolute; right: -70px; bottom: -90px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255,255,255,.12); }
    .payments-intro h1 { font-size: clamp(30px, 4vw, 46px); line-height: 1.05; margin-bottom: 14px; }
    .payments-intro p { max-width: 680px; color: rgba(255,255,255,.86); font-size: 17px; }
    .payment-benefits { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 24px; }
    .payment-benefit { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 16px; padding: 14px; }
    .payment-form { display: grid; gap: 16px; }
    .payment-form label { display: block; font-weight: 700; margin-bottom: 7px; color: #2b2b2b; }
    .payment-form input, .payment-form select { width: 100%; border: 1px solid #ddd; border-radius: 12px; padding: 13px 14px; font-size: 15px; background: #fbfbfb; }
    .payment-methods { display: grid; gap: 10px; }
    .payment-method { display: flex; gap: 10px; align-items: center; border: 1px solid #e8e8e8; border-radius: 14px; padding: 12px; background: #fafafa; cursor: pointer; }
    .payment-method input { width: auto; accent-color: #d32f2f; }
    .payment-method strong { display: block; line-height: 1.2; }
    .payment-method span { color: #777; font-size: 13px; }
    .payment-section-title { display:flex; justify-content:space-between; gap:16px; align-items:flex-end; margin: 30px 0 14px; }
    .payments-grid { display:grid; gap:14px; }
    .payment-card { border:1px solid #eee; border-radius:18px; padding:18px; background:#fff; box-shadow: 0 10px 28px rgba(0,0,0,.05); display:grid; grid-template-columns: 1fr auto; gap: 14px; align-items:center; }
    .payment-meta { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; color:#666; font-size:14px; }
    .pay-status { display:inline-flex; align-items:center; border-radius:999px; padding:7px 11px; font-weight:700; font-size:13px; }
    .pay-status-pending { background:#fff8e1; color:#9a6400; }
    .pay-status-paid { background:#e8f5e9; color:#1b7f35; }
    .pay-status-cancelled { background:#f1f1f1; color:#666; }
    .pay-status-error { background:#ffebee; color:#b71c1c; }
    .notification-list { display:grid; gap:10px; }
    .notification-card { border-left:4px solid #d32f2f; background:#fff; border-radius:14px; padding:14px 16px; box-shadow: 0 8px 22px rgba(0,0,0,.05); }
    .empty-state { border:1px dashed #ddd; border-radius:18px; padding:24px; color:#777; background:#fafafa; }
    @media (max-width: 860px) { .payments-hero { grid-template-columns: 1fr; } .payment-benefits { grid-template-columns: 1fr; } .payment-card { grid-template-columns: 1fr; } }
</style>
<div class="container payments-page">
    <section class="payments-hero">
        <div class="payments-panel payments-intro">
            <h1>Оплата, уведомления и документы</h1>
            <p>Создавайте платежи, выбирайте удобный способ оплаты, отслеживайте статус и скачивайте нужный документ: чек, счёт или отчёт.</p>
            <div class="payment-benefits">
                <div class="payment-benefit"><strong>3 способа оплаты</strong><br>карта, ЮMoney, счёт</div>
                <div class="payment-benefit"><strong>4 статуса</strong><br>ожидает, оплачено, отменено, ошибка</div>
                <div class="payment-benefit"><strong>Документы</strong><br>для каждого пользователя</div>
            </div>
        </div>

        <form method="post" class="payments-panel payment-form">
            <input type="hidden" name="create_payment" value="1">
            <h2>Создать платёж</h2>
            <div>
                <label for="amount">Сумма к оплате</label>
                <input id="amount" type="number" name="amount" min="1" step="1" placeholder="Например, 3500" required>
            </div>
            <div>
                <label for="document_type">Документ</label>
                <select id="document_type" name="document_type">
                    <?php foreach ($documentTypes as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Способ оплаты</label>
                <div class="payment-methods">
                    <label class="payment-method"><input type="radio" name="payment_method" value="card" checked><span><strong>Банковская карта</strong><span>Visa, Mastercard, МИР</span></span></label>
                    <label class="payment-method"><input type="radio" name="payment_method" value="yoomoney"><span><strong>Электронные деньги</strong><span>ЮMoney и похожие кошельки</span></span></label>
                    <label class="payment-method"><input type="radio" name="payment_method" value="invoice"><span><strong>Оплата по счёту</strong><span>Для организаций и безналичной оплаты</span></span></label>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Создать платёж</button>
        </form>
    </section>

    <section>
        <div class="payment-section-title">
            <div>
                <h2>Мои платежи</h2>
                <p>Статус обновляет администратор после проверки оплаты.</p>
            </div>
        </div>
        <?php if (empty($payments)): ?>
            <div class="empty-state">Платежей пока нет. Создайте первый платёж в форме выше.</div>
        <?php else: ?>
            <div class="payments-grid">
                <?php foreach ($payments as $payment): ?>
                    <article class="payment-card">
                        <div>
                            <h3><?php echo htmlspecialchars($payment['invoice_number']); ?></h3>
                            <div class="payment-meta">
                                <span><?php echo number_format((float)$payment['amount'], 2, ',', ' '); ?> ₽</span>
                                <span><?php echo htmlspecialchars($methods[$payment['payment_method']] ?? $payment['payment_method']); ?></span>
                                <span><?php echo htmlspecialchars($documentTypes[$payment['document_type']] ?? 'Документ'); ?></span>
                                <span><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($payment['created_at']))); ?></span>
                            </div>
                        </div>
                        <div style="display:grid; gap:10px; justify-items:start;">
                            <span class="pay-status <?php echo $statusClasses[$payment['status']] ?? 'pay-status-pending'; ?>"><?php echo htmlspecialchars($statuses[$payment['status']] ?? $payment['status']); ?></span>
                            <a class="btn btn-outline" href="/payments.php?download=<?php echo (int)$payment['id']; ?>">Скачать документ</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <div class="payment-section-title">
            <div>
                <h2>Уведомления на сайте</h2>
                <p>Здесь отображаются изменения по платежам. Дублирование также отправляется на email.</p>
            </div>
        </div>
        <?php if (empty($notifications)): ?>
            <div class="empty-state">Уведомлений пока нет.</div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card">
                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
                        <?php echo htmlspecialchars($notification['message']); ?>
                        <div style="color:#888; font-size:13px; margin-top:6px;"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($notification['created_at']))); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once 'includes/footer.php'; ?>
