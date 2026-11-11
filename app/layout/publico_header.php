<?php
// Cabecera común de las páginas públicas.
// Antes de incluirla, cada página puede definir $titulo_pagina.
require_once __DIR__ . '/../core/auth.php';
enviar_cabeceras_seguridad();

$titulo_pagina = $titulo_pagina ?? 'Clínica Vida Sana';

// ¿Hay alguien con sesión abierta? Solo miramos la sesión si el navegador
// trae su cookie; así los visitantes anónimos no reciben una sesión de balde.
$sesion_publica = isset($_COOKIE[session_name()]) ? usuario_actual() : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?> | Clínica Vida Sana</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/base.css')) ?>">
</head>
<body>
<header class="cabecera">
    <a class="marca" href="<?= e(url('/')) ?>">Clínica Vida Sana</a>
    <nav class="menu">
        <a href="<?= e(url('/')) ?>">Inicio</a>
        <a href="<?= e(url('/servicios.php')) ?>">Servicios</a>
        <a href="<?= e(url('/nosotros.php')) ?>">Nosotros</a>
        <a href="<?= e(url('/contacto.php')) ?>">Contacto</a>
        <?php if ($sesion_publica !== null): ?>
            <a class="boton boton-chico"
               href="<?= e(url(ruta_inicio_por_rol($sesion_publica['rol']))) ?>">Mi cuenta</a>
        <?php else: ?>
            <a class="boton boton-chico" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
        <?php endif; ?>
    </nav>
</header>
<main class="contenido">
