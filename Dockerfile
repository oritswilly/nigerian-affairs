FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2dismod mpm_event mpm_worker || true \
 && a2enmod mpm_prefork rewrite
COPY . /var/www/html/
RUN mkdir -p /var/www/html/storage && chown -R www-data:www-data /var/www/html/storage
EXPOSE 80
