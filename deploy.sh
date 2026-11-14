#!/usr/bin/env bash
# =====================================================================
# deploy.sh - Actualiza la web de la Clínica Vida Sana en el servidor.
#
# Uso (en el servidor Fedora, desde /var/www/clinica):
#     sudo ./deploy.sh
#
# Qué hace:
#   1. Descarga los cambios de GitHub (git pull).
#   2. Comprueba que existe config/config.php (no viene de GitHub).
#   3. Pone dueño y permisos correctos (sin 777).
#   4. Restaura las etiquetas de SELinux (restorecon).
#
# Se puede ejecutar muchas veces sin romper nada.
# No toca la configuración de Apache ni la base de datos.
# =====================================================================

# Detener el script si un comando falla o si se usa una variable sin definir.
set -euo pipefail

# Carpeta donde está la web en el servidor.
APP_DIR="/var/www/clinica"

# Usuario y grupo de Apache en Fedora.
GRUPO_WEB="apache"

# Pequeña función para mostrar mensajes con formato.
paso() {
    echo
    echo "==> $1"
}

# --- 0. Comprobaciones previas -------------------------------------------

# Hace falta ser root (sudo) para cambiar dueños y etiquetas de SELinux.
if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: ejecuta este script con sudo:  sudo ./deploy.sh"
    exit 1
fi

if [ ! -d "$APP_DIR/.git" ]; then
    echo "ERROR: $APP_DIR no es un repositorio git. Revisa la guía docs/despliegue.md."
    exit 1
fi

cd "$APP_DIR"

# --- 1. Descargar cambios ------------------------------------------------

paso "Descargando cambios de GitHub (rama main)"
# --ff-only: si hay cambios hechos a mano en el servidor, se detiene en vez
# de mezclarlos. Así nunca se crea un "merge" raro en producción.
git pull --ff-only origin main

# --- 2. Comprobar la configuración --------------------------------------

paso "Comprobando config/config.php"
if [ ! -f config/config.php ]; then
    echo "ERROR: falta config/config.php."
    echo "Cópialo desde config/config.example.php y pon los datos de producción."
    exit 1
fi

# Aviso si se dejó el modo de depuración encendido.
if grep -Eq "'debug'[[:space:]]*=>[[:space:]]*true" config/config.php; then
    echo "AVISO: 'debug' está en true. En producción debe estar en false."
fi

# --- 3. Dueño y permisos -------------------------------------------------

paso "Aplicando dueño y permisos"
# Dueño root y grupo apache: Apache puede LEER, pero no MODIFICAR el código.
# Se excluye la carpeta .git (Apache no necesita verla).
find . -path ./.git -prune -o -exec chown root:"$GRUPO_WEB" {} +

# Carpetas: 750 (root todo, apache entrar y leer, otros nada).
find . -path ./.git -prune -o -type d -exec chmod 750 {} +

# Archivos: 640 (root leer y escribir, apache solo leer, otros nada).
find . -path ./.git -prune -o -type f -exec chmod 640 {} +

# El propio script tiene que poder ejecutarse.
chmod 750 deploy.sh

# --- 4. SELinux ----------------------------------------------------------

paso "Restaurando etiquetas de SELinux"
# Pone a cada archivo la etiqueta que SELinux espera en /var/www
# (httpd_sys_content_t), para que Apache pueda leerlos.
restorecon -R "$APP_DIR"

# --- Fin -----------------------------------------------------------------

paso "Listo. Versión publicada:"
git log -1 --format="%h  %s  (%cr)"
