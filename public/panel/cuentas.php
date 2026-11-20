<?php
// Cuentas de pacientes por verificar (admisión y admin).
// Admisión compara los datos de la cuenta con la ficha del paciente y decide
// si verifica la cuenta o la rechaza.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/cuentas.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

requerir_login();
requerir_rol(['admin', 'admision']);

$titulo_pagina = 'Cuentas por verificar';
$pagina_activa = 'cuentas.php';
$yo = usuario_actual();

$filtros = [
    'pendientes'  => 'Pendientes',
    'verificadas' => 'Verificadas',
    'rechazadas'  => 'Rechazadas',
];

// Pestaña elegida (solo valores de la lista; cualquier otra cosa = pendientes).
$filtro = campo_texto($_GET, 'estado', 'pendientes');
if (!isset($filtros[$filtro])) {
    $filtro = 'pendientes';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Regla 4: todo formulario POST lleva token CSRF.
    if (!csrf_verificar()) {
        mensaje_guardar('La sesión expiró. Inténtalo otra vez.', 'error');
        redirigir('/panel/cuentas.php');
    }

    $accion = campo_texto($_POST, 'accion');
    $cuenta_id = (int) ($_POST['id'] ?? 0);

    // Ficha del paciente de esa cuenta (para la auditoría).
    $paciente_de_cuenta = paciente_de_cuenta($cuenta_id);

    if ($accion === 'verificar' && verificar_cuenta($cuenta_id, $yo['id'])) {
        registrar_auditoria('verificar_cuenta', $paciente_de_cuenta, 'Cuenta ' . $cuenta_id);
        mensaje_guardar('Cuenta verificada. El paciente ya puede ver su información.');
    } elseif ($accion === 'rechazar' && rechazar_cuenta($cuenta_id)) {
        registrar_auditoria('rechazar_cuenta', $paciente_de_cuenta, 'Cuenta ' . $cuenta_id);
        mensaje_guardar('Cuenta rechazada: ya no puede iniciar sesión.');
    } else {
        mensaje_guardar('No se pudo completar la acción.', 'error');
    }

    redirigir('/panel/cuentas.php?estado=' . urlencode($filtro));
}

$cuentas = listar_cuentas_paciente($filtro);
$mensaje = mensaje_tomar();

require __DIR__ . '/../../app/layout/panel_header.php';

// Aviso de la página: "ok" se ve verde; "error", rojo (y se anuncia como alerta).
$clase_mensaje = $mensaje !== null && $mensaje['tipo'] === 'error' ? 'ui-aviso-error' : 'ui-aviso-exito';
?>

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Cuentas por verificar</h1>
            <p>Compara los datos de la cuenta con la ficha del paciente antes de verificar.</p>
        </div>
    </header>

    <?php if ($mensaje !== null): ?>
        <div class="ui-aviso <?= e($clase_mensaje) ?>" role="<?= $mensaje['tipo'] === 'error' ? 'alert' : 'status' ?>">
            <?= $icono_panel($mensaje['tipo'] === 'error' ? 'i-alerta' : 'i-visto') ?>
            <p><?= e($mensaje['texto']) ?></p>
        </div>
    <?php endif; ?>

    <nav class="ui-pestanas" aria-label="Filtro de cuentas">
        <?php foreach ($filtros as $clave => $texto): ?>
            <a href="<?= e(url('/panel/cuentas.php?estado=' . $clave)) ?>"
               <?= $clave === $filtro ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (count($cuentas) === 0): ?>
        <section class="ui-tarjeta">
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-escudo') ?></span>
                <strong>No hay cuentas en esta lista.</strong>
                <p>
                    <?= $filtro === 'pendientes'
                        ? 'Cuando un paciente se registre, su cuenta aparecerá aquí para revisarla.'
                        : 'Prueba con otra pestaña.' ?>
                </p>
            </div>
        </section>
    <?php else: ?>
        <?php foreach ($cuentas as $cuenta): ?>
            <article class="ui-tarjeta ui-cuenta" aria-label="Cuenta de <?= e($cuenta['nombre_cuenta']) ?>">
                <div class="ui-comparacion">
                    <div>
                        <h3>Lo que escribió al registrarse</h3>
                        <dl class="datos">
                            <div>
                                <dt>Nombre</dt>
                                <dd><?= e($cuenta['nombre_cuenta']) ?></dd>
                            </div>
                            <div>
                                <dt>Correo</dt>
                                <dd><?= e($cuenta['correo']) ?></dd>
                            </div>
                            <div>
                                <dt>Registro</dt>
                                <dd><?= e(date('d/m/Y H:i', strtotime($cuenta['creado_en']))) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3>Ficha del paciente</h3>
                        <dl class="datos">
                            <div>
                                <dt>Documento</dt>
                                <dd><?= e($cuenta['tipo_documento']) ?> <?= e($cuenta['numero_documento']) ?></dd>
                            </div>
                            <div>
                                <dt>Nombre en la ficha</dt>
                                <dd><?= e($cuenta['nombres']) ?> <?= e($cuenta['apellidos']) ?></dd>
                            </div>
                            <div>
                                <dt>Teléfono</dt>
                                <dd><?= e($cuenta['telefono'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt>Citas registradas</dt>
                                <dd><?= (int) $cuenta['citas'] ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <?php if ($cuenta['ficha_previa']): ?>
                    <div class="ui-aviso ui-aviso-advertencia">
                        <?= $icono_panel('i-info') ?>
                        <p>
                            <strong>Ficha de la clínica:</strong> este documento ya tenía ficha antes
                            de la cuenta. Comprueba con el documento original antes de verificar.
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!$cuenta['nombre_coincide']): ?>
                    <div class="ui-aviso ui-aviso-error">
                        <?= $icono_panel('i-alerta') ?>
                        <p>
                            <strong>Los nombres no coinciden</strong> con la ficha. Verifica solo si
                            la persona demuestra que es quien dice ser.
                        </p>
                    </div>
                <?php endif; ?>

                <div class="ui-pie">
                    <div class="ui-pie-estado">
                        <?php if ($filtro === 'verificadas'): ?>
                            <span class="estado estado-confirmada">verificada</span>
                            <small>
                                <?= e($cuenta['verificador'] ?? '—') ?>
                                <?= $cuenta['verificado_en'] !== null
                                    ? '· ' . e(date('d/m/Y', strtotime($cuenta['verificado_en']))) : '' ?>
                            </small>
                        <?php elseif ($filtro === 'rechazadas'): ?>
                            <span class="estado estado-cancelada">rechazada</span>
                        <?php else: ?>
                            <span class="estado estado-pendiente">pendiente</span>
                        <?php endif; ?>
                    </div>

                    <div class="ui-acciones-fila">
                        <?php if ($filtro !== 'verificadas'): ?>
                            <form method="post" action="<?= e(url('/panel/cuentas.php?estado=' . $filtro)) ?>" data-cargando>
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="verificar">
                                <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                <button type="submit" class="ui-boton ui-boton-chico" data-texto-cargando="Verificando…">
                                    <?= $icono_panel('i-visto') ?>
                                    Verificar
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($filtro !== 'rechazadas'): ?>
                            <form method="post" action="<?= e(url('/panel/cuentas.php?estado=' . $filtro)) ?>" data-cargando>
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                <button type="submit" class="ui-boton ui-boton-peligro ui-boton-chico" data-texto-cargando="Rechazando…">
                                    Rechazar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
