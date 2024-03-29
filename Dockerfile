FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev
RUN docker-php-ext-install zip


RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /app

COPY . .

# Устанавливаем зависимости проекта через Composer
RUN composer install

# Запускаем PHP CLI сервер на порту 10000
CMD ["php", "-S", "0.0.0.0:10000", "-t", "public", "-d", "PHP_CLI_SERVER_WORKERS=5"]
