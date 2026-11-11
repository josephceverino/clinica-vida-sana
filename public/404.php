<?php
// Página "no encontrada". Apache la usa cuando una dirección no existe
// (ver ErrorDocument en public/.htaccess).
require_once __DIR__ . '/../app/errores.php';

mostrar_error(404);
