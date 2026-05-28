<?php
/**
 * Автоматическая подготовка MySQL для первого запуска на Railway/локально.
 * Файл создаёт таблицы и стартовые данные, чтобы не импортировать SQL вручную.
 */

function bootstrapDatabase(PDO $pdo): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    createApplicationTables($pdo);
    ensureApplicationColumns($pdo);
    seedApplicationData($pdo);

    $bootstrapped = true;
}

function createApplicationTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NULL,
        role ENUM('client','admin','mechanic') NOT NULL DEFAULT 'client',
        avatar VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_users_email (email),
        INDEX idx_users_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_categories_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(190) NOT NULL,
        description TEXT NULL,
        image VARCHAR(255) NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        duration INT NOT NULL DEFAULT 60,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_services_category (category_id),
        INDEX idx_services_active (is_active),
        CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        brand VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        year SMALLINT NULL,
        vin VARCHAR(32) NULL,
        license_plate VARCHAR(32) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cars_user (user_id),
        CONSTRAINT fk_cars_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        car_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status ENUM('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_appointments_user (user_id),
        INDEX idx_appointments_car (car_id),
        INDEX idx_appointments_date_status (appointment_date, status),
        CONSTRAINT fk_appointments_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_appointments_car FOREIGN KEY (car_id) REFERENCES cars(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_services (
        appointment_id INT NOT NULL,
        service_id INT NOT NULL,
        PRIMARY KEY (appointment_id, service_id),
        INDEX idx_appointment_services_service (service_id),
        CONSTRAINT fk_appointment_services_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_appointment_services_service FOREIGN KEY (service_id) REFERENCES services(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        appointment_id INT NULL,
        rating TINYINT NOT NULL,
        text TEXT NOT NULL,
        is_approved TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reviews_user (user_id),
        INDEX idx_reviews_approved (is_approved),
        INDEX idx_reviews_appointment (appointment_id),
        CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_reviews_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS work_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day_of_week TINYINT NOT NULL,
        start_time TIME NOT NULL DEFAULT '09:00:00',
        end_time TIME NOT NULL DEFAULT '18:00:00',
        slot_duration INT NOT NULL DEFAULT 60,
        is_working TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_work_schedule_day (day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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
        INDEX idx_payments_user (user_id),
        INDEX idx_payments_status (status),
        CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_payments_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notifications_user (user_id),
        CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensureApplicationColumns(PDO $pdo): void
{
    dbEnsureColumn($pdo, 'users', 'avatar', 'VARCHAR(255) NULL');
    dbEnsureColumn($pdo, 'services', 'image', 'VARCHAR(255) NULL');
    dbEnsureColumn($pdo, 'work_schedule', 'slot_duration', 'INT NOT NULL DEFAULT 60');
    dbEnsureColumn($pdo, 'work_schedule', 'is_working', 'TINYINT(1) NOT NULL DEFAULT 1');
    dbEnsureColumn($pdo, 'payments', 'document_type', "VARCHAR(50) NOT NULL DEFAULT 'receipt'");
}

function dbEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void
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

function seedApplicationData(PDO $pdo): void
{
    seedUsers($pdo);
    seedCategoriesAndServices($pdo);
    seedWorkSchedule($pdo);
    seedDemoAppointmentsAndReviews($pdo);
}

function seedUsers(PDO $pdo): void
{
    $users = [
        ['Администратор', 'admin@autokul.ru', '+7 (900) 000-00-01', 'admin'],
        ['Иван Петров', 'ivan@example.com', '+7 (900) 000-00-02', 'client'],
        ['Механик Автокул', 'mechanic@autokul.ru', '+7 (900) 000-00-03', 'mechanic'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password, phone, role)
         SELECT :full_name, :email, :password, :phone, :role
         WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = :email_check)'
    );

    foreach ($users as [$name, $email, $phone, $role]) {
        $stmt->execute([
            'full_name' => $name,
            'email' => $email,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'phone' => $phone,
            'role' => $role,
            'email_check' => $email,
        ]);
    }
}

function seedCategoriesAndServices(PDO $pdo): void
{
    $categories = [
        'Диагностика' => 'Компьютерная диагностика, проверка систем и поиск неисправностей.',
        'Техническое обслуживание' => 'Плановые работы, замена жидкостей и расходников.',
        'Ремонт ходовой' => 'Подвеска, рулевое управление и тормозная система.',
        'Шиномонтаж' => 'Сезонная замена шин, балансировка и ремонт колёс.',
    ];

    $categoryStmt = $pdo->prepare(
        'INSERT INTO categories (name, description)
         SELECT :name, :description
         WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = :name_check)'
    );
    foreach ($categories as $name => $description) {
        $categoryStmt->execute(['name' => $name, 'description' => $description, 'name_check' => $name]);
    }

    $services = [
        ['Диагностика', 'Компьютерная диагностика', 'Считывание ошибок, проверка датчиков и электронных систем автомобиля.', 1500, 60, 'uploads/avatars/default-service.png'],
        ['Диагностика', 'Диагностика перед покупкой', 'Комплексная проверка автомобиля перед покупкой с рекомендациями мастера.', 3500, 120, 'uploads/avatars/default-service.png'],
        ['Техническое обслуживание', 'Замена масла и фильтра', 'Замена моторного масла, масляного фильтра и базовый осмотр автомобиля.', 1200, 45, 'uploads/avatars/default-service.png'],
        ['Техническое обслуживание', 'Плановое ТО', 'Плановое техническое обслуживание по регламенту производителя.', 4500, 180, 'uploads/avatars/default-service.png'],
        ['Ремонт ходовой', 'Диагностика подвески', 'Проверка амортизаторов, рычагов, сайлентблоков и рулевого управления.', 1000, 45, 'uploads/avatars/default-service.png'],
        ['Ремонт ходовой', 'Замена тормозных колодок', 'Замена передних или задних тормозных колодок с проверкой тормозной системы.', 2200, 90, 'uploads/avatars/default-service.png'],
        ['Шиномонтаж', 'Шиномонтаж R15–R17', 'Снятие, установка, балансировка комплекта колёс.', 2400, 60, 'uploads/avatars/default-service.png'],
        ['Шиномонтаж', 'Ремонт прокола', 'Ремонт прокола шины с проверкой герметичности.', 700, 30, 'uploads/avatars/default-service.png'],
    ];

    $serviceStmt = $pdo->prepare(
        'INSERT INTO services (category_id, name, description, image, price, duration, is_active)
         SELECT c.id, :name, :description, :image, :price, :duration, 1
         FROM categories c
         WHERE c.name = :category
           AND NOT EXISTS (SELECT 1 FROM services s WHERE s.name = :name_check)'
    );

    foreach ($services as [$category, $name, $description, $price, $duration, $image]) {
        $serviceStmt->execute([
            'category' => $category,
            'name' => $name,
            'description' => $description,
            'image' => $image,
            'price' => $price,
            'duration' => $duration,
            'name_check' => $name,
        ]);
    }
}

function seedWorkSchedule(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO work_schedule (day_of_week, start_time, end_time, slot_duration, is_working)
         VALUES (:day, :start_time, :end_time, :slot_duration, 1)
         ON DUPLICATE KEY UPDATE day_of_week = VALUES(day_of_week)'
    );

    for ($day = 1; $day <= 7; $day++) {
        $stmt->execute([
            'day' => $day,
            'start_time' => '09:00:00',
            'end_time' => $day >= 6 ? '16:00:00' : '18:00:00',
            'slot_duration' => 60,
        ]);
    }
}

function seedDemoAppointmentsAndReviews(PDO $pdo): void
{
    $clientId = (int)$pdo->query("SELECT id FROM users WHERE email = 'ivan@example.com' LIMIT 1")->fetchColumn();
    if ($clientId <= 0) {
        return;
    }

    $carStmt = $pdo->prepare(
        'INSERT INTO cars (user_id, brand, model, year, vin, license_plate)
         SELECT :user_id, :brand, :model, :year, :vin, :license_plate
         WHERE NOT EXISTS (SELECT 1 FROM cars WHERE user_id = :user_check AND license_plate = :plate_check)'
    );
    $carStmt->execute([
        'user_id' => $clientId,
        'brand' => 'Toyota',
        'model' => 'Camry',
        'year' => 2018,
        'vin' => 'JTNB11HK0J3000001',
        'license_plate' => 'А123ВС35',
        'user_check' => $clientId,
        'plate_check' => 'А123ВС35',
    ]);

    $carId = (int)$pdo->query("SELECT id FROM cars WHERE user_id = {$clientId} ORDER BY id LIMIT 1")->fetchColumn();
    $serviceId = (int)$pdo->query("SELECT id FROM services ORDER BY id LIMIT 1")->fetchColumn();
    if ($carId <= 0 || $serviceId <= 0) {
        return;
    }

    if ((int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn() === 0) {
        $appointmentStmt = $pdo->prepare(
            'INSERT INTO appointments (user_id, car_id, appointment_date, appointment_time, status, notes)
             VALUES (:user_id, :car_id, :appointment_date, :appointment_time, :status, :notes)'
        );
        $appointmentStmt->execute([
            'user_id' => $clientId,
            'car_id' => $carId,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => '10:00:00',
            'status' => 'pending',
            'notes' => 'Демо-запись для проверки сайта после деплоя.',
        ]);
        $pendingAppointmentId = (int)$pdo->lastInsertId();

        $appointmentStmt->execute([
            'user_id' => $clientId,
            'car_id' => $carId,
            'appointment_date' => date('Y-m-d', strtotime('-7 days')),
            'appointment_time' => '12:00:00',
            'status' => 'completed',
            'notes' => 'Демо-выполненная запись для отзывов и статистики.',
        ]);
        $completedAppointmentId = (int)$pdo->lastInsertId();

        $linkStmt = $pdo->prepare('INSERT IGNORE INTO appointment_services (appointment_id, service_id) VALUES (:appointment_id, :service_id)');
        $linkStmt->execute(['appointment_id' => $pendingAppointmentId, 'service_id' => $serviceId]);
        $linkStmt->execute(['appointment_id' => $completedAppointmentId, 'service_id' => $serviceId]);

        $reviewStmt = $pdo->prepare(
            'INSERT INTO reviews (user_id, appointment_id, rating, text, is_approved)
             VALUES (:user_id, :appointment_id, 5, :text, 1)'
        );
        $reviewStmt->execute([
            'user_id' => $clientId,
            'appointment_id' => $completedAppointmentId,
            'text' => 'Быстро нашли проблему и подробно объяснили, что нужно сделать. Отличный сервис!',
        ]);
    }
}
