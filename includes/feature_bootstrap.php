<?php
function ensureFeatureTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        appointment_id INT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status ENUM('pending','paid','cancelled','error') NOT NULL DEFAULT 'pending',
        invoice_number VARCHAR(64) NOT NULL,
        document_type VARCHAR(50) NOT NULL DEFAULT 'receipt',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensureColumnExists($pdo, 'payments', 'document_type', "VARCHAR(50) NOT NULL DEFAULT 'receipt'");
}

function ensureColumnExists(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        throw new InvalidArgumentException('Некорректное имя таблицы или колонки.');
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function getPaymentStatuses(): array
{
    return [
        'pending' => 'Ожидает оплаты',
        'paid' => 'Оплачено',
        'cancelled' => 'Отменено',
        'error' => 'Ошибка',
    ];
}

function getPaymentMethods(): array
{
    return [
        'card' => 'Банковская карта',
        'yoomoney' => 'ЮMoney / электронные деньги',
        'invoice' => 'Оплата по счёту',
    ];
}

function getDocumentTypes(): array
{
    return [
        'receipt' => 'Чек об оплате',
        'invoice' => 'Счёт на оплату',
        'report' => 'Отчёт по заказу',
    ];
}

function createUserNotification(PDO $pdo, int $userId, string $title, string $message): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (:uid, :title, :message)');
    $stmt->execute(['uid' => $userId, 'title' => $title, 'message' => $message]);
}

function sendPaymentEmail(string $toEmail, string $subject, string $message): void
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/plain;charset=UTF-8\r\n";
    $headers .= "From: no-reply@autokul.local\r\n";
    @mail($toEmail, $subject, $message, $headers);
}

function renderPaymentDocument(array $payment, array $user, array $statuses, array $methods, array $documentTypes): string
{
    $documentTitle = $documentTypes[$payment['document_type'] ?? 'receipt'] ?? 'Платёжный документ';
    $status = $statuses[$payment['status'] ?? 'pending'] ?? ($payment['status'] ?? '—');
    $method = $methods[$payment['payment_method'] ?? 'card'] ?? ($payment['payment_method'] ?? '—');
    $amount = number_format((float)($payment['amount'] ?? 0), 2, ',', ' ');
    $client = $user['full_name'] ?? 'Клиент';
    $email = $user['email'] ?? '—';
    $invoice = $payment['invoice_number'] ?? '—';
    $created = $payment['created_at'] ?? date('Y-m-d H:i:s');

    return implode("\n", [
        'Автокул СТО',
        $documentTitle,
        str_repeat('=', 34),
        'Клиент: ' . $client,
        'Email: ' . $email,
        'Номер документа: ' . $invoice,
        'Сумма: ' . $amount . ' ₽',
        'Способ оплаты: ' . $method,
        'Статус оплаты: ' . $status,
        'Дата создания: ' . $created,
        '',
        'Документ сформирован автоматически для пользователя сайта.',
    ]);
}
