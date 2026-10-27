FROM php:8.2-apache

# Install ekstensi yang dibutuhkan Laravel & PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# Aktifkan mod_rewrite Apache
RUN a2enmod rewrite

# Set folder kerja
WORKDIR /var/www/html

# Salin seluruh isi proyek
COPY . .

# Install Composer dan dependency PHP
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Ubah root direktori Apache ke folder /public Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Atur izin akses folder storage dan cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]