<?php
// Página "Contacto" (zona pública).
// Solo muestra información (HTML). No tiene formulario: para pedir una
// cita está el Portal del paciente.
// Todos los datos son ficticios.
$titulo_pagina = 'Contacto';
require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- 1. Introducción -->
<section class="seccion">
    <p class="etiqueta">Contacto</p>
    <h1>Estamos para ayudarte</h1>
    <p class="portada-bajada">
        Llámanos, escríbenos o visítanos. Con gusto resolvemos tus dudas
        y te orientamos para que llegues al área correcta.
    </p>
</section>

<!-- 2. Datos de contacto y horarios -->
<section class="seccion">
    <div class="rejilla-2">
        <article class="servicio">
            <h3>Cómo encontrarnos</h3>
            <ul class="lista-datos">
                <li>
                    <span>Dirección</span>
                    <strong>Av. Salud 123, Ciudad Ejemplo</strong>
                </li>
                <li>
                    <span>Teléfono</span>
                    <strong>(01) 555-0100</strong>
                </li>
                <li>
                    <span>Emergencias</span>
                    <strong>(01) 555-0911</strong>
                </li>
                <li>
                    <span>Correo</span>
                    <strong>contacto@clinica.lan</strong>
                </li>
            </ul>
        </article>

        <!-- Misma tarjeta de horarios que la portada -->
        <aside class="tarjeta-horario" aria-label="Horarios de atención">
            <h2>Horarios de atención</h2>
            <ul>
                <li><span>Lunes a viernes</span><strong>7:00 – 20:00</strong></li>
                <li><span>Sábados</span><strong>8:00 – 14:00</strong></li>
                <li><span>Emergencias</span><strong>24 horas</strong></li>
            </ul>
            <p class="tarjeta-nota">Domingos y feriados: solo emergencias.</p>
        </aside>
    </div>
</section>

<!-- 3. Llamado final -->
<section class="llamado">
    <h2>¿Quieres pedir una cita?</h2>
    <p>Hazlo desde el Portal del paciente: admisión la confirmará.</p>
    <a class="boton boton-claro" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
