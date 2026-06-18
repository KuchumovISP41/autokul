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
        service_id INT NULL,
        rating TINYINT NOT NULL,
        text TEXT NOT NULL,
        is_approved TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reviews_user (user_id),
        INDEX idx_reviews_approved (is_approved),
        INDEX idx_reviews_appointment (appointment_id),
        INDEX idx_reviews_service (service_id),
        CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_reviews_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON UPDATE CASCADE ON DELETE SET NULL,
        CONSTRAINT fk_reviews_service FOREIGN KEY (service_id) REFERENCES services(id) ON UPDATE CASCADE ON DELETE SET NULL,
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
    dbEnsureColumn($pdo, 'reviews', 'service_id', 'INT NULL');
    dbEnsureIndex($pdo, 'reviews', 'idx_reviews_service', 'service_id');
    dbEnsureForeignKey($pdo, 'reviews', 'fk_reviews_service', 'service_id', 'services', 'id', 'SET NULL');
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

function dbEnsureIndex(PDO $pdo, string $table, string $index, string $columns): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $index)) {
        throw new InvalidArgumentException('Некорректное имя таблицы или индекса.');
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name'
    );
    $stmt->execute(['table' => $table, 'index_name' => $index]);

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
    }
}

function dbEnsureForeignKey(PDO $pdo, string $table, string $constraint, string $column, string $refTable, string $refColumn, string $onDelete = 'RESTRICT'): void
{
    foreach ([$table, $constraint, $column, $refTable, $refColumn] as $identifier) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Некорректное имя внешнего ключа.');
        }
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint_name AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $stmt->execute(['table' => $table, 'constraint_name' => $constraint]);

    if ((int)$stmt->fetchColumn() === 0) {
        $safeOnDelete = in_array($onDelete, ['RESTRICT', 'CASCADE', 'SET NULL'], true) ? $onDelete : 'RESTRICT';
        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(`{$refColumn}`) ON UPDATE CASCADE ON DELETE {$safeOnDelete}");
    }
}

function seedApplicationData(PDO $pdo): void
{
    seedUsers($pdo);
    seedCategoriesAndServices($pdo);
    seedWorkSchedule($pdo);
    seedDemoAppointmentsAndReviews($pdo);
    seedDemoNotifications($pdo);
}

