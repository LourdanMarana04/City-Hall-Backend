#!/bin/bash
apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev
docker-php-ext-configure gd --with-freetype --with-jpeg
docker-php-ext-install gd
composer install --optimize-autoloader --no-dev
