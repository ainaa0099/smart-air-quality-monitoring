FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    pkg-config \
    zlib1g-dev \
 && docker-php-ext-install sockets zip pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./

RUN composer install --no-interaction --prefer-dist

COPY . .

EXPOSE 8002

CMD ["php", "-S", "0.0.0.0:8002", "-t", "public"]
