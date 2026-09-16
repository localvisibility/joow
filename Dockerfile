# ─────────────────────────────────────────────────────────────
# Joow SaaS — image applicative (PHP 8.4 FPM + extensions)
# Sert l'app web (php-fpm), les workers Horizon et le scheduler.
# Le code est monté en volume au runtime ; l'entrypoint installe les
# dépendances (composer) et cale les permissions.
# ─────────────────────────────────────────────────────────────
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql bcmath gd zip intl exif pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
