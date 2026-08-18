# Fluro Tech API — container image for Railway (or any Docker host).
#
# php:8.3-apache rather than a php-fpm + nginx pair: one process to supervise, and
# Laravel's own public/.htaccess already handles the front-controller rewrite, so
# Apache + mod_rewrite needs no routing config of its own.
#
# Railway auto-detects this Dockerfile and uses it instead of Nixpacks, which makes the
# PHP version and extension set explicit rather than inferred.
FROM php:8.3-apache

# pdo_mysql for the database; gd for image handling; zip + intl are common Laravel deps.
# libpng/libjpeg/freetype are gd's build inputs, libzip for zip, libicu for intl.
RUN apt-get update && apt-get install -y --no-install-recommends \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so a source-only change reuses this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

# Apache must serve Laravel's public/, never the project root — otherwise .env and
# the whole source tree are downloadable.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/conf-available/docker-php.conf \
 && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

# 20M to match client_max_body_size in the nginx config used elsewhere: product
# image uploads exceed PHP's 2M default and would fail with an empty $_FILES.
RUN printf 'upload_max_filesize=20M\npost_max_size=20M\nmemory_limit=256M\n' \
      > /usr/local/etc/php/conf.d/zz-app.ini

RUN chown -R www-data:www-data storage bootstrap/cache

COPY deploy/railway-start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

EXPOSE 8080
CMD ["start"]
