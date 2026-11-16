FROM php:8.3-apache-bookworm AS php-build

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ${PHPIZE_DEPS} \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts

COPY . .

RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

FROM php:8.3-apache-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libicu72 \
        libonig5 \
        libzip4 \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

COPY --from=php-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=php-build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD curl --fail --silent http://127.0.0.1/up || exit 1

CMD ["apache2-foreground"]