function seedUsers(PDO $pdo): void
{
    $users = [
        ['Администратор', 'admin@autokul.ru', '+7 (900) 000-00-01', 'admin'],
        ['Иван Петров', 'ivan@example.com', '+7 (900) 000-00-02', 'client'],
        ['Анна Смирнова', 'anna@example.com', '+7 (900) 000-00-04', 'client'],
        ['Сергей Волков', 'sergey@example.com', '+7 (900) 000-00-05', 'client'],
        ['Мария Кузнецова', 'maria@example.com', '+7 (900) 000-00-06', 'client'],
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
        'Кузовные работы' => 'Локальный ремонт, полировка, восстановление лакокрасочного покрытия.',
        'Электрика' => 'Диагностика и ремонт электрооборудования, освещения и зарядной системы.',
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
        ['Кузовные работы', 'Полировка кузова', 'Мягкая абразивная полировка кузова с восстановлением блеска покрытия.', 6500, 240, 'uploads/avatars/default-service.png'],
        ['Кузовные работы', 'Локальная покраска элемента', 'Подбор цвета, подготовка и локальная покраска одного элемента кузова.', 9000, 360, 'uploads/avatars/default-service.png'],
        ['Электрика', 'Диагностика электрики', 'Проверка цепей, генератора, аккумулятора, освещения и электронных блоков.', 1800, 90, 'uploads/avatars/default-service.png'],
        ['Электрика', 'Замена аккумулятора', 'Подбор, замена и регистрация аккумулятора с проверкой зарядной системы.', 1000, 30, 'uploads/avatars/default-service.png'],
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
    $demoClients = [
        [
            'email' => 'ivan@example.com',
            'car' => ['Toyota', 'Camry', 2018, 'JTNB11HK0J3000001', 'А123ВС35'],
            'appointments' => [
                ['+1 day', '10:00:00', 'pending', 'Клиент просит заменить масло и масляный фильтр.', ['Замена масла и фильтра']],
                ['+8 days', '13:00:00', 'confirmed', 'Клиент отмечает периодические сбои электрики, нужна диагностика.', ['Диагностика электрики']],
                ['-7 days', '12:00:00', 'completed', 'Клиент обращался для компьютерной диагностики двигателя.', ['Компьютерная диагностика']],
            ],
            'reviews' => [
                ['Компьютерная диагностика', 5, 'Быстро нашли проблему и подробно объяснили, что нужно сделать. Отличный сервис!'],
            ],
            'payments' => [
                ['AK-2026-0001', 'paid', 'card', 'receipt', 1500],
            ],
        ],
        [
            'email' => 'anna@example.com',
            'car' => ['Kia', 'Rio', 2020, 'Z94CB41BBLR000002', 'В456ОР35'],
            'appointments' => [
                ['-3 days', '14:00:00', 'completed', 'Клиент приезжал на сезонный шиномонтаж с балансировкой.', ['Шиномонтаж R15–R17']],
                ['+3 days', '11:00:00', 'confirmed', 'Клиент планирует осмотр автомобиля перед покупкой.', ['Диагностика перед покупкой']],
                ['+10 days', '09:00:00', 'pending', 'Клиент хочет восстановить блеск кузова и убрать мелкие царапины.', ['Полировка кузова']],
            ],
            'reviews' => [
                ['Шиномонтаж R15–R17', 5, 'Записалась онлайн, приехала без очереди. Колёса отбалансировали аккуратно, рекомендую.'],
            ],
            'payments' => [
                ['AK-2026-0002', 'paid', 'card', 'receipt', 2400],
                ['AK-2026-0003', 'pending', 'invoice', 'invoice', 3500],
            ],
        ],
        [
            'email' => 'sergey@example.com',
            'car' => ['Volkswagen', 'Polo', 2017, 'XW8ZZZ61ZHG000003', 'С789МТ35'],
            'appointments' => [
                ['-14 days', '09:00:00', 'completed', 'Клиент обращался для замены передних тормозных колодок.', ['Замена тормозных колодок']],
                ['+5 days', '15:00:00', 'pending', 'Клиент записался на плановое техническое обслуживание.', ['Плановое ТО']],
            ],
            'reviews' => [
                ['Замена тормозных колодок', 4, 'Работу сделали качественно, после ремонта тормоза стали заметно лучше.'],
            ],
            'payments' => [
                ['AK-2026-0004', 'paid', 'yoomoney', 'receipt', 2200],
            ],
        ],
        [
            'email' => 'maria@example.com',
            'car' => ['Hyundai', 'Creta', 2021, 'TMAJ3815BMJ000004', 'Е321КХ35'],
            'appointments' => [
                ['-1 day', '16:00:00', 'completed', 'Клиент приезжал для ремонта прокола колеса.', ['Ремонт прокола']],
                ['+6 days', '12:00:00', 'confirmed', 'Клиент просит проверить аккумулятор и при необходимости заменить.', ['Замена аккумулятора']],
            ],
            'reviews' => [
                ['Ремонт прокола', 5, 'Прокол устранили за полчаса, всё прозрачно по цене и без навязанных работ.'],
            ],
            'payments' => [
                ['AK-2026-0005', 'paid', 'card', 'receipt', 700],
            ],
        ],
    ];

    $carStmt = $pdo->prepare(
        'INSERT INTO cars (user_id, brand, model, year, vin, license_plate)
         SELECT :user_id, :brand, :model, :year, :vin, :license_plate
         WHERE NOT EXISTS (SELECT 1 FROM cars WHERE user_id = :user_check AND license_plate = :plate_check)'
    );
    $appointmentStmt = $pdo->prepare(
        'INSERT INTO appointments (user_id, car_id, appointment_date, appointment_time, status, notes)
         SELECT :user_id, :car_id, :appointment_date, :appointment_time, :status, :notes
         WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE user_id = :user_check AND notes = :notes_check)'
    );
    $linkStmt = $pdo->prepare('INSERT IGNORE INTO appointment_services (appointment_id, service_id) VALUES (:appointment_id, :service_id)');
    $reviewStmt = $pdo->prepare(
        'INSERT INTO reviews (user_id, appointment_id, service_id, rating, text, is_approved)
         SELECT :user_id, :appointment_id, :service_id, :rating, :text, 1
         WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE user_id = :user_check AND text = :text_check)'
    );
    $paymentStmt = $pdo->prepare(
        'INSERT INTO payments (user_id, appointment_id, amount, payment_method, status, invoice_number, document_type)
         SELECT :user_id, :appointment_id, :amount, :payment_method, :status, :invoice_number, :document_type
         WHERE NOT EXISTS (SELECT 1 FROM payments WHERE invoice_number = :invoice_check)'
    );

    foreach ($demoClients as $client) {
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $userStmt->execute(['email' => $client['email']]);
        $userId = (int)$userStmt->fetchColumn();
        if ($userId <= 0) {
            continue;
        }

        [$brand, $model, $year, $vin, $plate] = $client['car'];
        $carStmt->execute([
            'user_id' => $userId,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'vin' => $vin,
            'license_plate' => $plate,
            'user_check' => $userId,
            'plate_check' => $plate,
        ]);

        $carSelect = $pdo->prepare('SELECT id FROM cars WHERE user_id = :user_id AND license_plate = :plate LIMIT 1');
        $carSelect->execute(['user_id' => $userId, 'plate' => $plate]);
        $carId = (int)$carSelect->fetchColumn();
        if ($carId <= 0) {
            continue;
        }

        $appointmentsByService = [];
        foreach ($client['appointments'] as [$dateOffset, $time, $status, $notes, $serviceNames]) {
            $appointmentDate = date('Y-m-d', strtotime($dateOffset));
            $appointmentStmt->execute([
                'user_id' => $userId,
                'car_id' => $carId,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $time,
                'status' => $status,
                'notes' => $notes,
                'user_check' => $userId,
                'notes_check' => $notes,
            ]);

            $appointmentSelect = $pdo->prepare('SELECT id FROM appointments WHERE user_id = :user_id AND notes = :notes LIMIT 1');
            $appointmentSelect->execute(['user_id' => $userId, 'notes' => $notes]);
            $appointmentId = (int)$appointmentSelect->fetchColumn();
            if ($appointmentId <= 0) {
                continue;
            }

            foreach ($serviceNames as $serviceName) {
                $serviceId = getDemoServiceId($pdo, $serviceName);
                if ($serviceId > 0) {
                    $linkStmt->execute(['appointment_id' => $appointmentId, 'service_id' => $serviceId]);
                    $appointmentsByService[$serviceName] = $appointmentId;
                }
            }
        }

        foreach ($client['reviews'] as [$serviceName, $rating, $text]) {
            $serviceId = getDemoServiceId($pdo, $serviceName);
            $reviewStmt->execute([
                'user_id' => $userId,
                'appointment_id' => $appointmentsByService[$serviceName] ?? null,
                'service_id' => $serviceId > 0 ? $serviceId : null,
                'rating' => $rating,
                'text' => $text,
                'user_check' => $userId,
                'text_check' => $text,
            ]);
        }

        foreach ($client['payments'] as [$invoice, $status, $method, $documentType, $amount]) {
            $appointmentId = null;
            foreach ($appointmentsByService as $candidateAppointmentId) {
                $appointmentId = $candidateAppointmentId;
                break;
            }
            $paymentStmt->execute([
                'user_id' => $userId,
                'appointment_id' => $appointmentId,
                'amount' => $amount,
                'payment_method' => $method,
                'status' => $status,
                'invoice_number' => $invoice,
                'document_type' => $documentType,
                'invoice_check' => $invoice,
            ]);
        }
    }
}


