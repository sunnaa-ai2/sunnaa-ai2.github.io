# Laravel API on FrankenPHP (PHP 8.3). Works on Render, Koyeb, Railway, Fly.io or any Docker host.
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_mysql intl zip opcache exif gd
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

ENV PORT=8080 APP_ENV=production LOG_CHANNEL=stderr
EXPOSE 8080

# Migrate, cache config, then serve /public.
CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && frankenphp php-server --root public/ --listen :${PORT}"]
