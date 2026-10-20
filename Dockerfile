FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libonig-dev libcurl4-openssl-dev \
 && docker-php-ext-install curl gd mbstring \
 && rm -rf /var/lib/apt/lists/*

RUN sed -ri 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf \
 && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-hide-version.conf \
 && echo 'expose_php = Off' > /usr/local/etc/php/conf.d/hide-version.ini

ENV PORT=10000

COPY . /var/www/html/

RUN rm -rf /var/www/html/.claude \
 && mkdir -p /var/www/html/api/privado/tmp \
 && chown -R www-data:www-data /var/www/html/api/privado/tmp

EXPOSE 10000
