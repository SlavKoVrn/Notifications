FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    librabbitmq-dev

# Установка расширений PHP
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install amqp && docker-php-ext-enable amqp

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
