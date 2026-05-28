FROM php:8.2-fpm

# Установка Nginx, Git, Unzip и расширений PHP
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

# Установка Composer (официальный способ)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем файлы проекта
COPY . .

# Проверяем наличие composer.json и устанавливаем зависимости
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader; \
    fi

# Создаём папки для загрузок
RUN mkdir -p uploads/avatars uploads/services && chmod -R 755 uploads

# Настройка Nginx для порта 8080
RUN echo 'server { \
    listen 8080; \
    server_name _; \
    root /var/www/html; \
    index index.php; \
    \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
        fastcgi_read_timeout 300; \
    } \
    \
    location ~ /\.ht { \
        deny all; \
    } \
    \
    location ~ /\.env { \
        deny all; \
    } \
}' > /etc/nginx/sites-enabled/default

# Удаляем дефолтный конфиг Nginx
RUN rm -f /etc/nginx/sites-enabled/default.conf 2>/dev/null || true

# Права на файлы
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

# Запуск PHP-FPM и Nginx
CMD php-fpm -D && nginx -g "daemon off;"