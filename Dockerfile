FROM php:8.3-cli
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install pdo_mysql curl && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . /app
RUN php -l /app/index.php && php -l /app/config.php && php -l /app/install.php && php -l /app/tests/synthetic-audit.php
RUN mkdir -p /app/storage/uploads /app/storage/manuscripts && chown -R www-data:www-data /app/storage && chmod +x /app/entrypoint.sh
EXPOSE 8080
ENTRYPOINT ["/app/entrypoint.sh"]
CMD ["sh","-c","php -S 0.0.0.0:${PORT:-8080} -t /app"]
