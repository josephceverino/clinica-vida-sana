<?php
// Horarios de los médicos: el admin define qué días y a qué horas atiende
// cada uno, y cuánto dura cada turno. De aquí salen los huecos libres que
// el paciente puede reservar.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/horarios.php';

// Regla 6: solo el administrador.
requerir_rol(['admin']);

$titulo_pagina = 'Horarios de médicos';
$pagina_activa = 'horarios.php';
$error = '';

$medicos = db()->query(
    "SELECT usuarios.id, usuarios.nombre, areas.nombre AS area
     FROM usuarios
     LEFT JOIN areas ON areas.id = usuarios.area_id
     WHERE usuarios.rol = 'medico' AND usuarios.activo = 1
     ORDER BY usuarios.nombre"
)->fetchAll();

// Médico elegido (?medico=3). Solo se acepta uno de la lista; si no, el primero.
$medico = $medicos[0] ?? null;
$pedido = is_string($_GET['medico'] ?? null) ? $_GET['medico'] : '';
foreach ($medicos as $opcion) {
    if ((string) $opcion['id'] === $pedido) {
        $medico = $opcion;
    }
}

$valores = ['dia_semana' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '13:00', 'duracion_min' => '20'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $medico) {
    $ruta = '/panel/horarios.php?medico=' . (int) $medico['id'];

    try {
        // Regla 4: token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException('La sesión del formulario venció. Recarga la página.');
        }

        $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';

        if ($accion === 'agregar') {
            foreach ($valores as $campo => $vacio) {
                $valores[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
            }
            agregar_horario((int) $medico['id'], $valores);
            mensaje_guardar('Horario agregado.');
        } elseif ($accion === 'eliminar') {
            eliminar_horario((int) ($_POST['horario_id'] ?? 0), (int) $medico['id']);
            mensaje_guardar('Horario eliminado. Las citas ya reservadas se mantienen.');
        } else {
            throw new InvalidArgumentException('Acción no válida.');
        }

        redirigir($ruta);
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        // Regla 9: mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        $error = 'No se pudo guardar el horario.';
    }
}

$bloques = $medico ? horarios_de_medico((int) $medico['id']) : [];
$dias = dias_semana();
$mensaje = mensaje_tomar();

// Total de turnos a la semana (para mostrar la capacidad del médico).
$turnos_semana = 0;
foreach ($bloques as $bloque) {
    $turnos_semana += count(turnos_de_bloque(
        $bloque['hora_inicio'],
        $bloque['hora_fin'],
        (int) $bloque['duracion_min']
    ));
}

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div>
        <h1>Horarios de médicos</h1>
        <p>Qué días y a qué horas atiende cada médico. Los pacientes reservan en estos turnos.</p>
    </div>
</header>

<?php if ($mensaje): ?>
    <p role="<?= $mensaje['tipo'] === 'error' ? 'alert' : 'status' ?>"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$medico): ?>
    <section class="caja">
        <p class="vacio">No hay médicos activos. Créalos en "Usuarios del personal".</p>
    </section>
<?php else: ?>

    <!-- Elegir médico (GET: solo cambia lo que se ve) -->
    <form class="agenda-filtro filtro-auditoria" method="get" action="<?= e(url('/panel/horarios.php')) ?>">
        <label for="medico">Médico</label>
        <select id="medico" name="medico">
            <?php foreach ($medicos as $opcion): ?>
                <option value="<?= (int) $opcion['id'] ?>" <?= $opcion['id'] === $medico['id'] ? 'selected' : '' ?>>
                    <?= e($opcion['nombre'] . ' (' . ($opcion['area'] ?? 'sin área') . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="boton-tabla" type="submit">Ver</button>
    </form>

    <div class="panel-columnas">
        <!-- Horario actual -->
        <section class="caja">
            <h2>Horario de <?= e($medico['nombre']) ?></h2>
            <p class="vacio"><?= $turnos_semana ?> turnos por semana</p>

            <?php if ($bloques === []): ?>
                <p class="vacio">Sin horario: los pacientes no pueden reservar con este médico.</p>
            <?php else: ?>
                <table class="tabla">
                    <thead>
                        <tr><th>Día</th><th>Horario</th><th>Turnos</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bloques as $bloque): ?>
                            <tr>
                                <td><?= e($dias[(int) $bloque['dia_semana']]) ?></td>
                                <td>
                                    <?= e(substr($bloque['hora_inicio'], 0, 5)) ?> –
                                    <?= e(substr($bloque['hora_fin'], 0, 5)) ?>
                                </td>
                                <td>
                                    <?= count(turnos_de_bloque($bloque['hora_inicio'], $bloque['hora_fin'], (int) $bloque['duracion_min'])) ?>
                                    <small>de <?= (int) $bloque['duracion_min'] ?> min</small>
                                </td>
                                <td>
                                    <form method="post"
                                          action="<?= e(url('/panel/horarios.php?medico=' . (int) $medico['id'])) ?>">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="horario_id" value="<?= (int) $bloque['id'] ?>">
                                        <button class="boton-tabla boton-tabla-claro" type="submit">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Agregar un bloque -->
        <section class="caja">
            <h2>Agregar horario</h2>
            <form class="form-panel" method="post"
                  action="<?= e(url('/panel/horarios.php?medico=' . (int) $medico['id'])) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="agregar">

                <div class="campo">
                    <label for="dia_semana">Día</label>
                    <select id="dia_semana" name="dia_semana" required>
                        <?php foreach ($dias as $numero => $nombre): ?>
                            <option value="<?= $numero ?>" <?= $valores['dia_semana'] === (string) $numero ? 'selected' : '' ?>>
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="hora_inicio">Desde</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" required
                           value="<?= e($valores['hora_inicio']) ?>">
                </div>

                <div class="campo">
                    <label for="hora_fin">Hasta</label>
                    <input type="time" id="hora_fin" name="hora_fin" required
                           value="<?= e($valores['hora_fin']) ?>">
                </div>

                <div class="campo">
                    <label for="duracion_min">Duración de cada turno</label>
                    <select id="duracion_min" name="duracion_min" required>
                        <?php foreach (DURACIONES_TURNO as $minutos): ?>
                            <option value="<?= $minutos ?>" <?= $valores['duracion_min'] === (string) $minutos ? 'selected' : '' ?>>
                                <?= $minutos ?> minutos
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="boton" type="submit">Agregar horario</button>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
