FROM php:8.2
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN docker-php-ext-install pdo_mysql mysqli
