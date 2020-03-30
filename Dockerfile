FROM php:apache

RUN apt-get update && apt-get install -y \
        libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

RUN apt-get install -y git

WORKDIR /var/www/html/
COPY . .
#RUN rm demos/coverage.php
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-dev


