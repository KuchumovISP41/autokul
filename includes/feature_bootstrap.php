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
}

function createUserNotification(PDO $pdo, int $userId, string $title, string $message): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (:uid, :title, :message)');
    $stmt->execute(['uid' => $userId, 'title' => $title, 'message' => $message]);
}

function sendPaymentEmail(string $toEmail, string $subject, string $message): void
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/plain;charset=UTF-8\r\n";
    $headers .= "From: no-reply@autokul.local\r\n";
    @mail($toEmail, $subject, $message, $headers);
}
