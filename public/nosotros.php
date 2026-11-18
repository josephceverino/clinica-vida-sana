<?php
// Página "Nosotros" (zona pública).
// Es solo contenido (HTML), no usa base de datos.
// Los textos son ficticios: la clínica es una simulación académica.
$titulo_pagina = 'Nosotros';
require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- 1. Quiénes somos -->
<section class="pub-encabezado aparecer">
    <p class="pub-etiqueta">Nosotros</p>
    <h1>Una clínica pensada para las personas</h1>
    <p class="pub-bajada">
        En Clínica Vida Sana creemos que una buena atención empieza por
        escuchar. Somos un equipo de médicos, enfermeras y personal de
        apoyo que trabaja para que te sientas acompañado en cada visita.
    </p>
</section>

<!-- 2. Misión y visión: un bloque partido en dos tonos -->
<section class="pub-seccion aparecer">
    <div class="pub-mision">
        <article class="pub-mision-mision">
            <h2>Nuestra misión</h2>
            <p>
                Brindar atención médica cercana, clara y de calidad,
                cuidando la salud de las familias en cada etapa de la vida.
            </p>
        </article>
        <article class="pub-mision-vision">
            <h2>Nuestra visión</h2>
            <p>
                Ser la clínica de confianza de nuestra comunidad, donde
                cada paciente se sienta escuchado y bien atendido.
            </p>
        </article>
    </div>
</section>

<!-- 3. Valores: título a la izquierda, lista numerada a la derecha -->
<section class="pub-seccion pub-dividida aparecer">
    <div class="pub-dividida-titulo">
        <p class="pub-etiqueta">Valores</p>
        <h2>Lo que nos guía</h2>
    </div>
    <ol class="pub-razones">
        <li>
            <h3>Respeto</h3>
            <p>Tratamos a cada persona con amabilidad y sin prisas.</p>
        </li>
        <li>
            <h3>Confidencialidad</h3>
            <p>Tus datos y tu historial se cuidan con la máxima reserva.</p>
        </li>
        <li>
            <h3>Mejora continua</h3>
            <p>Escuchamos tus opiniones para atenderte mejor cada día.</p>
        </li>
    </ol>
</section>

<!-- 4. Llamado final -->
<section class="pub-llamado aparecer">
    <div>
        <h2>Queremos cuidar de ti</h2>
        <p>Entra al Portal del paciente y pide tu cita.</p>
    </div>
    <a class="pub-boton pub-boton-blanco" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
