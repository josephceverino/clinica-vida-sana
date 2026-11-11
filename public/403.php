<?php
// Página "acceso no permitido". Apache la usa cuando bloquea una dirección
// (ver ErrorDocument en public/.htaccess).
require_once __DIR__ . '/../app/errores.php';

mostrar_error(403);
