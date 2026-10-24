#!/bin/sh
set -e

# Render indique le port à utiliser dans la variable PORT
PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Crée ou met à jour les tables (ne supprime jamais de données)
php artisan migrate --force

# Premier démarrage seulement : crée le compte du directeur et les matières
php artisan db:seed --force

exec apache2-foreground