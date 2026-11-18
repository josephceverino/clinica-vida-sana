<?php
// Horarios de los médicos: el admin define qué días y a qué horas atiende
// cada uno, y cuánto dura cada turno. De aquí salen los huecos libres que
// el paciente puede reservar.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/horarios.php';

// Regla 6: solo el administrador.
requerir_login();
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

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Horarios de médicos</h1>
            <p>Qué días y a qué horas atiende cada médico. Los pacientes reservan en estos turnos.</p>
        </div>
    </header>

    <?php if ($mensaje): ?>
        <div class="ui-aviso <?= $mensaje['tipo'] === 'error' ? 'ui-aviso-error' : 'ui-aviso-exito' ?>"
             role="<?= $mensaje['tipo'] === 'error' ? 'alert' : 'status' ?>">
            <?= $icono_panel($mensaje['tipo'] === 'error' ? 'i-alerta' : 'i-visto') ?>
            <p><?= e($mensaje['texto']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$medico): ?>
        <section class="ui-tarjeta">
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-personas') ?></span>
                <strong>No hay médicos activos.</strong>
                <p>Créalos en "Usuarios del personal".</p>
            </div>
        </section>
    <?php else: ?>

        <!-- Elegir médico (GET: solo cambia lo que se ve) -->
        <section class="ui-tarjeta" aria-label="Elegir médico">
            <form class="ui-buscador" method="get" action="<?= e(url('/panel/horarios.php')) ?>">
                <div class="ui-campo">
                    <label for="medico">Médico</label>
                    <select class="ui-entrada" id="medico" name="medico">
                        <?php foreach ($medicos as $opcion): ?>
                            <option value="<?= (int) $opcion['id'] ?>" <?= $opcion['id'] === $medico['id'] ? 'selected' : '' ?>>
                                <?= e($opcion['nombre'] . ' (' . ($opcion['area'] ?? 'sin área') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="ui-boton ui-boton-chico" type="submit">Ver</button>
            </form>
        </section>

        <div class="ui-columnas ui-columnas-anchas">
            <!-- Horario actual -->
            <section class="ui-tarjeta">
                <div class="ui-tarjeta-cabecera">
                    <h2>Horario de <?= e($medico['nombre']) ?></h2>
                    <span class="ui-insignia"><?= $turnos_semana ?> turnos por semana</span>
                </div>

                <?php if ($bloques === []): ?>
                    <div class="ui-vacio">
                        <span class="ui-vacio-icono"><?= $icono_panel('i-reloj') ?></span>
                        <strong>Sin horario</strong>
                        <p>Los pacientes no pueden reservar con este médico. Agrega un horario.</p>
                    </div>
                <?php else: ?>
                    <div class="ui-tabla-contenedor">
                        <table class="ui-tabla ui-tabla-apilable" role="table">
                            <caption class="solo-lectores">Horario de <?= e($medico['nombre']) ?></caption>
                            <thead role="rowgroup">
                                <tr role="row">
                                    <th scope="col" role="columnheader">Día</th>
                                    <th scope="col" role="columnheader">Horario</th>
                                    <th scope="col" role="columnheader">Turnos</th>
                                    <th scope="col" role="columnheader"><span class="solo-lectores">Acción</span></th>
                                </tr>
                            </thead>
                            <tbody role="rowgroup">
                                <?php foreach ($bloques as $bloque): ?>
                                    <tr role="row">
                                        <td class="ui-celda-fuerte" role="cell" data-etiqueta="Día"><?= e($dias[(int) $bloque['dia_semana']]) ?></td>
                                        <td role="cell" data-etiqueta="Horario">
                                            <?= e(substr($bloque['hora_inicio'], 0, 5)) ?> –
                                            <?= e(substr($bloque['hora_fin'], 0, 5)) ?>
                                        </td>
                                        <td role="cell" data-etiqueta="Turnos">
                                            <span class="ui-celda-valor">
                                                <?= count(turnos_de_bloque($bloque['hora_inicio'], $bloque['hora_fin'], (int) $bloque['duracion_min'])) ?>
                                                <small>de <?= (int) $bloque['duracion_min'] ?> min</small>
                                            </span>
                                        </td>
                                        <td role="cell" data-etiqueta="Acción">
                                            <form method="post"
                                                  action="<?= e(url('/panel/horarios.php?medico=' . (int) $medico['id'])) ?>" data-cargando>
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="horario_id" value="<?= (int) $bloque['id'] ?>">
                                                <button class="ui-boton ui-boton-peligro ui-boton-chico" type="submit"
                                                        data-texto-cargando="Quitando…">Quitar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="ui-nota">Quitar un horario no borra las citas ya reservadas.</p>
                <?php endif; ?>
            </section>

            <!-- Agregar un bloque -->
            <section class="ui-tarjeta">
                <h2>Agregar horario</h2>
                <form method="post"
                      action="<?= e(url('/panel/horarios.php?medico=' . (int) $medico['id'])) ?>" data-cargando>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="agregar">

                    <div class="ui-rejilla">
                        <div class="ui-campo">
                            <label for="dia_semana">Día</label>
                            <select class="ui-entrada" id="dia_semana" name="dia_semana" required>
                                <?php foreach ($dias as $numero => $nombre): ?>
                                    <option value="<?= $numero ?>" <?= $valores['dia_semana'] === (string) $numero ? 'selected' : '' ?>>
                                        <?= e($nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ui-campo">
                            <label for="duracion_min">Duración de cada turno</label>
                            <select class="ui-entrada" id="duracion_min" name="duracion_min" required>
                                <?php foreach (DURACIONES_TURNO as $minutos): ?>
                                    <option value="<?= $minutos ?>" <?= $valores['duracion_min'] === (string) $minutos ? 'selected' : '' ?>>
                                        <?= $minutos ?> minutos
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ui-campo">
                            <label for="hora_inicio">Desde</label>
                            <input class="ui-entrada" type="time" id="hora_inicio" name="hora_inicio" required
                                   value="<?= e($valores['hora_inicio']) ?>">
                        </div>

                        <div class="ui-campo">
                            <label for="hora_fin">Hasta</label>
                            <input class="ui-entrada" type="time" id="hora_fin" name="hora_fin" required
                                   value="<?= e($valores['hora_fin']) ?>">
                        </div>
                    </div>

                    <div class="ui-acciones">
                        <button class="ui-boton" type="submit" data-texto-cargando="Agregando…">
                            <?= $icono_panel('i-agregar') ?>
                            Agregar horario
                        </button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
