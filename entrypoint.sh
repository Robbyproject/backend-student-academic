#!/bin/sh
# Jalankan migrasi database otomatis saat container menyala
php artisan migrate --force

# Jalankan web server Apache
exec "$@"