<?php
// Resumen del panel (zona privada).
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/cuentas.php';

// Regla 6: toda página del panel empieza protegida.
// Solo entra el personal: los pacientes tienen su propio portal y no deben
// ver datos de otras personas (requerir_rol ya incluye requerir_login).
requerir_rol(['admin', 'admision', 'medico']);

// El médico tiene su propio resumen ("Mi día"), pensado para su trabajo.
if (usuario_actual()['rol'] === 'medico') {
    require __DIR__ . '/inicio-medico.php';
    exit;
}

$titulo_pagina = 'Resumen';
$pagina_activa = 'index.php';
$pdo = db();

$yo = usuario_actual();
$es_medico = $yo['rol'] === 'medico';
// Admin y admisión gestionan solicitudes, cuentas y pacientes. El médico no.
$ve_gestion = in_array($yo['rol'], ['admin', 'admision'], true);

// El médico solo ve SUS citas. Para los demás roles no hay filtro.
// (El texto SQL lo decide el código; el id del médico va como parámetro.)
$donde_medico = $es_medico ? ' AND medico_id = :medico' : '';
// Lo mismo, para la consulta que usa el alias "c" (c.medico_id).
$donde_medico_c = $es_medico ? ' AND c.medico_id = :medico' : '';
$parametros_medico = $es_medico ? ['medico' => $yo['id']] : [];

// --- Números de las tarjetas -------------------------------------------
$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM citas
     WHERE fecha = CURDATE() AND estado <> 'cancelada'" . $donde_medico
);
$consulta->execute($parametros_medico);
$citas_hoy = (int) $consulta->fetchColumn();

$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM citas
     WHERE estado = 'pendiente' AND fecha >= CURDATE()" . $donde_medico
);
$consulta->execute($parametros_medico);
$citas_pendientes = (int) $consulta->fetchColumn();

$solicitudes_nuevas = 0;
$cuentas_pendientes = 0;
$total_pacientes = 0;
if ($ve_gestion) {
    $solicitudes_nuevas = (int) $pdo->query(
        "SELECT COUNT(*) FROM solicitudes_cita WHERE estado = 'nueva'"
    )->fetchColumn();
    $cuentas_pendientes = contar_cuentas_pendientes();
    $total_pacientes = (int) $pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();
}

// --- Próximas citas (de hoy en adelante) ----------------------------------
$consulta = $pdo->prepare(
    "SELECT c.fecha, c.hora, c.motivo, c.estado,
            p.nombres, p.apellidos, a.nombre AS area
     FROM citas c
     JOIN pacientes p ON p.id = c.paciente_id
     JOIN areas a     ON a.id = c.area_id
     WHERE c.fecha >= CURDATE() AND c.estado IN ('pendiente', 'confirmada')"
     . $donde_medico_c . "
     ORDER BY c.fecha, c.hora
     LIMIT 6"
);
$consulta->execute($parametros_medico);
$proximas = $consulta->fetchAll();

// --- Solicitudes nuevas (solo las ven admin y admisión) ---------------------
$solicitudes = [];
if ($ve_gestion) {
    $solicitudes = $pdo->query(
        "SELECT CONCAT(p.nombres, ' ', p.apellidos) AS nombre, p.telefono,
                s.fecha_preferida, a.nombre AS area
         FROM solicitudes_cita s
         JOIN pacientes p ON p.id = s.paciente_id
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
        <p><?= $es_medico ? 'Esto es lo que tienes hoy.' : 'Esto es lo que pasa hoy en la clínica.' ?></p>
    </div>
    <time datetime="<?= e(date('Y-m-d')) ?>"><?= e(date('d/m/Y')) ?></time>
</header>

<!-- Tarjetas de números -->
<section class="tarjetas">
    <article class="dato">
        <span class="dato-numero"><?= $citas_hoy ?></span>
        <span class="dato-texto"><?= $es_medico ? 'Mis citas hoy' : 'Citas hoy' ?></span>
    </article>
    <article class="dato">
        <span class="dato-numero"><?= $citas_pendientes ?></span>
        <span class="dato-texto"><?= $es_medico ? 'Mis citas por confirmar' : 'Citas por confirmar' ?></span>
    </article>
    <?php if ($ve_gestion): ?>
        <article class="dato dato-alerta">
            <span class="dato-numero"><?= $solicitudes_nuevas ?></span>
            <span class="dato-texto">Solicitudes nuevas</span>
        </article>
        <a class="dato dato-alerta dato-enlace" href="<?= e(url('/panel/cuentas.php')) ?>">
            <span class="dato-numero"><?= $cuentas_pendientes ?></span>
            <span class="dato-texto">Cuentas por verificar</span>
        </a>
        <article class="dato">
            <span class="dato-numero"><?= $total_pacientes ?></span>
            <span class="dato-texto">Pacientes registrados</span>
        </article>
    <?php endif; ?>
</section>

<div class="panel-columnas">

    <!-- Próximas citas -->
    <section class="caja">
        <h2><?= $es_medico ? 'Mis próximas citas' : 'Próximas citas' ?></h2>
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
    <?php if ($ve_gestion): ?>
        <section class="caja">
            <h2>Solicitudes de cita</h2>
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
