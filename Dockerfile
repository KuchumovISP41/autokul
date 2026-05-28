FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY . .
COPY docker/railway-entrypoint.sh /usr/local/bin/railway-entrypoint

# === ИСПРАВЛЕНИЕ ОШИБКИ ===
# Отключаем mpm_event и включаем mpm_prefork
RUN a2dismod mpm_event || true && \
    a2enmod mpm_prefork && \
    a2enmod rewrite headers && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /usr/local/bin/railway-entrypoint

# Проверка конфигурации Apache
RUN apachectl -t

EXPOSE 80

ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]
