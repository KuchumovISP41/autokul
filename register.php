<?php
// register.php - Страница регистрации нового пользователя

require_once 'includes/config.php';
require_once 'includes/auth_check.php';

// Заголовок страницы
$page_title = 'Регистрация — Автокул СТО';

// Массив для ошибок
$errors = [];
// Данные формы (чтобы не терять при ошибке)
$form_data = [
    'full_name' => '',
    'email' => '',
    'phone' => ''
];

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Получаем и очищаем данные
    $form_data['full_name'] = normalizeSpaces($_POST['full_name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = formatPhoneMask($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // ========== ВАЛИДАЦИЯ НА СЕРВЕРЕ ==========
    
    // Проверка имени
    if ($error = validateHumanName($form_data['full_name'], 'Полное имя', 3, 150)) {
        $errors['full_name'] = $error;
    }
    
    // Проверка email
    if ($error = validateEmailValue($form_data['email'])) {
        $errors['email'] = $error;
    } else {
        // Проверяем, нет ли уже такого email в БД
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $form_data['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Пользователь с таким email уже зарегистрирован. <a href="/login.php">Войти?</a>';
        }
    }
    
    // Проверка телефона (опционально)
    if ($error = validatePhone($form_data['phone'], false)) {
        $errors['phone'] = $error;
    }
    
    // Проверка пароля
    if ($error = validatePasswordRules($password, true)) {
        $errors['password'] = $error;
    }
    
    // Проверка подтверждения пароля
    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'Пароли не совпадают';
    }
    
    // ========== ЕСЛИ ОШИБОК НЕТ — РЕГИСТРИРУЕМ ==========
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            
            // Хешируем пароль (bcrypt, стоимость 10 — оптимально)
            $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            
            // Добавляем пользователя в БД
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, phone, role, created_at)
                VALUES (:full_name, :email, :password, :phone, 'client', :created_at)
            ");
            
            $stmt->execute([
                'full_name' => $form_data['full_name'],
                'email' => $form_data['email'],
                'password' => $password_hash,
                'phone' => $form_data['phone'] ?: null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Автоматически авторизуем после регистрации
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $form_data['full_name'];
            $_SESSION['user_email'] = $form_data['email'];
            $_SESSION['user_role'] = 'client';
            
            // Перенаправляем на главную (или туда, куда хотел)
            $redirect = $_SESSION['redirect_after_login'] ?? '/profile.php';
            unset($_SESSION['redirect_after_login']);
            
            header('Location: ' . $redirect);
            exit;
            
        } catch (PDOException $e) {
            $errors['db'] = 'Ошибка при регистрации. Попробуйте позже.';
            // В реальном проекте: запись в лог-файл
            error_log('Ошибка регистрации: ' . $e->getMessage());
        }
    }
}

// Подключаем шапку
require_once 'includes/header.php';
?>

