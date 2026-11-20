<?php
// Cabecera común de las páginas públicas.
// Antes de incluirla, cada página puede definir $titulo_pagina.
require_once __DIR__ . '/../core/auth.php';
enviar_cabeceras_seguridad();

$titulo_pagina = $titulo_pagina ?? 'Clínica Vida Sana';

// ¿Hay alguien con sesión abierta? Solo miramos la sesión si el navegador
// trae su cookie; así los visitantes anónimos no reciben una sesión de balde.
$sesion_publica = isset($_COOKIE[session_name()]) ? usuario_actual() : null;

// Enlaces del menú: dirección => texto. El que coincide con la página
// actual se marca (aria-current) para que se vea dónde está la persona.
$menu_publico = [
    '/'              => 'Inicio',
    '/servicios.php' => 'Servicios',
    '/nosotros.php'  => 'Nosotros',
    '/contacto.php'  => 'Contacto',
];
$pagina_actual = '/' . basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($pagina_actual === '/index.php') {
    $pagina_actual = '/';
}

// Dibuja un icono de public/assets/img/iconos.svg (decorativo).
$icono_publico = fn (string $id): string =>
    '<svg class="pub-icono" aria-hidden="true" focusable="false"><use href="'
    . e(recurso('/assets/img/iconos.svg') . '#' . $id) . '"></use></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?> | Clínica Vida Sana</title>
    <!-- Modo claro/oscuro: primero de todo y sin "defer", para aplicar el
         tema elegido antes de pintar (sin parpadeo del tema equivocado). -->
    <script src="<?= e(recurso('/assets/js/tema.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(recurso('/assets/css/base.css')) ?>">
    <!-- Diseño 2.0 de la web pública (independiente del panel y del portal) -->
    <link rel="stylesheet" href="<?= e(recurso('/assets/css/publico.css')) ?>">
    <?php if (!empty($script_pagina)): ?>
        <!-- JavaScript propio de esta página. "defer": se ejecuta cuando el
             HTML ya cargó. Va en un archivo aparte (no dentro del HTML)
             porque la política de seguridad (CSP) bloquea el JS incrustado. -->
        <script src="<?= e(recurso('/assets/js/' . $script_pagina)) ?>" defer></script>
    <?php endif; ?>
    <!-- Menú del celular, aparición al bajar y "cargando" de los formularios -->
    <script src="<?= e(recurso('/assets/js/publico.js')) ?>" defer></script>
</head>
<body class="publico">
<!-- Accesibilidad: con Tab, lo primero es saltar el menú. -->
<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>
<header class="pub-cabecera" data-cabecera>
    <div class="pub-cabecera-interior">
        <a class="pub-marca" href="<?= e(url('/')) ?>">
            <img class="logo-oficial" src="<?= e(recurso('/assets/img/branding/logo.png')) ?>" width="2172" height="724" alt="Clínica Vida Sana">
        </a>

        <!-- Celular: este botón abre y cierra el menú (lo activa publico.js) -->
        <button class="pub-boton-menu" type="button" data-boton-menu
                aria-controls="pub-menu" aria-expanded="false" hidden>
            <span class="pub-hamburguesa" aria-hidden="true"><span></span></span>
            <span>Menú</span>
        </button>

        <nav class="pub-menu" id="pub-menu" aria-label="Menú principal">
            <?php foreach ($menu_publico as $ruta => $texto): ?>
                <a href="<?= e(url($ruta)) ?>"
                   <?= $ruta === $pagina_actual ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
            <?php endforeach; ?>
            <?php if ($sesion_publica !== null): ?>
                <a class="pub-boton pub-boton-chico"
                   href="<?= e(url(ruta_inicio_por_rol($sesion_publica['rol']))) ?>">Mi cuenta</a>
            <?php else: ?>
                <a class="pub-boton pub-boton-chico" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
            <?php endif; ?>
        </nav>

        <!-- Modo claro/oscuro: fuera del menú, para que en el celular se vea
             siempre junto al botón "Menú" -->
        <?php require __DIR__ . '/switch_tema.php'; ?>
    </div>
</header>
<main class="pub-contenido" id="contenido" tabindex="-1">
