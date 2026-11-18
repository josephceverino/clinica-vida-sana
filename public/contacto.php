<?php
// Página "Contacto" (zona pública).
// Solo muestra información (HTML). No tiene formulario: para pedir una
// cita está el Portal del paciente.
// Todos los datos son ficticios.
$titulo_pagina = 'Contacto';
require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- 1. Introducción -->
<section class="pub-encabezado aparecer">
    <p class="pub-etiqueta">Contacto</p>
    <h1>Estamos para ayudarte</h1>
    <p class="pub-bajada">
        Llámanos, escríbenos o visítanos. Con gusto resolvemos tus dudas
        y te orientamos para que llegues al área correcta.
    </p>
</section>

<!-- 2. Datos de contacto y horarios -->
<section class="pub-seccion pub-contacto aparecer">
    <div class="pub-contacto-datos">
        <h2>Cómo encontrarnos</h2>
        <ul class="pub-lista-datos">
            <li>
                <span class="pub-lista-icono"><?= $icono_publico('i-ubicacion') ?></span>
                <span>Dirección</span>
                <strong>Av. Salud 123, Ciudad Ejemplo</strong>
            </li>
            <li>
                <span class="pub-lista-icono"><?= $icono_publico('i-telefono') ?></span>
                <span>Teléfono</span>
                <strong>(01) 555-0100</strong>
            </li>
            <li>
                <span class="pub-lista-icono"><?= $icono_publico('i-alerta') ?></span>
                <span>Emergencias</span>
                <strong>(01) 555-0911</strong>
            </li>
            <li>
                <span class="pub-lista-icono"><?= $icono_publico('i-correo') ?></span>
                <span>Correo</span>
                <strong>contacto@clinica.lan</strong>
            </li>
        </ul>
    </div>

    <!-- Horarios: tarjeta oscura -->
    <aside class="pub-horario" aria-label="Horarios de atención">
        <h2>Horarios de atención</h2>
        <ul>
            <li><span>Lunes a viernes</span><strong>7:00 – 20:00</strong></li>
            <li><span>Sábados</span><strong>8:00 – 14:00</strong></li>
            <li><span>Emergencias</span><strong>24 horas</strong></li>
        </ul>
        <p>Domingos y feriados: solo emergencias.</p>
    </aside>
</section>

<!-- 3. Llamado final -->
<section class="pub-llamado aparecer">
    <div>
        <h2>¿Quieres pedir una cita?</h2>
        <p>Hazlo desde el Portal del paciente: eliges el horario y tu cita queda confirmada.</p>
    </div>
    <a class="pub-boton pub-boton-blanco" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
