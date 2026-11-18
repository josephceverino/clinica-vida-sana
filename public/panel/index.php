<?php
// Resumen del panel (zona privada).
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/cuentas.php';

// Regla 6: toda página del panel empieza protegida.
// Solo entra el personal: los pacientes tienen su propio portal y no deben
// ver datos de otras personas (regla 6: primero el login, luego el rol).
requerir_login();
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

// --- Textos para mostrar (solo presentación) ------------------------------
require __DIR__ . '/../../app/layout/panel_header.php';

// Fecha de hoy en español, ej. "Martes, 17 de noviembre de 2026".
$dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$meses_cortos = ['ene', 'feb', 'mar', 'abr', 'may', 'jun',
                 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fecha_hoy = $dias[(int) date('w')] . ', ' . date('j') . ' de '
    . $meses[(int) date('n') - 1] . ' de ' . date('Y');

// Nombre del rol para la etiqueta de arriba a la derecha.
$nombres_rol = ['admin' => 'Administrador', 'admision' => 'Admisión'];
$nombre_rol = $nombres_rol[$yo['rol']] ?? $yo['rol'];

// Fecha corta de una cita, ej. "18 nov".
$fecha_corta = function (string $fecha) use ($meses_cortos): string {
    $momento = strtotime($fecha);
    return date('j', $momento) . ' ' . $meses_cortos[(int) date('n', $momento) - 1];
};
?>

<!-- Estilos propios de esta página: todo va dentro de .resumen-admin
     (ver panel.css) para no cambiar las demás pantallas del panel. -->
<div class="resumen-admin ui-animada">

    <header class="resumen-cabecera">
        <div>
            <h1>Panel general</h1>
            <p>
                Resumen operativo de la clínica &middot;
                <time datetime="<?= e(date('Y-m-d')) ?>"><?= e($fecha_hoy) ?></time>
            </p>
        </div>
        <span class="resumen-pastilla"><?= e($nombre_rol) ?></span>
    </header>

    <!-- Indicadores: los cinco números reales de la base de datos -->
    <section class="resumen-indicadores" aria-label="Indicadores del día">
        <article class="resumen-indicador">
            <h2>Citas de hoy</h2>
            <p class="resumen-numero"><?= e(number_format($citas_hoy)) ?></p>
            <p class="resumen-nota">Agenda del día</p>
        </article>
        <article class="resumen-indicador">
            <h2>Citas pendientes</h2>
            <p class="resumen-numero"><?= e(number_format($citas_pendientes)) ?></p>
            <p class="resumen-nota">Por confirmar</p>
        </article>
        <?php if ($ve_gestion): ?>
            <article class="resumen-indicador">
                <h2>Pacientes registrados</h2>
                <p class="resumen-numero"><?= e(number_format($total_pacientes)) ?></p>
                <p class="resumen-nota">Total acumulado</p>
            </article>
            <a class="resumen-indicador resumen-indicador-enlace" href="<?= e(url('/panel/solicitudes.php')) ?>">
                <h2>Solicitudes nuevas</h2>
                <p class="resumen-numero"><?= e(number_format($solicitudes_nuevas)) ?></p>
                <p class="resumen-nota">Requieren revisión</p>
            </a>
            <a class="resumen-indicador resumen-indicador-enlace" href="<?= e(url('/panel/cuentas.php')) ?>">
                <h2>Cuentas por verificar</h2>
                <p class="resumen-numero"><?= e(number_format($cuentas_pendientes)) ?></p>
                <p class="resumen-nota">Esperan a admisión</p>
            </a>
        <?php endif; ?>
    </section>

    <!-- Próximas citas (las mismas 6 de siempre, ahora en tabla) -->
    <section class="resumen-caja">
        <div class="resumen-caja-cabecera">
            <h2>Próximas citas</h2>
            <a class="resumen-pastilla" href="<?= e(url('/panel/citas.php')) ?>">Ver todas</a>
        </div>

        <?php if (count($proximas) === 0): ?>
            <p class="resumen-vacio">No hay citas próximas.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="resumen-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Paciente</th>
                            <th scope="col">Especialidad</th>
                            <th scope="col">Fecha / hora</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proximas as $cita): ?>
                            <tr>
                                <td class="resumen-paciente">
                                    <?= e($cita['nombres'] . ' ' . $cita['apellidos']) ?>
                                </td>
                                <td>
                                    <?= e($cita['area']) ?>
                                    <small><?= e($cita['motivo']) ?></small>
                                </td>
                                <td class="resumen-fecha">
                                    <?= e($fecha_corta($cita['fecha'])) ?> &middot; <?= e(substr($cita['hora'], 0, 5)) ?>
                                </td>
                                <td>
                                    <span class="resumen-estado resumen-estado-<?= e($cita['estado']) ?>">
                                        <?= e(ucfirst(texto_estado($cita['estado']))) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="resumen-columnas">

        <!-- Solicitudes nuevas (las mismas 5 de siempre) -->
        <?php if ($ve_gestion): ?>
            <section class="resumen-caja">
                <div class="resumen-caja-cabecera">
                    <h2>Solicitudes de cita</h2>
                    <a class="resumen-pastilla" href="<?= e(url('/panel/solicitudes.php')) ?>">Ver todas</a>
                </div>
                <?php if (count($solicitudes) === 0): ?>
                    <p class="resumen-vacio">No hay solicitudes nuevas.</p>
                <?php else: ?>
                    <ul class="resumen-lista">
                        <?php foreach ($solicitudes as $sol): ?>
                            <li>
                                <strong><?= e($sol['nombre']) ?></strong>
                                <small><?= e($sol['area']) ?> &middot; Tel. <?= e($sol['telefono']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Accesos rápidos: enlaces a páginas que admin y admisión ya pueden abrir -->
            <section class="resumen-caja">
                <h2>Accesos rápidos</h2>
                <ul class="resumen-accesos">
                    <li><a class="resumen-pastilla" href="<?= e(url('/panel/registrar-paciente.php')) ?>">+ Registrar paciente</a></li>
                    <li><a class="resumen-pastilla" href="<?= e(url('/panel/registrar-cita.php')) ?>">+ Registrar cita</a></li>
                    <li><a class="resumen-pastilla" href="<?= e(url('/panel/agenda.php')) ?>">Consultar agenda</a></li>
                </ul>
            </section>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
