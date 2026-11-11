<?php
// Cabecera común de las páginas del panel (zona privada).
// Antes de incluirla, cada página del panel debe haber llamado a
// requerir_login() y definir:
//   $titulo_pagina  -> título de la pestaña y de la página
//   $pagina_activa  -> nombre del archivo del menú (ej. 'index.php')
require_once __DIR__ . '/../core/auth.php';
enviar_cabeceras_seguridad();

date_default_timezone_set('America/Lima');

$usuario = usuario_actual();
$titulo_pagina = $titulo_pagina ?? 'Panel';
$pagina_activa = $pagina_activa ?? 'index.php';

// Menú lateral: archivo => [texto, roles que lo ven]
$menu_panel = [
    'index.php'       => ['Resumen',     ['admin', 'admision', 'medico']],
    'pacientes.php'   => ['Pacientes',   ['admin', 'admision', 'medico']],
    'citas.php'       => ['Citas',       ['admin', 'admision', 'medico']],
    'solicitudes.php' => ['Solicitudes', ['admin', 'admision']],
    'usuarios.php'    => ['Usuarios',    ['admin']],
];

// Iniciales para el círculo del usuario (ej. "Administrador Demo" -> "AD").
$partes = preg_split('/\s+/', trim($usuario['nombre']));
$iniciales = mb_strtoupper(
    mb_substr($partes[0], 0, 1) . mb_substr($partes[1] ?? '', 0, 1)
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titulo_pagina) ?> | Panel Vida Sana</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/panel.css')) ?>">
</head>
<body class="panel-body">
<div class="panel">

    <aside class="panel-lateral">
        <a class="panel-marca" href="<?= e(url('/panel/index.php')) ?>">
            Vida Sana <span>Panel</span>
        </a>

        <nav class="panel-menu" aria-label="Menú del panel">
            <?php foreach ($menu_panel as $archivo => [$texto, $roles]): ?>
                <?php if (!in_array($usuario['rol'], $roles, true)) { continue; } ?>

                <?php if (file_exists(__DIR__ . '/../../public/panel/' . $archivo)): ?>
                    <a href="<?= e(url('/panel/' . $archivo)) ?>"
                       class="<?= $archivo === $pagina_activa ? 'activo' : '' ?>">
                        <?= e($texto) ?>
                    </a>
                <?php else: ?>
                    <!-- Esta sección aún no existe: se muestra apagada. -->
                    <span class="deshabilitado"><?= e($texto) ?> <small>pronto</small></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="panel-usuario">
            <span class="panel-avatar"><?= e($iniciales) ?></span>
            <div>
                <strong><?= e($usuario['nombre']) ?></strong>
                <small><?= e($usuario['rol']) ?></small>
            </div>
            <form method="post" action="<?= e(url('/logout.php')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="panel-salir">Salir</button>
            </form>
        </div>
    </aside>

    <div class="panel-principal">
        <main class="panel-contenido">
