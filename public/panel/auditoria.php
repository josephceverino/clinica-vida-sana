<?php
// Auditoría: quién hizo qué, sobre qué paciente, cuándo y desde qué IP.
// Solo la ve el administrador. Es de SOLO LECTURA: nadie puede editar ni
// borrar el registro desde la web.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

// Regla 6: solo el administrador.
requerir_login();
requerir_rol(['admin']);

$titulo_pagina = 'Auditoría';
$pagina_activa = 'auditoria.php';
$acciones = acciones_auditoria();

// --- Filtros (GET: solo cambian lo que se ve) -----------------------------
$filtro_accion = is_string($_GET['accion'] ?? null) ? $_GET['accion'] : '';
if (!isset($acciones[$filtro_accion])) {
    $filtro_accion = '';
}

$pacientes = db()->query(
    "SELECT id, CONCAT(apellidos, ', ', nombres) AS nombre
     FROM pacientes ORDER BY apellidos, nombres"
)->fetchAll();

// Solo se acepta un paciente que exista en la lista.
$filtro_paciente = null;
$pedido = is_string($_GET['paciente'] ?? null) ? $_GET['paciente'] : '';
foreach ($pacientes as $paciente) {
    if ((string) $paciente['id'] === $pedido) {
        $filtro_paciente = (int) $paciente['id'];
    }
}

$registros = buscar_auditoria($filtro_accion, $filtro_paciente);

$nombres_rol = [
    'admin' => 'Admin', 'admision' => 'Admisión',
    'medico' => 'Médico', 'paciente' => 'Paciente',
];

// Acciones sobre datos clínicos: se resaltan en la tabla.
$acciones_clinicas = ['ver_historia', 'ver_atencion', 'registrar_atencion'];

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Auditoría</h1>
            <p>Quién hizo qué y cuándo. Sirve para saber quién consultó la historia clínica de cada paciente.</p>
        </div>
    </header>

    <!-- Filtros (GET: solo cambian lo que se ve) -->
    <section class="ui-tarjeta" aria-label="Filtros de la auditoría">
        <form class="ui-filtros" method="get" action="<?= e(url('/panel/auditoria.php')) ?>">
            <div class="ui-campo">
                <label for="accion">Acción</label>
                <select class="ui-entrada" id="accion" name="accion">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $clave => $texto): ?>
                        <option value="<?= e($clave) ?>" <?= $clave === $filtro_accion ? 'selected' : '' ?>>
                            <?= e($texto) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ui-campo">
                <label for="paciente">Paciente</label>
                <select class="ui-entrada" id="paciente" name="paciente">
                    <option value="">Todos</option>
                    <?php foreach ($pacientes as $paciente): ?>
                        <option value="<?= (int) $paciente['id'] ?>"
                            <?= $filtro_paciente === (int) $paciente['id'] ? 'selected' : '' ?>>
                            <?= e($paciente['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ui-grupo">
                <button class="ui-boton ui-boton-chico" type="submit">Filtrar</button>
                <?php if ($filtro_accion !== '' || $filtro_paciente !== null): ?>
                    <a class="ui-boton ui-boton-secundario ui-boton-chico" href="<?= e(url('/panel/auditoria.php')) ?>">Quitar filtros</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="ui-tarjeta">
        <?php if ($registros === []): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-documento') ?></span>
                <strong>No hay registros con estos filtros.</strong>
                <p>Prueba con otra acción u otro paciente.</p>
            </div>
        <?php else: ?>
            <div class="ui-tarjeta-cabecera">
                <h2>Registros</h2>
                <span class="ui-insignia"><?= count($registros) ?> más recientes</span>
            </div>

            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable ui-tabla-apilable-ancha ui-tabla-compacta" role="table">
                    <caption class="solo-lectores">Registros de auditoría</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Fecha y hora</th>
                            <th scope="col" role="columnheader">Quién</th>
                            <th scope="col" role="columnheader">Acción</th>
                            <th scope="col" role="columnheader">Paciente</th>
                            <th scope="col" role="columnheader">Detalle</th>
                            <th scope="col" role="columnheader">IP</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($registros as $registro): ?>
                            <tr role="row">
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Fecha y hora">
                                    <?= e(date('d/m/Y H:i:s', strtotime($registro['creado_en']))) ?>
                                </td>
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Quién">
                                    <span class="ui-celda-valor">
                                        <?= e($registro['usuario'] ?? 'Cuenta eliminada') ?>
                                        <small><?= e($nombres_rol[$registro['rol']] ?? '') ?></small>
                                    </span>
                                </td>
                                <td role="cell" data-etiqueta="Acción">
                                    <span class="ui-celda-valor">
                                        <?php if (in_array($registro['accion'], $acciones_clinicas, true)): ?>
                                            <span class="estado estado-nueva ui-dato-clinico">dato clínico</span>
                                        <?php endif; ?>
                                        <?= e($acciones[$registro['accion']] ?? $registro['accion']) ?>
                                    </span>
                                </td>
                                <td role="cell" data-etiqueta="Paciente"><?= e($registro['paciente'] ?? '—') ?></td>
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Detalle"><?= e($registro['detalle'] ?? '') ?></td>
                                <td class="ui-celda-suave" role="cell" data-etiqueta="IP"><?= e($registro['ip']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="ui-nota">Se muestran los <?= count($registros) ?> registros más recientes (máximo 200).</p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
