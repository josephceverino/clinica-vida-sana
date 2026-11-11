<?php
// Página "Nosotros" (zona pública).
// Es solo contenido (HTML), no usa base de datos.
// Los textos son ficticios: la clínica es una simulación académica.
$titulo_pagina = 'Nosotros';
require __DIR__ . '/../app/layout/header.php';
?>

<!-- 1. Quiénes somos -->
<section class="seccion">
    <p class="etiqueta">Nosotros</p>
    <h1>Una clínica pensada para las personas</h1>
    <p class="portada-bajada">
        En Clínica Vida Sana creemos que una buena atención empieza por
        escuchar. Somos un equipo de médicos, enfermeras y personal de
        apoyo que trabaja para que te sientas acompañado en cada visita.
    </p>
</section>

<!-- 2. Misión y visión -->
<section class="seccion">
    <div class="rejilla-2">
        <article class="servicio">
            <h3>Nuestra misión</h3>
            <p>
                Brindar atención médica cercana, clara y de calidad,
                cuidando la salud de las familias en cada etapa de la vida.
            </p>
        </article>
        <article class="servicio">
            <h3>Nuestra visión</h3>
            <p>
                Ser la clínica de confianza de nuestra comunidad, donde
                cada paciente se sienta escuchado y bien atendido.
            </p>
        </article>
    </div>
</section>

<!-- 3. Valores -->
<section class="seccion">
    <h2>Lo que nos guía</h2>
    <div class="rejilla-3">
        <article class="punto">
            <span class="punto-numero">01</span>
            <h3>Respeto</h3>
            <p>Tratamos a cada persona con amabilidad y sin prisas.</p>
        </article>
        <article class="punto">
            <span class="punto-numero">02</span>
            <h3>Confidencialidad</h3>
            <p>Tus datos y tu historial se cuidan con la máxima reserva.</p>
        </article>
        <article class="punto">
            <span class="punto-numero">03</span>
            <h3>Mejora continua</h3>
            <p>Escuchamos tus opiniones para atenderte mejor cada día.</p>
        </article>
    </div>
</section>

<!-- 4. Llamado final -->
<section class="llamado">
    <h2>Queremos cuidar de ti</h2>
    <p>Pide tu cita y conoce nuestra forma de atender.</p>
    <a class="boton boton-claro" href="<?= e(url('/solicitar-cita.php')) ?>">Solicitar una cita</a>
</section>

<?php require __DIR__ . '/../app/layout/footer.php'; ?>
