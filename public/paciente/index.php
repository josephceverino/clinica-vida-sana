<?php
// Inicio del Portal del paciente.
// Por ahora solo muestra el estado de la cuenta; las citas y la historia
// clínica se agregan en los siguientes pasos.
require_once __DIR__ . '/../../app/core/auth.php';

// Solo pacientes. La cuenta puede estar pendiente de verificación.
requerir_paciente();

$titulo_pagina = 'Inicio';
$pagina_activa = 'index.php';

require __DIR__ . '/../../app/layout/paciente_header.php';

$primer_nombre = explode(' ', trim($usuario['nombre']))[0];
?>

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Hola, <?= e($primer_nombre) ?></h1>

    <?php if ($verificada): ?>
        <p class="aviso aviso-ok" role="status">
            Tu cuenta está <strong>verificada</strong>. Pronto podrás ver tus citas
            y tu historia clínica desde aquí.
        </p>
    <?php else: ?>
        <p class="aviso aviso-pendiente" role="status">
            Tu cuenta está <strong>pendiente de verificación</strong>. Acércate a
            admisión con tu documento de identidad para que comprueben tus datos.
            Hasta entonces no podrás ver tu historial ni pedir citas.
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
