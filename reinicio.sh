#!/bin/bash

# Obtener la ruta del directorio actual
cd "$(dirname "$0")"

# Cargar variables desde el archivo .env
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Configurar valores por defecto si no existen
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-127.0.0.1}

if [ "$DB_CONNECTION" == "pgsql" ]; then
    DB_ENGINE="PostgreSQL"
    DEFAULT_PORT=5432
elif [ "$DB_CONNECTION" == "mysql" ]; then
    DB_ENGINE="MySQL"
    DEFAULT_PORT=3306
else
    DB_ENGINE="Base de datos"
    DEFAULT_PORT=5432
fi

DB_PORT=${DB_PORT:-$DEFAULT_PORT}

echo "Reiniciando Sistema Agarcorp..."
echo "Verificando conexion con $DB_ENGINE en $DB_HOST:$DB_PORT..."

# Verificar conexión usando nc (netcat)
nc -z -w3 "$DB_HOST" "$DB_PORT" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: No hay conexion con $DB_ENGINE en $DB_HOST:$DB_PORT."
    exit 1
fi

echo "Ejecutando migraciones y seeders..."
php artisan migrate:fresh --seed --force || { echo "Error en artisan"; exit 1; }

echo "Generando Permisos de Shield..."
php artisan shield:generate --all || { echo "Error en artisan"; exit 1; }

echo "Sincronizando permisos en roles de gestion..."
php artisan db:seed --force --no-interaction || { echo "Error en artisan"; exit 1; }

echo "Limpiando Cache..."
php artisan permission:cache-reset || { echo "Error en artisan"; exit 1; }

echo "Optimizando aplicacion..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "PROCESO TERMINADO. Ya puedes entrar al sistema."
