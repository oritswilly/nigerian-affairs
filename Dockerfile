FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite
COPY . /var/www/html/
RUN mkdir -p /var/www/html/storage && chown -R www-data:www-data /var/www/html/storage
EXPOSE 80