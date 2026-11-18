<?php
// Cabecera común del Portal del paciente.
// Antes de incluirla, la página debe haber llamado a requerir_paciente() (o
// requerir_paciente_verificado()) y definir:
//   $titulo_pagina  -> título de la pestaña
//   $pagina_activa  -> archivo del menú (ej. 'index.php')
require_once __DIR__ . '/../core/auth.php';
enviar_cabeceras_seguridad();

$usuario = usuario_actual();
$titulo_pagina = $titulo_pagina ?? 'Mi cuenta';
$pagina_activa = $pagina_activa ?? 'index.php';
$verificada = ($usuario['verificado'] ?? 0) === 1;

// Menú del portal: archivo => [texto, icono]. Lo que aún no existe sale apagado.
// El icono es el id de un dibujo de public/assets/img/iconos.svg.
$menu_paciente = [
    'index.php'      => ['Inicio', 'i-casa'],
    'citas.php'      => ['Mis citas', 'i-calendario'],
    'pedir-cita.php' => ['Pedir cita', 'i-agregar'],
    'historia.php'   => ['Historia clínica', 'i-documento'],
];

// Dibuja un icono del archivo iconos.svg (decorativo: el texto ya dice qué es).
// Es el mismo ayudante que usa el panel, para que las páginas se escriban igual.
$icono_panel = fn (string $id): string =>
    '<svg class="icono" aria-hidden="true" focusable="false"><use href="'
    . e(url('/assets/img/iconos.svg#' . $id)) . '"></use></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titulo_pagina) ?> | Portal del paciente</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/base.css')) ?>">
    <!-- Los componentes (botones, tarjetas, formularios, tablas, avisos y
         animaciones) son los mismos del panel: se reutiliza panel.css. -->
    <link rel="stylesheet" href="<?= e(url('/assets/css/panel.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/paciente.css')) ?>">
    <!-- Indicador "cargando" de los formularios (archivo aparte por la CSP). -->
    <script src="<?= e(url('/assets/js/panel.js')) ?>"></script>
</head>
<body class="panel-body portal">
<!-- Accesibilidad: con Tab, lo primero es saltar el menú. -->
<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>
<header class="portal-barra">
    <div class="portal-barra-interior">
        <a class="portal-marca" href="<?= e(url('/paciente/index.php')) ?>">
            <?= $icono_panel('i-cruz') ?>
            <span>Vida Sana</span>
            <small>Portal del paciente</small>
        </a>

        <!-- En el celular, este menú pasa a una barra fija abajo -->
        <nav class="portal-menu" aria-label="Menú del portal">
            <?php foreach ($menu_paciente as $archivo => [$texto, $icono]): ?>
                <?php if (file_exists(__DIR__ . '/../../public/paciente/' . $archivo)): ?>
                    <a href="<?= e(url('/paciente/' . $archivo)) ?>"
                       class="<?= $archivo === $pagina_activa ? 'activo' : '' ?>"
                       <?= $archivo === $pagina_activa ? 'aria-current="page"' : '' ?>>
                        <?= $icono_panel($icono) ?>
                        <span><?= e($texto) ?></span>
                    </a>
                <?php else: ?>
                    <span class="deshabilitado"><?= $icono_panel($icono) ?><span><?= e($texto) ?> <small>pronto</small></span></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="portal-sesion">
            <a class="portal-perfil <?= $pagina_activa === 'perfil.php' ? 'activo' : '' ?>"
               href="<?= e(url('/paciente/perfil.php')) ?>"
               <?= $pagina_activa === 'perfil.php' ? 'aria-current="page"' : '' ?>
               title="Mi perfil y contraseña">
                <?= $icono_panel('i-usuario') ?>
                <span class="portal-perfil-nombre"><?= e($usuario['nombre']) ?></span>
                <span class="solo-lectores">(Mi perfil)</span>
            </a>
            <form method="post" action="<?= e(url('/logout.php')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="portal-salir">
                    <?= $icono_panel('i-salir') ?>
                    <span>Salir</span>
                </button>
            </form>
        </div>
    </div>
</header>
<main class="panel-contenido portal-contenido" id="contenido" tabindex="-1">
