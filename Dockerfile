# Live hosting image (used by Render.com). PHP 8.3 + Apache.
FROM php:8.3-apache

# MySQL driver (SQLite driver is already included in the image)
RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html/
RUN rm -rf /var/www/html/_tools /var/www/html/data \
    && mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

# Default: built-in SQLite database (no setup). Set DB_DRIVER env vars on the host to use MySQL.
ENV PTC_DB_DRIVER=sqlite

# Render provides the port in $PORT
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf
EXPOSE 10000
