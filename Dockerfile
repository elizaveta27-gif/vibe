FROM php:8.2-fpm-alpine

# Системные зависимости
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    libpq-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    $PHPIZE_DEPS

# PHP расширения
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    mbstring

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Конфиг PHP для dev
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini

RUN echo "upload_max_filesize=10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=12M" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/backend

# Права на запись в var/ (кэш и логи Symfony)
RUN mkdir -p var && chmod -R 777 var
