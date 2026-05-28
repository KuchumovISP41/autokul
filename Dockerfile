FROM php:8.2-fpm

# Установка Nginx и необходимых расширений
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копирование composer файлов
COPY composer.json ./
RUN if [ -f "composer.json" ]; then composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader; fi

# Копирование всего проекта
COPY . .

# Создаем конфигурацию Nginx с правильными путями
RUN echo 'server { \
    listen 80 default_server; \
    listen [::]:80 default_server; \
    root /var/www/html; \
    index index.php index.html; \
    server_name _; \
    \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    \
    location ~ \.php$ { \
        include fastcgi_params; \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        fastcgi_param PATH_INFO $fastcgi_path_info; \
    } \
    \
    location ~ /\.ht { \
        deny all; \
    } \
}' > /etc/nginx/sites-enabled/default

# Удаляем дефолтный конфиг
RUN rm -f /etc/nginx/sites-enabled/default.conf 2>/dev/null || true

# Создаем правильный скрипт запуска
RUN echo '#!/bin/sh\n\
set -e\n\
\n\
# Создаем необходимые директории\n\
mkdir -p /var/run/php\n\
chown -R www-data:www-data /var/run/php\n\
\n\
# Проверяем конфигурации\n\
nginx -t\n\
php-fpm -t\n\
\n\
# Запускаем PHP-FPM в фоне\n\
php-fpm -D\n\
\n\
# Ждем запуска PHP-FPM\n\
sleep 2\n\
\n\
# Запускаем Nginx в foreground\n\
nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

# Настройка прав
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/start.sh"]
