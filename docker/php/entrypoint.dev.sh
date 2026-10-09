#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  if [ "$(id -u)" = "0" ]; then
    su -s /bin/sh app -c "composer install --no-interaction --prefer-dist"
  else
    composer install --no-interaction --prefer-dist
  fi
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R app:app storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache || true

exec "$@"
