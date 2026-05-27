#!/bin/bash

# 1. Definir el nombre del contenedor de PHP
CONTAINER_NAME="advpruebas_php"

# Obtener la ruta del directorio actual
cd "$(dirname "$0")"

# 2. Cargar variables desde el archivo .env para saber el Host y Puerto
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Configurar valores por defecto si no existen
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-db}  # En Docker el host suele ser 'db'
DB_PORT=${DB_PORT:-5432}

echo "=========================================================="
echo "   REINICIANDO SISTEMA AGARCORP (MODO DOCKER)            "
echo "=========================================================="

echo "Verificando conexi�n con la base de datos dentro de Docker..."

# 3. Verificamos la conexi�n DESDE el contenedor de PHP hacia el de DB
docker exec -i $CONTAINER_NAME php -r '$h=$argv[1]??"db"; $p=(int)($argv[2]??5432); $f=@fsockopen($h,$p,$errno,$errstr,3); if($f){fclose($f); exit(0);} fwrite(STDERR,"No conecta a $h:$p ($errno $errstr)\\n"); exit(1);' "$DB_HOST" "$DB_PORT" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: El contenedor de PHP no llega a la DB en $DB_HOST:$DB_PORT."
    echo "Aseg�rate de que los contenedores est�n encendidos con 'docker compose up -d'"
    exit 1
fi

echo ">> Ejecutando migraciones y seeders (FRESH)..."
docker exec -i $CONTAINER_NAME php artisan migrate:fresh --seed --force || { echo "Error en artisan"; exit 1; }

echo ">> Generando Permisos de Shield..."
docker exec -i $CONTAINER_NAME php artisan shield:generate --all --panel=agarcorp --no-interaction || { echo "Error en artisan"; exit 1; }

echo ">> Sincronizando permisos en roles de gestion..."
docker exec -i $CONTAINER_NAME php artisan db:seed --force --no-interaction || { echo "Error en artisan"; exit 1; }

echo ">> Limpiando Cache..."
docker exec -i $CONTAINER_NAME php artisan permission:cache-reset || { echo "Error en artisan"; exit 1; }

echo ">> Optimizando aplicacion..."
docker exec -i $CONTAINER_NAME php artisan config:cache
docker exec -i $CONTAINER_NAME php artisan route:cache
docker exec -i $CONTAINER_NAME php artisan view:cache
docker exec -i $CONTAINER_NAME php artisan event:cache

echo ">> Corrigiendo permisos de carpetas (Fix Error 500)..."
docker exec -i $CONTAINER_NAME chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -i $CONTAINER_NAME chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -i $CONTAINER_NAME chown -R www-data:www-data "/var/www/html/Facturas" "/var/www/html/Notas de Entrega" "/var/www/html/Comprobantes-ODC"
docker exec -i $CONTAINER_NAME chmod -R 775 "/var/www/html/Facturas" "/var/www/html/Notas de Entrega" "/var/www/html/Comprobantes-ODC"

echo "=========================================================="
echo "   PROCESO TERMINADO. Ya puedes entrar al sistema.       "
echo "=========================================================="