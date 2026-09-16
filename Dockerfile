# ─────────────────────────────────────────────────────────────
# Joow SaaS — image applicative (PHP 8.4 FPM + extensions)
# Sert à la fois l'app web (php-fpm), les workers Horizon et le scheduler.
# ─────────────────────────────────────────────────────────────
FROM php:8.4-fpm AS base

# Dépendances système + extensions PHP nécessaires (Postgres, Redis, images, zip, intl, bcmath…)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql bcmath gd zip intl exif pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (depuis l'image officielle)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Installer les dépendances PHP en cache (couche séparée)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copier le code puis finaliser l'autoload
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
