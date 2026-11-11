<?php
// Cierra la sesión. Solo acepta POST con token CSRF, para que otra web
// no pueda sacar al usuario de su sesión con un simple enlace.
require_once __DIR__ . '/../app/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verificar()) {
    cerrar_sesion();
}

redirigir('/login.php');
