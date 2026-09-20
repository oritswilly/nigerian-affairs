FROM php:8.3-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY . /app
RUN mkdir -p /app/storage && chown -R www-data:www-data /app/storage
EXPOSE 8080
CMD ["sh","-c","php -S 0.0.0.0:${PORT:-8080} -t /app"]
