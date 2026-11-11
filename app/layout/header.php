<?php
// Cabecera común de las páginas públicas.
// Antes de incluirla, cada página puede definir $titulo_pagina.
require_once __DIR__ . '/../helpers.php';

$titulo_pagina = $titulo_pagina ?? 'Clínica Vida Sana';
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
        <a href="<?= e(url('/login.php')) ?>">Acceso personal</a>
        <a class="boton boton-chico" href="<?= e(url('/solicitar-cita.php')) ?>">Solicitar cita</a>
    </nav>
</header>
<main class="contenido">
