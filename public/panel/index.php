<?php
// Resumen del panel (zona privada).
require_once __DIR__ . '/../../app/auth.php';

// Regla 6: toda página del panel empieza con requerir_login().
requerir_login();

$titulo_pagina = 'Resumen';
$pagina_activa = 'index.php';
$pdo = db();

// --- Números de las tarjetas -------------------------------------------
// Estas consultas no llevan datos del usuario, por eso usamos query().
$citas_hoy = (int) $pdo->query(
    "SELECT COUNT(*) FROM citas WHERE fecha = CURDATE() AND estado <> 'cancelada'"
)->fetchColumn();

$citas_pendientes = (int) $pdo->query(
    "SELECT COUNT(*) FROM citas WHERE estado = 'pendiente' AND fecha >= CURDATE()"
)->fetchColumn();

$solicitudes_nuevas = (int) $pdo->query(
    "SELECT COUNT(*) FROM solicitudes_cita WHERE estado = 'nueva'"
)->fetchColumn();

$total_pacientes = (int) $pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();

// --- Próximas citas (de hoy en adelante) ----------------------------------
$proximas = $pdo->query(
    "SELECT c.fecha, c.hora, c.motivo, c.estado,
            p.nombres, p.apellidos, a.nombre AS area
     FROM citas c
     JOIN pacientes p ON p.id = c.paciente_id
     JOIN areas a     ON a.id = c.area_id
     WHERE c.fecha >= CURDATE() AND c.estado IN ('pendiente', 'confirmada')
     ORDER BY c.fecha, c.hora
     LIMIT 6"
)->fetchAll();

// --- Solicitudes nuevas (solo las ven admin y admisión) ---------------------
$rol = usuario_actual()['rol'];
$ve_solicitudes = in_array($rol, ['admin', 'admision'], true);
$solicitudes = [];
if ($ve_solicitudes) {
    $solicitudes = $pdo->query(
        "SELECT s.nombre, s.telefono, s.fecha_preferida, a.nombre AS area
         FROM solicitudes_cita s
         JOIN areas a ON a.id = s.area_id
         WHERE s.estado = 'nueva'
         ORDER BY s.creado_en DESC
         LIMIT 5"
    )->fetchAll();
}

// Saludo según la hora.
require __DIR__ . '/../../app/layout/panel_header.php';
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$primer_nombre = explode(' ', trim($usuario['nombre']))[0];
?>

<header class="panel-titulo">
    <div>
        <h1><?= e($saludo) ?>, <?= e($primer_nombre) ?></h1>
        <p>Esto es lo que pasa hoy en la clínica.</p>
    </div>
    <time datetime="<?= e(date('Y-m-d')) ?>"><?= e(date('d/m/Y')) ?></time>
</header>

<!-- Tarjetas de números -->
<section class="tarjetas">
    <article class="dato">
        <span class="dato-numero"><?= $citas_hoy ?></span>
        <span class="dato-texto">Citas hoy</span>
    </article>
    <article class="dato">
        <span class="dato-numero"><?= $citas_pendientes ?></span>
        <span class="dato-texto">Citas por confirmar</span>
    </article>
    <?php if ($ve_solicitudes): ?>
        <article class="dato dato-alerta">
            <span class="dato-numero"><?= $solicitudes_nuevas ?></span>
            <span class="dato-texto">Solicitudes nuevas</span>
        </article>
    <?php endif; ?>
    <article class="dato">
        <span class="dato-numero"><?= $total_pacientes ?></span>
        <span class="dato-texto">Pacientes registrados</span>
    </article>
</section>

<div class="panel-columnas">

    <!-- Próximas citas -->
    <section class="caja">
        <h2>Próximas citas</h2>
        <?php if (count($proximas) === 0): ?>
            <p class="vacio">No hay citas próximas.</p>
        <?php else: ?>
            <ul class="agenda">
                <?php foreach ($proximas as $cita): ?>
                    <li>
                        <div class="agenda-hora">
                            <strong><?= e(substr($cita['hora'], 0, 5)) ?></strong>
                            <small><?= e(date('d/m', strtotime($cita['fecha']))) ?></small>
                        </div>
                        <div class="agenda-detalle">
                            <strong><?= e($cita['nombres'] . ' ' . $cita['apellidos']) ?></strong>
                            <small><?= e($cita['area']) ?> &middot; <?= e($cita['motivo']) ?></small>
                        </div>
                        <span class="estado estado-<?= e($cita['estado']) ?>">
                            <?= e($cita['estado']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Solicitudes nuevas -->
    <?php if ($ve_solicitudes): ?>
        <section class="caja">
            <h2>Solicitudes desde la web</h2>
            <?php if (count($solicitudes) === 0): ?>
                <p class="vacio">No hay solicitudes nuevas.</p>
            <?php else: ?>
                <ul class="agenda">
                    <?php foreach ($solicitudes as $sol): ?>
                        <li>
                            <div class="agenda-detalle">
                                <strong><?= e($sol['nombre']) ?></strong>
                                <small>
                                    <?= e($sol['area']) ?> &middot; Tel. <?= e($sol['telefono']) ?>
                                </small>
                            </div>
                            <span class="estado estado-nueva">nueva</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
