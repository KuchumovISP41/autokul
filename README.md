# Автокул СТО — сайт автосервиса

Дипломный проект. Веб-приложение для автосервиса «Автокул СТО» (г. Вологда).

## Функционал

### Для клиентов
- Просмотр каталога услуг с фильтрацией по категориям
- Онлайн-запись на обслуживание с динамическим выбором времени
- Личный кабинет с историей записей и гаражом автомобилей
- Отзывы о выполненных работах
- Оплата и скачивание документов

### Для администратора
- Дашборд с аналитикой и статистикой
- Управление записями, услугами, категориями, клиентами и платежами
- Модерация отзывов

## Технологии

- PHP 8.1+
- MySQL 5.7+/8+
- Apache + Docker для Railway
- Cloudinary для хранения новых изображений на хостинге
- HTML5, CSS3, JavaScript, Fetch API

## Автоматическая база данных и тестовые данные

Импортировать `database.sql` вручную больше не нужно. При первом открытии сайта функция `getDBConnection()` запускает автоматическую подготовку базы:

1. Если базы `DB_NAME` ещё нет и у пользователя MySQL есть права, приложение выполнит `CREATE DATABASE IF NOT EXISTS`.
2. Создаст таблицы `users`, `categories`, `services`, `cars`, `appointments`, `appointment_services`, `reviews`, `work_schedule`, `payments`, `notifications`.
3. Добавит стартовые категории, услуги, график работы, демо-записи и тестовых пользователей.

Автоматический запуск управляется переменной:

```text
DB_AUTO_MIGRATE=true
```

Для ручной проверки можно выполнить:

```bash
php scripts/bootstrap_database.php
```

## Тестовые доступы

| Роль | Email | Пароль |
|------|-------|--------|
| Админ | admin@autokul.ru | password123 |
| Клиент | ivan@example.com | password123 |
| Механик | mechanic@autokul.ru | password123 |

## Локальный запуск

1. Установите PHP 8.1+, Composer и MySQL.
2. Скопируйте файл переменных:

```bash
cp .env.example .env
```

3. В `.env` заполните локальную БД:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=autokul_sto
DB_USER=root
DB_PASS=
DB_AUTO_MIGRATE=true
```

4. Установите зависимости:

```bash
composer install
```

5. Откройте сайт через локальный Apache/PHP-сервер. При первом запросе таблицы и демо-данные создадутся сами.

## Деплой на Railway + Cloudinary по шагам

### Шаг 1. Подготовьте секреты Cloudinary

В Cloudinary Dashboard возьмите:

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`

Не вставляйте реальные секреты в код и не коммитьте `.env`. Если секрет уже отправлялся в чат или публичное место, перевыпустите его в Cloudinary.

### Шаг 2. Создайте проект Railway

1. Загрузите этот репозиторий в GitHub.
2. В Railway создайте новый проект из GitHub-репозитория.
3. Добавьте MySQL-сервис в этот же Railway-проект.
4. В сервисе сайта откройте **Variables**.

### Шаг 3. Переменные Railway

Если Railway добавил переменную подключения `MYSQL_URL` или `DATABASE_URL`, можно оставить её — сайт умеет читать эти URL автоматически. Дополнительно добавьте переменные приложения:

```text
APP_ENV=production
APP_DEBUG=false
DB_AUTO_MIGRATE=true
CLOUDINARY_CLOUD_NAME=<ваш cloud name>
CLOUDINARY_API_KEY=<ваш api key>
CLOUDINARY_API_SECRET=<ваш api secret>
CLOUDINARY_FOLDER=autokul_sto
UPLOAD_MAX_SIZE=5242880
```

Если `MYSQL_URL` / `DATABASE_URL` нет, добавьте ручные MySQL-переменные из Railway MySQL-сервиса:

```text
DB_HOST=<MYSQLHOST из Railway>
DB_PORT=<MYSQLPORT из Railway, обычно 3306>
DB_NAME=<MYSQLDATABASE из Railway>
DB_USER=<MYSQLUSER из Railway>
DB_PASS=<MYSQLPASSWORD из Railway>
DB_CHARSET=utf8mb4
```

### Шаг 4. Деплой

Railway использует файлы `railway.json` и `Dockerfile`. Контейнер сам установит Composer-зависимости, PHP-расширения `gd`, `pdo_mysql`, `mysqli`, включит Apache `mod_rewrite` и запустится на порту Railway.

После пуша в GitHub Railway соберёт проект автоматически:

```bash
git push
```

### Шаг 5. Проверка после деплоя

1. Откройте URL сайта Railway.
2. Войдите как `admin@autokul.ru` / `password123`.
3. Проверьте `/services.php`, `/appointment.php`, `/admin/services.php`.
4. Загрузите изображение услуги или аватар — при настроенном Cloudinary в БД сохранится `public_id`, а файл будет храниться в Cloudinary.

## Как работает хранение изображений

- В существующих колонках `services.image` и `users.avatar` хранится либо старый локальный путь `uploads/...`, либо Cloudinary `public_id`.
- Старые локальные картинки продолжают отображаться, если файл есть в проекте.
- Новые загрузки при настроенном Cloudinary уходят в папки `autokul_sto/services` и `autokul_sto/avatars`.
- Если Cloudinary-переменные не заданы, сайт использует локальное сохранение в `uploads/` — это удобно для разработки, но на Railway новые файлы не переживут пересборку контейнера.

## Автор

Кучумов А. Н. — дипломный проект, 2024 г.
