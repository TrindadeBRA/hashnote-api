#!/bin/sh
set -e

# Executa setup do banco de dados se não existir
if [ ! -f /var/www/html/data/app.sqlite ]; then
    echo "Configurando banco de dados..."
    php /var/www/html/scripts/setup.php
fi

# Garante permissões corretas
chmod -R 777 /var/www/html/data 2>/dev/null || true

echo "Iniciando servidor PHP..."
exec "$@"

