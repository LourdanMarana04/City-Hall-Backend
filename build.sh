#!/bin/bash
install-php-extensions gd
composer install --optimize-autoloader --no-dev --ignore-platform-req=ext-gd
