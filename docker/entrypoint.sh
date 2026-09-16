#!/bin/sh
# Entrypoint commun (app / horizon / scheduler).
# Installe les dépendances une seule fois (verrou flock pour éviter les
# installs concurrents entre conteneurs partageant le même volume de code),
# puis cale les permissions avant de lancer la commande.
set -e
cd /var/www/html

(
  flock 9
  if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] composer install…"
    composer install --no-interaction --prefer-dist --no-progress
  fi
) 9>/tmp/joow-composer.lock

# Attendre que le vendor soit prêt (cas horizon/scheduler démarrés en parallèle)
i=0
while [ ! -f vendor/autoload.php ] && [ $i -lt 60 ]; do sleep 2; i=$((i+1)); done

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
