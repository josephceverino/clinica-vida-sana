<?php
// Pedir cita. Dos formas:
//   1. RESERVAR un horario libre: el paciente elige área y día, ve los huecos
//      libres de cada médico, toca uno y la cita queda confirmada al momento.
//   2. ENVIAR UNA SOLICITUD (si no encuentra horario): admisión la revisa y
//      la programa (como antes).
// Todo funciona sin JavaScript: el paso 1 es un formulario GET que recarga
// la página con los horarios de ese día.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/core/auditoria.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';
require_once __DIR__ . '/../../app/modelos/horarios.php';

// Solo pacientes con la cuenta verificada por admisión.
requerir_paciente_verificado();

$titulo_pagina = 'Pedir cita';
$pagina_activa = 'pedir-cita.php';
$error = '';

// El paciente sale de la SESIÓN, nunca del formulario.
$yo = usuario_actual();
$paciente_id = (int) $yo['paciente_id'];

// Áreas que atienden pacientes (no Admisión ni Sistemas).
$areas = db()->query(
    'SELECT id, nombre FROM areas WHERE publica = 1 ORDER BY nombre'
)->fetchAll();
$ids_areas = array_map('strval', array_column($areas, 'id'));

$hoy = date('Y-m-d');
$ultimo_dia = date('Y-m-d', strtotime('+' . DIAS_RESERVA_MAXIMO . ' days'));

// --- Paso 1: área y día elegidos (vienen por GET) --------------------------
$area_id = is_string($_GET['area_id'] ?? null) ? $_GET['area_id'] : '';
$fecha = is_string($_GET['fecha'] ?? null) ? $_GET['fecha'] : $hoy;

// En un POST, el área y el día vienen ocultos en el formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $area_id = is_string($_POST['area_id'] ?? null) ? $_POST['area_id'] : $area_id;
    $fecha = is_string($_POST['fecha'] ?? null) ? $_POST['fecha'] : $fecha;
}

// La fecha TAL COMO LLEGÓ. Para reservar se usa esta, no la corregida de
// abajo: si ya pasó (ej. el paciente reservó justo después de medianoche),
// debe dar un error, no reservar en silencio otro día.
$fecha_enviada = $fecha;

// Validar: el área debe estar en la lista y el día en el rango permitido.
if (!in_array($area_id, $ids_areas, true)) {
    $area_id = '';
}
$fecha_valida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
if (!$fecha_valida || $fecha_valida->format('Y-m-d') !== $fecha || $fecha < $hoy || $fecha > $ultimo_dia) {
    $fecha = $hoy;
}

// Valores de los formularios (para no borrarlos si hay un error).
$motivo = '';
$hueco_elegido = '';
$solicitud = ['area_id' => '', 'fecha_preferida' => '', 'mensaje' => ''];

// --- POST: reservar un hueco o enviar una solicitud -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';

    try {
        // Regla 4: token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException('La sesión del formulario venció. Recarga la página.');
        }

        if ($accion === 'reservar') {
            $motivo = is_string($_POST['motivo'] ?? null) ? trim($_POST['motivo']) : '';
            $hueco_elegido = is_string($_POST['hueco'] ?? null) ? $_POST['hueco'] : '';

            $cita_id = reservar_hueco($paciente_id, (int) $yo['id'], [
                'area_id' => $area_id,
                'fecha'   => $fecha_enviada,
                'hueco'   => $hueco_elegido,
                'motivo'  => $motivo,
            ]);
            registrar_auditoria('reservar_cita', $paciente_id, 'Cita ' . $cita_id);

            mensaje_guardar('¡Listo! Tu cita quedó confirmada. Te esperamos.');
            redirigir('/paciente/citas.php');
        } elseif ($accion === 'solicitud') {
            foreach ($solicitud as $campo => $vacio) {
                $solicitud[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
            }
            registrar_solicitud_paciente($paciente_id, $solicitud);

            mensaje_guardar('Solicitud enviada. Admisión la revisará y te confirmará la cita.');
            redirigir('/paciente/citas.php');
        } else {
            throw new InvalidArgumentException('Acción no válida.');
        }
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        // Regla 9: al usuario un mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        $error = 'No se pudo completar. Inténtalo más tarde.';
    }
}

