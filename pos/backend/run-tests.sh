#!/bin/sh
php artisan migrate --force && php vendor/bin/phpunit --configuration /var/www/phpunit.xml
