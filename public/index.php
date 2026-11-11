<?php
// Página de inicio (zona pública).
$titulo_pagina = 'Inicio';
require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- 1. Bienvenida -->
<section class="portada">
    <div class="portada-texto">
        <p class="etiqueta">Clínica Vida Sana</p>
        <h1>Tu salud es lo primero.</h1>
        <p class="portada-bajada">
            Cuidamos de ti y de tu familia con atención cercana, médicos
            que te escuchan y turnos que se adaptan a tu día.
        </p>
        <div class="portada-botones">
            <a class="boton" href="<?= e(url('/solicitar-cita.php')) ?>">Solicitar una cita</a>
            <a class="boton boton-claro" href="<?= e(url('/servicios.php')) ?>">Ver servicios</a>
        </div>
    </div>

    <!-- Tarjeta de horarios (datos ficticios) -->
    <aside class="tarjeta-horario" aria-label="Horarios de atención">
        <h2>Horarios de atención</h2>
        <ul>
            <li><span>Lunes a viernes</span><strong>7:00 – 20:00</strong></li>
            <li><span>Sábados</span><strong>8:00 – 14:00</strong></li>
            <li><span>Emergencias</span><strong>24 horas</strong></li>
        </ul>
        <p class="tarjeta-nota">Teléfono: (01) 555-0100</p>
    </aside>
</section>

<!-- 2. Por qué elegirnos -->
<section class="seccion">
    <h2>Por qué elegirnos</h2>
    <div class="rejilla-3">
        <article class="punto">
            <span class="punto-numero">01</span>
            <h3>Atención cercana</h3>
            <p>Te llamamos por tu nombre. Dedicamos el tiempo que haga falta a escucharte.</p>
        </article>
        <article class="punto">
            <span class="punto-numero">02</span>
            <h3>Todo en un solo lugar</h3>
            <p>Consulta, laboratorio y seguimiento bajo el mismo techo, sin vueltas.</p>
        </article>
        <article class="punto">
            <span class="punto-numero">03</span>
            <h3>Citas fáciles</h3>
            <p>Pide tu cita desde la web y nuestro equipo de admisión te confirma.</p>
        </article>
    </div>
</section>

<!-- 3. Servicios -->
<section class="seccion">
    <h2>Nuestros servicios</h2>
    <div class="rejilla-3">
        <article class="servicio">
            <h3>Medicina General</h3>
            <p>Controles, chequeos y atención de molestias del día a día.</p>
        </article>
        <article class="servicio">
            <h3>Pediatría</h3>
            <p>Cuidado y seguimiento del crecimiento de los más pequeños.</p>
        </article>
        <article class="servicio">
            <h3>Laboratorio</h3>
            <p>Análisis clínicos con resultados claros y a tiempo.</p>
        </article>
    </div>
    <p><a href="<?= e(url('/servicios.php')) ?>">Ver todos los servicios &rarr;</a></p>
</section>

<!-- 4. Llamado final -->
<section class="llamado">
    <h2>¿Necesitas una consulta?</h2>
    <p>Déjanos tus datos y te contactamos para confirmar tu cita.</p>
    <a class="boton boton-claro" href="<?= e(url('/solicitar-cita.php')) ?>">Solicitar una cita</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
