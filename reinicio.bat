@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"

set "DB_CONNECTION="
set "DB_HOST="
set "DB_PORT="

for /f "usebackq tokens=1,* delims==" %%A in (".env") do (
	if /I "%%A"=="DB_CONNECTION" set "DB_CONNECTION=%%B"
	if /I "%%A"=="DB_HOST" set "DB_HOST=%%B"
	if /I "%%A"=="DB_PORT" set "DB_PORT=%%B"
)

if not defined DB_CONNECTION set "DB_CONNECTION=pgsql"
if not defined DB_HOST set "DB_HOST=127.0.0.1"

set "DB_ENGINE=Base de datos"
set "DEFAULT_PORT=5432"

if /I "%DB_CONNECTION%"=="pgsql" (
	set "DB_ENGINE=PostgreSQL"
	set "DEFAULT_PORT=5432"
) else if /I "%DB_CONNECTION%"=="mysql" (
	set "DB_ENGINE=MySQL"
	set "DEFAULT_PORT=3306"
)

if not defined DB_PORT set "DB_PORT=%DEFAULT_PORT%"

echo Reiniciando Sistema Agarcorp...
echo Verificando conexion con !DB_ENGINE! en !DB_HOST!:!DB_PORT!...
powershell -NoProfile -Command "if (Test-NetConnection -ComputerName '%DB_HOST%' -Port %DB_PORT% -InformationLevel Quiet) { exit 0 } else { exit 1 }"
if errorlevel 1 goto db_error

echo Ejecutando migraciones y seeders...
call php artisan migrate:fresh --seed --force --no-interaction
if errorlevel 1 goto artisan_error

echo Shield se genera desde DatabaseSeeder con panel agarcorp y sin interaccion.

echo Limpiando Cache...
call php artisan permission:cache-reset
if errorlevel 1 goto artisan_error

echo Optimizando aplicacion para cargas rapidas...
call php artisan config:cache
if errorlevel 1 goto artisan_error
call php artisan route:cache
if errorlevel 1 goto artisan_error
call php artisan view:cache
if errorlevel 1 goto artisan_error
call php artisan event:cache
if errorlevel 1 goto artisan_error
rem call composer dump-autoload --optimize --classmap-authoritative --no-interaction --no-scripts
rem if errorlevel 1 goto artisan_error

echo PROCESO TERMINADO. Ya puedes entrar al sistema.
pause
exit /b 0

:db_error
echo ERROR: No hay conexion con !DB_ENGINE! en !DB_HOST!:!DB_PORT!.
echo Verifica que el servicio de base de datos este iniciado en Laragon y vuelve a ejecutar este archivo.
pause
exit /b 1

:artisan_error
echo ERROR: Uno de los comandos de Artisan fallo. Revisa el mensaje anterior.
pause
exit /b 1