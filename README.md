# Автокул СТО — Сайт автосервиса

Дипломный проект. Веб-приложение для автосервиса «Автокул СТО» (г. Вологда).

## Функционал

### Для клиентов
- Просмотр каталога услуг с фильтрацией по категориям
- Онлайн-запись на обслуживание с динамическим выбором времени
- Личный кабинет с историей записей и гаражом автомобилей
- Отзывы о выполненных работах

### Для администратора
- Дашборд с аналитикой и статистикой
- Управление записями (подтверждение, статусы)
- Управление услугами и категориями
- Модерация отзывов
- Просмотр базы клиентов

## Технологии

- PHP 8.0+
- MySQL 5.7+
- HTML5, CSS3, JavaScript (ES6+)
- AJAX (Fetch API)

## Установка

1. Клонировать репозиторий в папку веб-сервера
2. Импортировать `database.sql` в MySQL
3. Скопировать `includes/config.example.php` → `includes/config.php`
4. Настроить подключение к БД в `config.php`
5. Открыть сайт в браузере

## Тестовые доступы

| Роль | Email | Пароль |
|------|-------|--------|
| Админ | admin@autokul.ru | password123 |
| Клиент | ivan@example.com | password123 |
| Механик | mechanic@autokul.ru | password123 |

## Автор

Кучумов А. Н. — дипломный проект, 2024 г.

## Деплой на Railway + Cloudinary

Проект подготовлен к запуску в Docker-контейнере Railway. Изображения услуг и аватары сохраняются в Cloudinary, если заданы переменные `CLOUDINARY_*`; без них приложение продолжит использовать локальную папку `uploads/` для разработки.

### 1. Локальные переменные

Скопируйте пример окружения и заполните значения только у себя на компьютере:

```bash
cp .env.example .env
```

В `.env` укажите подключение к MySQL и Cloudinary. Реальные `API Secret`, пароли БД и другие секреты нельзя коммитить в Git.

### 2. Переменные Railway

В Railway откройте сервис приложения → **Variables** и добавьте:

```text
APP_ENV=production
APP_DEBUG=false
DB_HOST=<host from Railway MySQL>
DB_PORT=3306
DB_NAME=<database name>
DB_USER=<database user>
DB_PASS=<database password>
CLOUDINARY_CLOUD_NAME=<cloud name from Cloudinary>
CLOUDINARY_API_KEY=<api key from Cloudinary>
CLOUDINARY_API_SECRET=<api secret from Cloudinary>
CLOUDINARY_FOLDER=autokul_sto
UPLOAD_MAX_SIZE=5242880
```

Если Railway выдаёт `DATABASE_URL` или `MYSQL_URL`, приложение также умеет читать эти переменные автоматически.

### 3. Как работает хранение изображений

- В существующих колонках `services.image` и `users.avatar` хранится либо старый локальный путь (`uploads/...`), либо новый `public_id` из Cloudinary.
- Старые локальные изображения продолжают отображаться, пока файл есть в репозитории/контейнере.
- Новые загрузки при настроенном Cloudinary будут уходить в папки `autokul_sto/services` и `autokul_sto/avatars`.

### 4. Деплой

Railway использует `railway.json` и `Dockerfile`. При деплое контейнер устанавливает Composer-зависимости, расширения PHP (`pdo_mysql`, `mysqli`, `gd`) и запускает Apache на порту из переменной `PORT`.

```bash
git push
```

После подключения GitHub-репозитория Railway соберёт и запустит проект автоматически.