<!-- ========== ФОРМА РЕГИСТРАЦИИ ========== -->
<section style="padding: 60px 0; min-height: calc(100vh - 400px);">
    <div class="container">
        <div style="max-width: 500px; margin: 0 auto;">
            
            <h1 style="text-align: center; margin-bottom: 8px; font-size: 28px;">Регистрация</h1>
            <p style="text-align: center; color: var(--gray-500); margin-bottom: 30px;">
                Создайте аккаунт для быстрой записи и отслеживания заказов
            </p>
            
            <?php if (!empty($errors['db'])): ?>
                <div style="background: #f2dede; color: #a94442; padding: 14px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #d9534f;">
                    <?php echo $errors['db']; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/register.php" novalidate style="background: var(--white); padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid var(--gray-200);">
                
                <!-- Полное имя -->
                <div style="margin-bottom: 20px;">
                    <label for="full_name" style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--secondary);">
                        Полное имя <span style="color: var(--primary);">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        value="<?php echo htmlspecialchars($form_data['full_name']); ?>"
                        placeholder="Иван Петров"
                        maxlength="150"
                        required
                        style="width: 100%; padding: 12px 14px; border: 1px solid <?php echo isset($errors['full_name']) ? '#d9534f' : 'var(--gray-300)'; ?>; border-radius: 8px; font-size: 15px; transition: var(--transition); outline: none;"
                        onfocus="this.style.borderColor='var(--primary)'"
                        onblur="this.style.borderColor='<?php echo isset($errors['full_name']) ? '#d9534f' : 'var(--gray-300)'; ?>'"
                    >
                    <?php if (isset($errors['full_name'])): ?>
                        <span style="color: #d9534f; font-size: 13px; display: block; margin-top: 4px;"><?php echo $errors['full_name']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Email -->
                <div style="margin-bottom: 20px;">
                    <label for="email" style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--secondary);">
                        Email <span style="color: var(--primary);">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($form_data['email']); ?>"
                        placeholder="user@example.com"
                        maxlength="100"
                        required
                        style="width: 100%; padding: 12px 14px; border: 1px solid <?php echo isset($errors['email']) ? '#d9534f' : 'var(--gray-300)'; ?>; border-radius: 8px; font-size: 15px; transition: var(--transition); outline: none;"
                        onfocus="this.style.borderColor='var(--primary)'"
                        onblur="this.style.borderColor='<?php echo isset($errors['email']) ? '#d9534f' : 'var(--gray-300)'; ?>'"
                    >
                    <?php if (isset($errors['email'])): ?>
                        <span style="color: #d9534f; font-size: 13px; display: block; margin-top: 4px;"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Телефон -->
                <div style="margin-bottom: 20px;">
                    <label for="phone" style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--secondary);">
                        Телефон <span style="color: var(--gray-500); font-weight: 400;">(необязательно)</span>
                    </label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                        placeholder="+7 (900) 123-45-67" maxlength="18" data-phone-mask
                        style="width: 100%; padding: 12px 14px; border: 1px solid <?php echo isset($errors['phone']) ? '#d9534f' : 'var(--gray-300)'; ?>; border-radius: 8px; font-size: 15px; transition: var(--transition); outline: none;"
                        onfocus="this.style.borderColor='var(--primary)'"
                        onblur="this.style.borderColor='<?php echo isset($errors['phone']) ? '#d9534f' : 'var(--gray-300)'; ?>'"
                    >
                    <?php if (isset($errors['phone'])): ?>
                        <span style="color: #d9534f; font-size: 13px; display: block; margin-top: 4px;"><?php echo $errors['phone']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Пароль -->
                <div style="margin-bottom: 20px;">
                    <label for="password" style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--secondary);">
                        Пароль <span style="color: var(--primary);">*</span>
                    </label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Пароль с цифрой и заглавной буквой"
                            minlength="4"
                            maxlength="72"
                            required
                            style="width: 100%; padding: 12px 50px 12px 14px; border: 1px solid <?php echo isset($errors['password']) ? '#d9534f' : 'var(--gray-300)'; ?>; border-radius: 8px; font-size: 15px; transition: var(--transition); outline: none;"
                            onfocus="this.style.borderColor='var(--primary)'"
                            onblur="this.style.borderColor='<?php echo isset($errors['password']) ? '#d9534f' : 'var(--gray-300)'; ?>'"
                        >
                        <button type="button" onclick="togglePassword('password', this)" 
                                style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; padding: 4px 8px; color: var(--gray-500);"
                                aria-label="Показать пароль">👁️</button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <span style="color: #d9534f; font-size: 13px; display: block; margin-top: 4px;"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Подтверждение пароля -->
                <div style="margin-bottom: 24px;">
                    <label for="password_confirm" style="display: block; font-weight: 600; margin-bottom: 6px; color: var(--secondary);">
                        Подтверждение пароля <span style="color: var(--primary);">*</span>
                    </label>
                    <div style="position: relative;">
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Повторите пароль"
                            minlength="4"
                            maxlength="72"
                            required
                            style="width: 100%; padding: 12px 50px 12px 14px; border: 1px solid <?php echo isset($errors['password_confirm']) ? '#d9534f' : 'var(--gray-300)'; ?>; border-radius: 8px; font-size: 15px; transition: var(--transition); outline: none;"
                            onfocus="this.style.borderColor='var(--primary)'"
                            onblur="this.style.borderColor='<?php echo isset($errors['password_confirm']) ? '#d9534f' : 'var(--gray-300)'; ?>'"
                        >
                        <button type="button" onclick="togglePassword('password_confirm', this)" 
                                style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; padding: 4px 8px; color: var(--gray-500);"
                                aria-label="Показать пароль">👁️</button>
                    </div>
                    <?php if (isset($errors['password_confirm'])): ?>
                        <span style="color: #d9534f; font-size: 13px; display: block; margin-top: 4px;"><?php echo $errors['password_confirm']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Кнопка отправки -->
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px;">
                    Зарегистрироваться
                </button>
                
                <!-- Ссылка на вход -->
                <p style="text-align: center; margin-top: 20px; color: var(--gray-500); font-size: 14px;">
                    Уже есть аккаунт? <a href="/login.php" style="color: var(--primary); font-weight: 600;">Войти</a>
                </p>
                
            </form>
        </div>
    </div>
</section>

<script>
// Функция показать/скрыть пароль
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
}

// Клиентская валидация (дополняет серверную)
document.querySelector('form').addEventListener('submit', function(e) {
    let hasError = false;
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    
    // Проверка совпадения паролей до отправки
    if (password.value !== passwordConfirm.value) {
        passwordConfirm.style.borderColor = '#d9534f';
        if (!document.getElementById('password_confirm_error')) {
            const error = document.createElement('span');
            error.id = 'password_confirm_error';
            error.style.cssText = 'color: #d9534f; font-size: 13px; display: block; margin-top: 4px;';
            error.textContent = 'Пароли не совпадают';
            passwordConfirm.parentElement.parentElement.appendChild(error);
        }
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>