// --- Paso 2: huecos libres del área en ese día ------------------------------
$opciones = [];
$dias_con_huecos = [];
if ($area_id !== '') {
    $opciones = huecos_por_area((int) $area_id, $fecha);

    // Si ese día no hay nada, se sugieren los próximos días con horarios.
    if ($opciones === []) {
        for ($i = 1; $i <= 14 && count($dias_con_huecos) < 4; $i++) {
            $otro = date('Y-m-d', strtotime($fecha . ' +' . $i . ' days'));
            if ($otro > $ultimo_dia) {
                break;
            }
            $total = array_sum(array_map('count', array_column(huecos_por_area((int) $area_id, $otro), 'huecos')));
            if ($total > 0) {
                $dias_con_huecos[$otro] = $total;
            }
        }
    }
}

$nombre_area = '';
foreach ($areas as $area) {
    if ((string) $area['id'] === $area_id) {
        $nombre_area = $area['nombre'];
    }
}

/**
 * Dirección de esta página para otra fecha (mantiene el área).
 */
function enlace_dia(string $area_id, string $fecha): string
{
    return url('/paciente/pedir-cita.php?area_id=' . $area_id . '&fecha=' . $fecha);
}

$dia_anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
$dia_siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));

require __DIR__ . '/../../app/layout/paciente_header.php';
?>

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Pedir una cita</h1>
            <p>Elige el área y el día, y reserva un horario libre. Tu cita queda confirmada al momento.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <!-- Paso 1: área y día (GET: solo muestra horarios, no guarda nada) -->
    <section class="ui-tarjeta" aria-labelledby="paso-1">
        <h2 id="paso-1"><span class="portal-paso">1</span> Elige el área y el día</h2>
        <form class="ui-buscador portal-buscador" method="get"
              action="<?= e(url('/paciente/pedir-cita.php')) ?>">
            <div class="ui-campo">
                <label for="area_id">Área</label>
                <select class="ui-entrada" id="area_id" name="area_id" required>
                    <option value="">Selecciona un área</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?= (int) $area['id'] ?>" <?= $area_id === (string) $area['id'] ? 'selected' : '' ?>>
                            <?= e($area['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ui-campo">
                <label for="fecha">Día</label>
                <input class="ui-entrada" type="date" id="fecha" name="fecha" required
                       min="<?= e($hoy) ?>" max="<?= e($ultimo_dia) ?>" value="<?= e($fecha) ?>">
            </div>
            <button class="ui-boton" type="submit">
                <?= $icono_panel('i-buscar') ?>
                Ver horarios
            </button>
        </form>
    </section>

    <?php if ($area_id !== ''): ?>
        <!-- Paso 2: horarios libres -->
        <section class="ui-tarjeta" aria-labelledby="paso-2">
            <div class="ui-tarjeta-cabecera">
                <h2 id="paso-2"><span class="portal-paso">2</span> <?= e($nombre_area) ?> &middot; <?= e(fecha_relativa($fecha)) ?></h2>
                <nav class="ui-grupo" aria-label="Cambiar de día">
                    <?php if ($fecha > $hoy): ?>
                        <a class="ui-boton ui-boton-secundario ui-boton-chico" href="<?= e(enlace_dia($area_id, $dia_anterior)) ?>">
                            <span aria-hidden="true">&larr;</span> Día anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($dia_siguiente <= $ultimo_dia): ?>
                        <a class="ui-boton ui-boton-secundario ui-boton-chico" href="<?= e(enlace_dia($area_id, $dia_siguiente)) ?>">
                            Día siguiente <span aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <?php if ($opciones === []): ?>
                <div class="ui-vacio">
                    <span class="ui-vacio-icono"><?= $icono_panel('i-reloj') ?></span>
                    <strong>No hay horarios libres este día.</strong>
                    <?php if ($dias_con_huecos !== []): ?>
                        <p>Próximos días con horarios:</p>
                        <div class="portal-dias">
                            <?php foreach ($dias_con_huecos as $dia => $total): ?>
                                <a class="portal-dia" href="<?= e(enlace_dia($area_id, $dia)) ?>">
                                    <strong><?= e(fecha_relativa($dia)) ?></strong>
                                    <span><?= $total ?> horario<?= $total === 1 ? '' : 's' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Prueba con otro día o envía una solicitud a admisión (abajo).</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- data-cargando: al reservar, el botón muestra "Reservando…" y evita el doble envío -->
                <form method="post" action="<?= e(url('/paciente/pedir-cita.php')) ?>" data-cargando>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="reservar">
                    <input type="hidden" name="area_id" value="<?= e($area_id) ?>">
                    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">

                    <?php foreach ($opciones as $opcion): ?>
                        <fieldset class="portal-medico">
                            <legend><?= e($opcion['medico']) ?></legend>
                            <!-- Cada hora es un botón de opción con forma de "pastilla" -->
                            <div class="portal-horas">
                                <?php foreach ($opcion['huecos'] as $hora): ?>
                                    <?php $valor = $opcion['medico_id'] . '|' . $hora; ?>
                                    <label class="portal-hora">
                                        <input type="radio" name="hueco" value="<?= e($valor) ?>" required
                                            <?= $hueco_elegido === $valor ? 'checked' : '' ?>>
                                        <span><?= e($hora) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>

                    <div class="ui-campo portal-motivo">
                        <label for="motivo"><span class="portal-paso">3</span> Motivo de la consulta</label>
                        <textarea class="ui-entrada" id="motivo" name="motivo" rows="3" minlength="5" maxlength="255"
                                  aria-describedby="ayuda-motivo" required><?= e($motivo) ?></textarea>
                        <span class="ui-ayuda" id="ayuda-motivo">Entre 5 y 255 caracteres.</span>
                    </div>

                    <div class="ui-acciones">
                        <button class="ui-boton" type="submit" data-texto-cargando="Reservando…">
                            <?= $icono_panel('i-visto') ?>
                            Reservar y confirmar cita
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Plan B: enviar una solicitud a admisión -->
    <details class="ui-tarjeta portal-otra-opcion" <?= ($_POST['accion'] ?? '') === 'solicitud' ? 'open' : '' ?>>
        <summary>¿No encuentras un horario que te sirva? Envía una solicitud a admisión</summary>

        <form method="post" action="<?= e(url('/paciente/pedir-cita.php')) ?>" data-cargando>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="solicitud">

            <div class="ui-rejilla">
                <div class="ui-campo">
                    <label for="solicitud_area">Área</label>
                    <select class="ui-entrada" id="solicitud_area" name="area_id" required>
                        <option value="">Selecciona un área</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?= (int) $area['id'] ?>"
                                <?= $solicitud['area_id'] === (string) $area['id'] ? 'selected' : '' ?>>
                                <?= e($area['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ui-campo">
                    <label for="fecha_preferida">Fecha preferida (opcional)</label>
                    <input class="ui-entrada" type="date" id="fecha_preferida" name="fecha_preferida"
                           min="<?= e($hoy) ?>" value="<?= e($solicitud['fecha_preferida']) ?>">
                </div>

                <div class="ui-campo ui-campo-ancho">
                    <label for="mensaje">Motivo y disponibilidad</label>
                    <textarea class="ui-entrada" id="mensaje" name="mensaje" rows="3" minlength="5" maxlength="500"
                              placeholder="Ej.: dolor de cabeza; puedo cualquier tarde después de las 4."
                              aria-describedby="ayuda-mensaje" required><?= e($solicitud['mensaje']) ?></textarea>
                    <span class="ui-ayuda" id="ayuda-mensaje">Entre 5 y 500 caracteres.</span>
                </div>
            </div>

            <div class="ui-acciones">
                <button class="ui-boton ui-boton-secundario" type="submit" data-texto-cargando="Enviando…">Enviar solicitud</button>
            </div>
        </form>
    </details>
</div>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
