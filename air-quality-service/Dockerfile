FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
EXPOSE 8001
CMD ["php", "-S", "0.0.0.0:8001", "-t", "public", "public/index.php"]
