<?php

require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';

requerir_login();
requerir_rol(['admin', 'admision', 'medico']);

$titulo_pagina = 'Citas';
$usuario = usuario_actual();
$error = '';
$citas = [];

$puede_gestionar = in_array(
    $usuario['rol'],
    ['admin', 'admision'],
    true
);

$zona = new DateTimeZone('America/Lima');
$fecha = (new DateTimeImmutable('today', $zona))->format('Y-m-d');

if (isset($_GET['fecha'])) {
    if (!is_string($_GET['fecha'])) {
        $error = 'La fecha no es válida.';
    } else {
        $fecha = trim($_GET['fecha']);
    }
}

if ($error === '') {
    try {
        $fecha_validada = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $zona
        );

        if (
            !$fecha_validada
            || $fecha_validada->format('Y-m-d') !== $fecha
        ) {
            throw new InvalidArgumentException(
                'Selecciona una fecha válida.'
            );
        }

        if ($usuario['rol'] === 'medico') {
            // El médico solo consulta las citas de su propia sesión.
            $citas = buscar_citas_medico($usuario['id'], $fecha);
        } else {
            $citas = buscar_citas($fecha);
        }
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudieron consultar las citas.';
    }
}

// Sección del menú lateral que se marca como activa.
$pagina_activa = 'citas.php';
require __DIR__ . '/../../app/layout/panel_header.php';

// Fecha consultada en palabras, ej. "miércoles 18 de noviembre de 2026"
// (solo para mostrar; si la fecha no es válida se muestra tal cual).
$dias_semana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses_anio = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
               'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha_mostrar = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
$fecha_texto = $fecha_mostrar && $fecha_mostrar->format('Y-m-d') === $fecha
    ? $dias_semana[(int) $fecha_mostrar->format('w')] . ' ' . $fecha_mostrar->format('j')
      . ' de ' . $meses_anio[(int) $fecha_mostrar->format('n') - 1] . ' de ' . $fecha_mostrar->format('Y')
    : $fecha;
?>

<div class="ui-pagina">

    <a class="ui-migas" href="<?= e(url('/panel/index.php')) ?>">
        <?= $icono_panel('i-volver') ?>
        Volver al panel
    </a>

    <header class="ui-cabecera">
        <div>
            <h1>Citas</h1>
            <p>Citas del <?= e($fecha_texto) ?>.</p>
        </div>
        <?php if ($puede_gestionar): ?>
            <a class="ui-boton" href="<?= e(url('/panel/registrar-cita.php')) ?>">
                <?= $icono_panel('i-agregar') ?>
                Registrar cita
            </a>
        <?php endif; ?>
    </header>

    <?php if ($usuario['rol'] === 'medico'): ?>
        <div class="ui-aviso">
            <?= $icono_panel('i-info') ?>
            <p>Se muestran únicamente tus citas asignadas.</p>
        </div>
    <?php endif; ?>

    <!-- Filtro por fecha (GET: la fecha queda en la dirección) -->
    <section class="ui-tarjeta" aria-label="Elegir fecha">
        <form class="ui-buscador" method="get" action="<?= e(url('/panel/citas.php')) ?>">
            <div class="ui-campo">
                <label for="fecha">Fecha de las citas</label>
                <input
                    class="ui-entrada"
                    id="fecha"
                    name="fecha"
                    type="date"
                    value="<?= e($fecha) ?>"
                    required
                >
            </div>
            <button class="ui-boton" type="submit">Consultar</button>
        </form>
    </section>

    <section class="ui-tarjeta">
        <?php if ($error !== ''): ?>
            <div class="ui-aviso ui-aviso-error" role="alert">
                <?= $icono_panel('i-alerta') ?>
                <p><?= e($error) ?></p>
            </div>

        <?php elseif ($citas === []): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-calendario') ?></span>
                <strong>No hay citas para la fecha seleccionada.</strong>
                <p>Elige otro día en el filtro de arriba.</p>
                <?php if ($puede_gestionar): ?>
                    <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/registrar-cita.php')) ?>">
                        <?= $icono_panel('i-agregar') ?>
                        Registrar cita
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="ui-tarjeta-cabecera">
                <h2>Agenda del día</h2>
                <span class="ui-insignia">Resultados mostrados: <?= e(count($citas)) ?></span>
            </div>

            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable" role="table">
                    <caption class="solo-lectores">Citas del <?= e($fecha_texto) ?></caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Hora</th>
                            <th scope="col" role="columnheader">Paciente</th>
                            <th scope="col" role="columnheader">Área</th>
                            <th scope="col" role="columnheader">Motivo</th>
                            <th scope="col" role="columnheader">Estado</th>
                            <th scope="col" role="columnheader">Acción</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($citas as $cita): ?>
                            <tr role="row">
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Hora">
                                    <span class="ui-celda-valor">
                                        <?= e(substr((string) $cita['hora'], 0, 5)) ?>
                                        <small>Cita <?= e($cita['id']) ?></small>
                                    </span>
                                </td>
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Paciente">
                                    <span class="ui-celda-valor">
                                        <?= e($cita['nombres']) ?> <?= e($cita['apellidos']) ?>
                                        <small><?= e($cita['tipo_documento']) ?> <?= e($cita['numero_documento']) ?></small>
                                    </span>
                                </td>
                                <td role="cell" data-etiqueta="Área"><?= e($cita['area']) ?></td>
                                <td role="cell" data-etiqueta="Motivo"><?= e($cita['motivo'] ?? 'Sin motivo registrado') ?></td>
                                <td role="cell" data-etiqueta="Estado">
                                    <span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span>
                                </td>
                                <td role="cell" data-etiqueta="Acción">
                                    <?php if ($puede_gestionar): ?>
                                        <a class="ui-boton ui-boton-secundario"
                                           href="<?= e(url('/panel/gestionar-cita.php?id=' . $cita['id'])) ?>">
                                            Gestionar cita
                                        </a>
                                    <?php elseif ($usuario['rol'] === 'medico' && $cita['estado'] === 'confirmada'): ?>
                                        <a class="ui-boton"
                                           href="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">
                                            Atender
                                        </a>
                                    <?php elseif ($usuario['rol'] === 'medico' && $cita['estado'] === 'atendida'): ?>
                                        <a class="ui-boton ui-boton-secundario"
                                           href="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">
                                            Ver atención
                                        </a>
                                    <?php else: ?>
                                        <span aria-hidden="true">—</span><span class="solo-lectores">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($puede_gestionar): ?>
                <p class="ui-nota">Se muestran hasta 100 citas por consulta.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
