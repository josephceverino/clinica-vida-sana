<?php
// Auditoría: quién hizo qué, sobre qué paciente, cuándo y desde qué IP.
// Solo la ve el administrador. Es de SOLO LECTURA: nadie puede editar ni
// borrar el registro desde la web.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

// Regla 6: solo el administrador.
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

<header class="panel-titulo">
    <div>
        <h1>Auditoría</h1>
        <p>Quién hizo qué y cuándo. Sirve para saber quién consultó la historia clínica de cada paciente.</p>
    </div>
</header>

<form class="agenda-filtro filtro-auditoria" method="get" action="<?= e(url('/panel/auditoria.php')) ?>">
    <label for="accion">Acción</label>
    <select id="accion" name="accion">
        <option value="">Todas</option>
        <?php foreach ($acciones as $clave => $texto): ?>
            <option value="<?= e($clave) ?>" <?= $clave === $filtro_accion ? 'selected' : '' ?>>
                <?= e($texto) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="paciente">Paciente</label>
    <select id="paciente" name="paciente">
        <option value="">Todos</option>
        <?php foreach ($pacientes as $paciente): ?>
            <option value="<?= (int) $paciente['id'] ?>"
                <?= $filtro_paciente === (int) $paciente['id'] ? 'selected' : '' ?>>
                <?= e($paciente['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="boton-tabla" type="submit">Filtrar</button>
    <?php if ($filtro_accion !== '' || $filtro_paciente !== null): ?>
        <a href="<?= e(url('/panel/auditoria.php')) ?>">Quitar filtros</a>
    <?php endif; ?>
</form>

<p class="vacio">Se muestran los <?= count($registros) ?> registros más recientes (máximo 200).</p>

<?php if ($registros === []): ?>
    <section class="caja">
        <p class="vacio">No hay registros con estos filtros.</p>
    </section>
<?php else: ?>
    <div class="caja tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Quién</th>
                    <th>Acción</th>
                    <th>Paciente</th>
                    <th>Detalle</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i:s', strtotime($registro['creado_en']))) ?></td>
                        <td>
                            <?= e($registro['usuario'] ?? 'Cuenta eliminada') ?><br>
                            <small><?= e($nombres_rol[$registro['rol']] ?? '') ?></small>
                        </td>
                        <td>
                            <?php if (in_array($registro['accion'], $acciones_clinicas, true)): ?>
                                <span class="estado estado-nueva">dato clínico</span><br>
                            <?php endif; ?>
                            <?= e($acciones[$registro['accion']] ?? $registro['accion']) ?>
                        </td>
                        <td><?= e($registro['paciente'] ?? '—') ?></td>
                        <td><small><?= e($registro['detalle'] ?? '') ?></small></td>
                        <td><small><?= e($registro['ip']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