function seedDemoNotifications(PDO $pdo): void
{
    $notifications = [
        ['ivan@example.com', 'Запись создана', 'Ваша запись на обслуживание принята и ожидает подтверждения администратора.', 0],
        ['ivan@example.com', 'Рекомендация мастера', 'После диагностики электрики рекомендуем проверить состояние аккумулятора перед зимой.', 0],
        ['anna@example.com', 'Запись подтверждена', 'Запись на диагностику перед покупкой подтверждена. Ждём вас в выбранное время.', 0],
        ['sergey@example.com', 'Оплата получена', 'Платёж за замену тормозных колодок успешно проведён.', 1],
        ['maria@example.com', 'Спасибо за отзыв', 'Ваш отзыв опубликован на сайте после модерации.', 1],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, is_read)
         SELECT u.id, :title, :message, :is_read
         FROM users u
         WHERE u.email = :email
           AND NOT EXISTS (
               SELECT 1 FROM notifications n
               WHERE n.user_id = u.id AND n.title = :title_check AND n.message = :message_check
           )'
    );

    foreach ($notifications as [$email, $title, $message, $isRead]) {
        $stmt->execute([
            'email' => $email,
            'title' => $title,
            'message' => $message,
            'is_read' => $isRead,
            'title_check' => $title,
            'message_check' => $message,
        ]);
    }
}

function getDemoServiceId(PDO $pdo, string $serviceName): int
{
    static $cache = [];
    if (!array_key_exists($serviceName, $cache)) {
        $stmt = $pdo->prepare('SELECT id FROM services WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $serviceName]);
        $cache[$serviceName] = (int)$stmt->fetchColumn();
    }

    return $cache[$serviceName];
}
