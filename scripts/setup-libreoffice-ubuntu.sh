#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Ejecuta este script con sudo para instalar paquetes del sistema."
  echo "Ejemplo: sudo bash scripts/setup-libreoffice-ubuntu.sh"
  exit 1
fi

echo "[1/4] Actualizando repositorios..."
apt-get update

echo "[2/4] Instalando LibreOffice y fuentes recomendadas..."
apt-get install -y \
  libreoffice \
  libreoffice-calc \
  fontconfig \
  fonts-dejavu \
  fonts-liberation \
  fonts-noto-core \
  fonts-noto-cjk \
  fonts-noto-color-emoji

echo "[3/4] Refrescando cache de fuentes..."
fc-cache -f -v >/dev/null

echo "[4/4] Verificando binario LibreOffice..."
if command -v soffice >/dev/null 2>&1; then
  soffice --headless --version
else
  echo "No se encontro 'soffice' en PATH luego de la instalacion." >&2
  exit 1
fi

echo "Listo. Reinicia PHP-FPM/Apache/Nginx si aplica para tomar nuevas variables de entorno."
