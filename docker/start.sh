#!/bin/sh

echo "▶ Génération des clés JWT..."
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction

echo "▶ Lancement des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "▶ Nettoyage du cache..."
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "▶ Démarrage PHP-FPM..."
php-fpm -D

echo "▶ Démarrage Nginx..."
nginx -g "daemon off;"