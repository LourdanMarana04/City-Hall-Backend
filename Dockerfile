FROM php:8.2-fpm

# Install GD extension
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Continue with your existing setup...
COPY . /var/www/html
WORKDIR /var/www/html

RUN composer install --optimize-autoloader --no-scripts --no-interaction
