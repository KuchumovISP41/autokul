FROM php:8.2-fpm

# Установка Nginx и расширений
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

# Копирование файлов
COPY . .

# Установка зависимостей Composer (если есть)
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader || true; \
    fi

# Создаем конфигурацию Nginx
RUN echo 'server { \
    listen 80; \
    server_name _; \
    root /var/www/html; \
    index index.php index.html; \
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
    } \
}' > /etc/nginx/sites-enabled/default

# Удаляем дефолтный сайт Nginx
RUN rm -f /etc/nginx/sites-enabled/default.conf 2>/dev/null || true

# Настройка прав
RUN chown -R www-data:www-data /var/www/html

# Создаем директорию для логов
RUN mkdir -p /var/log/nginx && chown -R www-data:www-data /var/log/nginx

EXPOSE 80

# Запускаем PHP-FPM в фоне и Nginx в foreground
CMD php-fpm -D && nginx -g "daemon off;"
