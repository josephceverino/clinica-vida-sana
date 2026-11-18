<?php
// Cabecera común de las páginas del panel (zona privada).
// Antes de incluirla, cada página del panel debe haber llamado a
// requerir_login() y definir:
//   $titulo_pagina  -> título de la pestaña y de la página
//   $pagina_activa  -> nombre del archivo del menú (ej. 'index.php')
require_once __DIR__ . '/../core/auth.php';
enviar_cabeceras_seguridad();

$usuario = usuario_actual();
$titulo_pagina = $titulo_pagina ?? 'Panel';
$pagina_activa = $pagina_activa ?? 'index.php';

// Menú lateral, en grupos. Cada grupo tiene un título (o null) y sus enlaces:
//   archivo => [texto, roles que lo ven, icono]
// Un enlace solo se muestra si el rol puede abrir esa página; los grupos sin
// ningún enlace visible no se dibujan. El icono es el id de un dibujo de
// public/assets/img/iconos.svg (solo cambia el aspecto, no los permisos).
$menu_panel = [
    [
        'titulo'  => 'Resumen',
        'enlaces' => [
            'index.php' => ['Panel general', ['admin', 'admision', 'medico'], 'i-resumen'],
        ],
    ],
    [
        'titulo'  => 'Atención',
        'enlaces' => [
            'pacientes.php'   => ['Pacientes',   ['admin', 'admision'],           'i-personas'],
            'citas.php'       => ['Citas',       ['admin', 'admision', 'medico'], 'i-calendario'],
            'agenda.php'      => ['Agenda',      ['admin', 'admision', 'medico'], 'i-reloj'],
            'solicitudes.php' => ['Solicitudes', ['admin', 'admision'],           'i-documento'],
            'cuentas.php'     => ['Cuentas',     ['admin', 'admision'],           'i-escudo'],
        ],
    ],
    [
        'titulo'  => 'Administración',
        'enlaces' => [
            'usuarios.php'  => ['Usuarios',  ['admin'], 'i-personas'],
            'horarios.php'  => ['Horarios',  ['admin'], 'i-calendario'],
            'auditoria.php' => ['Auditoría', ['admin'], 'i-documento'],
        ],
    ],
];

// Dibuja un icono del archivo iconos.svg. Es decorativo (aria-hidden):
// el texto del enlace ya dice qué es.
$icono_panel = fn (string $id): string =>
    '<svg class="icono" aria-hidden="true" focusable="false"><use href="'
    . e(recurso('/assets/img/iconos.svg') . '#' . $id) . '"></use></svg>';

// Números que se muestran como globito junto a un enlace del menú.
$globitos_menu = [];
if (in_array($usuario['rol'], ['admin', 'admision'], true)) {
    require_once __DIR__ . '/../modelos/cuentas.php';
    $globitos_menu['cuentas.php'] = contar_cuentas_pendientes();
    // Solicitudes que esperan que admisión las programe o descarte.
    $globitos_menu['solicitudes.php'] = (int) db()->query(
        "SELECT COUNT(*) FROM solicitudes_cita WHERE estado = 'nueva'"
    )->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titulo_pagina) ?> | Panel Vida Sana</title>
    <!-- Modo claro/oscuro: primero de todo y sin "defer", para aplicar el
         tema elegido antes de pintar (sin parpadeo del tema equivocado). -->
    <script src="<?= e(recurso('/assets/js/tema.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(recurso('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(recurso('/assets/css/panel.css')) ?>">
    <!-- Menú contraíble y menú del celular. Va en <head> y SIN "defer" a
         propósito: así aplica la preferencia guardada (menú contraído) antes
         de que se pinte la página, sin parpadeo. Es un archivo aparte porque
         la política de seguridad (CSP) bloquea el JavaScript incrustado. -->
    <script src="<?= e(recurso('/assets/js/panel.js')) ?>"></script>
