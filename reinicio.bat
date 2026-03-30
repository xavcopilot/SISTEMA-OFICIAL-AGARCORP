@echo off
setlocal

echo Reiniciando Sistema Agarcorp...
echo Verificando conexion con MySQL en 127.0.0.1:3306...
powershell -NoProfile -Command "if (Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -InformationLevel Quiet) { exit 0 } else { exit 1 }"
if errorlevel 1 goto db_error

echo Ejecutando migraciones y seeders...
call php artisan migrate:fresh --seed --force
if errorlevel 1 goto artisan_error

echo Generando Permisos de Shield...
call php artisan shield:generate --all
if errorlevel 1 goto artisan_error

echo Sincronizando permisos en roles de gestion...
call php artisan db:seed --force --no-interaction
if errorlevel 1 goto artisan_error

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
call composer dump-autoload --optimize --classmap-authoritative --no-interaction --no-scripts
if errorlevel 1 goto artisan_error

echo PROCESO TERMINADO. Ya puedes entrar al sistema.
pause
exit /b 0

:db_error
echo ERROR: No hay conexion con MySQL en 127.0.0.1:3306.
echo Inicia MySQL desde Laragon y vuelve a ejecutar este archivo.
pause
exit /b 1

:artisan_error
echo ERROR: Uno de los comandos de Artisan fallo. Revisa el mensaje anterior.
pause
exit /b 1