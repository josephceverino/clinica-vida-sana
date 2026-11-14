<?php
// Cuentas de pacientes por verificar (admisión y admin).
// Admisión compara los datos de la cuenta con la ficha del paciente y decide
// si verifica la cuenta o la rechaza.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/cuentas.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

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
$filtro = (string) ($_GET['estado'] ?? 'pendientes');
if (!isset($filtros[$filtro])) {
    $filtro = 'pendientes';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Regla 4: todo formulario POST lleva token CSRF.
    if (!csrf_verificar()) {
        mensaje_guardar('La sesión expiró. Inténtalo otra vez.', 'error');
        redirigir('/panel/cuentas.php');
    }

    $accion = (string) ($_POST['accion'] ?? '');
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
?>

<header class="panel-titulo">
    <div>
        <h1>Cuentas por verificar</h1>
        <p>Compara los datos de la cuenta con la ficha del paciente antes de verificar.</p>
    </div>
</header>

<?php if ($mensaje !== null): ?>
    <p class="aviso aviso-<?= e($mensaje['tipo']) ?>" role="status"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<nav class="pestanas" aria-label="Filtro de cuentas">
    <?php foreach ($filtros as $clave => $texto): ?>
        <a href="<?= e(url('/panel/cuentas.php?estado=' . $clave)) ?>"
           class="<?= $clave === $filtro ? 'activo' : '' ?>"><?= e($texto) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (count($cuentas) === 0): ?>
    <section class="caja">
        <p class="vacio">No hay cuentas en esta lista.</p>
    </section>
<?php else: ?>
    <?php foreach ($cuentas as $cuenta): ?>
        <article class="caja cuenta">
            <div class="cuenta-comparacion">

                <div>
                    <h2>Lo que escribió al registrarse</h2>
                    <dl class="datos">
                        <dt>Nombre</dt>
                        <dd><?= e($cuenta['nombre_cuenta']) ?></dd>
                        <dt>Correo</dt>
                        <dd><?= e($cuenta['correo']) ?></dd>
                        <dt>Registro</dt>
                        <dd><?= e(date('d/m/Y H:i', strtotime($cuenta['creado_en']))) ?></dd>
                    </dl>
                </div>

                <div>
                    <h2>Ficha del paciente</h2>
                    <dl class="datos">
                        <dt>Documento</dt>
                        <dd><?= e($cuenta['tipo_documento']) ?> <?= e($cuenta['numero_documento']) ?></dd>
                        <dt>Nombre en la ficha</dt>
                        <dd><?= e($cuenta['nombres']) ?> <?= e($cuenta['apellidos']) ?></dd>
                        <dt>Teléfono</dt>
                        <dd><?= e($cuenta['telefono'] ?? '—') ?></dd>
                        <dt>Citas registradas</dt>
                        <dd><?= (int) $cuenta['citas'] ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($cuenta['ficha_previa']): ?>
                <p class="aviso aviso-pendiente">
                    <strong>Ficha de la clínica:</strong> este documento ya tenía ficha antes
                    de la cuenta. Comprueba con el documento original antes de verificar.
                </p>
            <?php endif; ?>

            <?php if (!$cuenta['nombre_coincide']): ?>
                <p class="aviso aviso-error">
                    <strong>Los nombres no coinciden</strong> con la ficha. Verifica solo si
                    la persona demuestra que es quien dice ser.
                </p>
            <?php endif; ?>

            <div class="cuenta-pie">
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

                <div class="cuenta-acciones">
                    <?php if ($filtro !== 'verificadas'): ?>
                        <form method="post" action="<?= e(url('/panel/cuentas.php?estado=' . $filtro)) ?>">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="verificar">
                            <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                            <button type="submit" class="boton boton-chico">Verificar</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($filtro !== 'rechazadas'): ?>
                        <form method="post" action="<?= e(url('/panel/cuentas.php?estado=' . $filtro)) ?>">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="rechazar">
                            <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                            <button type="submit" class="boton boton-claro boton-chico">Rechazar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