</head>
<body class="panel-body">
<!-- Accesibilidad: con Tab, lo primero es saltar el menú. -->
<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>
<div class="panel">

    <!-- Barra superior: solo se ve en el celular (con JavaScript). -->
    <div class="panel-barra-movil">
        <a class="panel-marca" href="<?= e(url('/panel/index.php')) ?>">
            <?= $icono_panel('i-cruz') ?>
            <span class="panel-marca-nombre">Vida Sana</span>
        </a>
        <button type="button" class="panel-boton-menu" data-abrir-menu
                aria-controls="panel-lateral" aria-expanded="false" hidden>
            <?= $icono_panel('i-menu') ?>
            <span>Menú</span>
        </button>
    </div>

    <aside class="panel-lateral" id="panel-lateral">
        <div class="panel-cabecera-lateral">
            <a class="panel-marca" href="<?= e(url('/panel/index.php')) ?>">
                <?= $icono_panel('i-cruz') ?>
                <span class="panel-marca-nombre">Vida Sana</span>
                <span class="panel-marca-lema">Gestión clínica</span>
            </a>
            <!-- Escritorio: contraer o expandir el menú. -->
            <button type="button" class="panel-boton-lateral panel-contraer" data-contraer-lateral
                    aria-controls="panel-lateral" aria-expanded="true" aria-label="Contraer menú"
                    title="Contraer menú" hidden>
                <?= $icono_panel('i-contraer') ?>
            </button>
            <!-- Celular: cerrar el menú. -->
            <button type="button" class="panel-boton-lateral panel-cerrar-menu" data-cerrar-menu
                    aria-label="Cerrar menú" hidden>
                <?= $icono_panel('i-cerrar') ?>
            </button>
        </div>

        <nav class="panel-menu" aria-label="Menú del panel">
            <?php foreach ($menu_panel as $grupo): ?>
                <?php
                // Enlaces de este grupo que el rol puede ver.
                $visibles = array_filter(
                    $grupo['enlaces'],
                    fn ($datos) => in_array($usuario['rol'], $datos[1], true)
                );
                if (count($visibles) === 0) { continue; }
                ?>

                <?php if ($grupo['titulo'] !== null): ?>
                    <p class="panel-menu-titulo"><?= e($grupo['titulo']) ?></p>
                <?php endif; ?>

                <?php foreach ($visibles as $archivo => [$texto, $roles, $icono]): ?>
                    <?php if (file_exists(__DIR__ . '/../../public/panel/' . $archivo)): ?>
                        <a href="<?= e(url('/panel/' . $archivo)) ?>"
                           class="<?= $archivo === $pagina_activa ? 'activo' : '' ?>"
                           <?= $archivo === $pagina_activa ? 'aria-current="page"' : '' ?>>
                            <?= $icono_panel($icono) ?>
                            <span class="panel-menu-texto"><?= e($texto) ?></span>
                            <?php if (($globitos_menu[$archivo] ?? 0) > 0): ?>
                                <span class="globito"><?= (int) $globitos_menu[$archivo] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <!-- Esta sección aún no existe: se muestra apagada. -->
                        <span class="deshabilitado">
                            <?= $icono_panel($icono) ?>
                            <span class="panel-menu-texto"><?= e($texto) ?></span>
                            <small>pronto</small>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Abajo: el usuario (lleva a "Mi perfil") y cerrar sesión. -->
        <div class="panel-usuario">
            <?php $switch_tema_texto = true; require __DIR__ . '/switch_tema.php'; ?>
            <a class="panel-perfil <?= $pagina_activa === 'perfil.php' ? 'activo' : '' ?>"
               href="<?= e(url('/panel/perfil.php')) ?>" title="Mi perfil y contraseña">
                <?= $icono_panel('i-escudo') ?>
                <span class="panel-menu-texto">
                    <?= e($usuario['nombre']) ?>
                    <small>Mi perfil</small>
                </span>
            </a>
            <form method="post" action="<?= e(url('/logout.php')) ?>">
                <?= csrf_campo() ?>
                <button type="submit" class="panel-salir">
                    <?= $icono_panel('i-salir') ?>
                    <span class="panel-menu-texto">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Celular: fondo oscuro detrás del menú abierto (un clic lo cierra). -->
    <div class="panel-velo" data-velo-menu hidden></div>

    <div class="panel-principal">
        <main class="panel-contenido" id="contenido" tabindex="-1">
