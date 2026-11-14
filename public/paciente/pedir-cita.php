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
                'fecha'   => $fecha,
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

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Pedir una cita</h1>
    <p>Elige el área y el día, y reserva un horario libre. Tu cita queda confirmada al momento.</p>

    <?php if ($error !== ''): ?>
        <p class="aviso aviso-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
</section>

<!-- Paso 1: área y día (GET: solo muestra horarios, no guarda nada) -->
<form class="tarjeta-paciente buscador-horarios" method="get"
      action="<?= e(url('/paciente/pedir-cita.php')) ?>">
    <div class="campo">
        <label for="area_id">1. Área</label>
        <select id="area_id" name="area_id" required>
            <option value="">Selecciona un área</option>
            <?php foreach ($areas as $area): ?>
                <option value="<?= (int) $area['id'] ?>" <?= $area_id === (string) $area['id'] ? 'selected' : '' ?>>
                    <?= e($area['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="fecha">2. Día</label>
        <input type="date" id="fecha" name="fecha" required
               min="<?= e($hoy) ?>" max="<?= e($ultimo_dia) ?>" value="<?= e($fecha) ?>">
    </div>
    <button class="boton" type="submit">Ver horarios</button>
</form>

<?php if ($area_id !== ''): ?>
    <!-- Paso 2: horarios libres -->
    <section class="seccion">
        <div class="horarios-cabecera">
            <h2><?= e($nombre_area) ?> &middot; <?= e(fecha_relativa($fecha)) ?></h2>
            <nav class="horarios-dias" aria-label="Cambiar de día">
                <?php if ($fecha > $hoy): ?>
                    <a href="<?= e(enlace_dia($area_id, $dia_anterior)) ?>">&larr; Día anterior</a>
                <?php endif; ?>
                <?php if ($dia_siguiente <= $ultimo_dia): ?>
                    <a href="<?= e(enlace_dia($area_id, $dia_siguiente)) ?>">Día siguiente &rarr;</a>
                <?php endif; ?>
            </nav>
        </div>

        <?php if ($opciones === []): ?>
            <div class="tarjeta-paciente">
                <p>No hay horarios libres este día.</p>
                <?php if ($dias_con_huecos !== []): ?>
                    <p><strong>Próximos días con horarios:</strong></p>
                    <div class="dias-sugeridos">
                        <?php foreach ($dias_con_huecos as $dia => $total): ?>
                            <a class="dia-sugerido" href="<?= e(enlace_dia($area_id, $dia)) ?>">
                                <strong><?= e(fecha_relativa($dia)) ?></strong>
                                <span><?= $total ?> horario<?= $total === 1 ? '' : 's' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/paciente/pedir-cita.php')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="reservar">
                <input type="hidden" name="area_id" value="<?= e($area_id) ?>">
                <input type="hidden" name="fecha" value="<?= e($fecha) ?>">

                <?php foreach ($opciones as $opcion): ?>
                    <fieldset class="tarjeta-paciente horarios-medico">
                        <legend><?= e($opcion['medico']) ?></legend>
                        <!-- Cada hora es un botón de opción con forma de "pastilla" -->
                        <div class="horas">
                            <?php foreach ($opcion['huecos'] as $hora): ?>
                                <?php $valor = $opcion['medico_id'] . '|' . $hora; ?>
                                <label class="hora">
                                    <input type="radio" name="hueco" value="<?= e($valor) ?>" required
                                        <?= $hueco_elegido === $valor ? 'checked' : '' ?>>
                                    <span><?= e($hora) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>

                <div class="tarjeta-paciente">
                    <div class="campo">
                        <label for="motivo">3. Motivo de la consulta</label>
                        <textarea id="motivo" name="motivo" rows="3" minlength="5" maxlength="255"
                                  required><?= e($motivo) ?></textarea>
                    </div>
                    <button class="boton" type="submit">Reservar y confirmar cita</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Plan B: enviar una solicitud a admisión -->
<details class="seccion tarjeta-paciente otra-opcion" <?= ($_POST['accion'] ?? '') === 'solicitud' ? 'open' : '' ?>>
    <summary>¿No encuentras un horario que te sirva? Envía una solicitud a admisión</summary>

    <form class="formulario" method="post" action="<?= e(url('/paciente/pedir-cita.php')) ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="solicitud">

        <div class="campo">
            <label for="solicitud_area">Área</label>
            <select id="solicitud_area" name="area_id" required>
                <option value="">Selecciona un área</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= (int) $area['id'] ?>"
                        <?= $solicitud['area_id'] === (string) $area['id'] ? 'selected' : '' ?>>
                        <?= e($area['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="fecha_preferida">Fecha preferida (opcional)</label>
            <input type="date" id="fecha_preferida" name="fecha_preferida"
                   min="<?= e($hoy) ?>" value="<?= e($solicitud['fecha_preferida']) ?>">
        </div>

        <div class="campo">
            <label for="mensaje">Motivo y disponibilidad</label>
            <textarea id="mensaje" name="mensaje" rows="3" minlength="5" maxlength="500"
                      placeholder="Ej.: dolor de cabeza; puedo cualquier tarde después de las 4."
                      required><?= e($solicitud['mensaje']) ?></textarea>
        </div>

        <button class="boton boton-claro" type="submit">Enviar solicitud</button>
    </form>
</details>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
