<?php
// Página de inicio (zona pública).
$titulo_pagina = 'Inicio';
// Carrusel, aviso emergente y animaciones al bajar (public/assets/js/portada.js).
$script_pagina = 'portada.js';
require __DIR__ . '/../app/layout/publico_header.php';

// Diapositivas del carrusel. Para cambiar una imagen por una foto, deja el
// archivo en public/assets/img/ y cambia aquí su nombre.
$diapositivas = [
    [
        'etiqueta' => 'Clínica Vida Sana',
        'titulo'   => 'Tu salud es lo primero.',
        'texto'    => 'Atención cercana, médicos que te escuchan y turnos que se adaptan a tu día.',
        'boton'    => ['Portal del paciente', '/login.php'],
        'imagen'   => 'slide-bienvenida.svg',
        'tono'     => 'tono-azul',
    ],
    [
        'etiqueta' => 'Pediatría',
        'titulo'   => 'Cuidamos su crecimiento.',
        'texto'    => 'Controles, vacunas y seguimiento para los más pequeños de la casa.',
        'boton'    => ['Ver servicios', '/servicios.php'],
        'imagen'   => 'slide-pediatria.svg',
        'tono'     => 'tono-verde',
    ],
    [
        'etiqueta' => 'Laboratorio',
        'titulo'   => 'Resultados claros y a tiempo.',
        'texto'    => 'Análisis clínicos en el mismo lugar donde te atiende tu médico.',
        'boton'    => ['Ver servicios', '/servicios.php'],
        'imagen'   => 'slide-laboratorio.svg',
        'tono'     => 'tono-oscuro',
    ],
    [
        'etiqueta' => 'Citas en línea',
        'titulo'   => 'Pide tu cita sin hacer cola.',
        'texto'    => 'Crea tu cuenta, elige el área y reserva un horario libre. Tu cita queda confirmada al momento.',
        'boton'    => ['Crear mi cuenta', '/registro.php'],
        'imagen'   => 'slide-citas.svg',
        'tono'     => 'tono-azul',
    ],
];
?>

<!-- 1. Carrusel de bienvenida -->
<!-- Sin JavaScript se ve la primera diapositiva y las demás debajo: nada se pierde. -->
<div class="pub-hero">
    <section class="carrusel" aria-roledescription="carrusel" aria-label="Novedades de la clínica"
             data-carrusel>
        <div class="carrusel-pista" data-pista>
            <?php foreach ($diapositivas as $i => $d): ?>
                <article class="diapositiva <?= e($d['tono']) ?>"
                         aria-roledescription="diapositiva"
                         aria-label="<?= ($i + 1) . ' de ' . count($diapositivas) ?>">
                    <div class="diapositiva-texto">
                        <p class="diapositiva-etiqueta"><?= e($d['etiqueta']) ?></p>
                        <?php // La primera es el título principal de la página (h1). ?>
                        <?php $etiqueta_titulo = $i === 0 ? 'h1' : 'h2'; ?>
                        <<?= $etiqueta_titulo ?>><?= e($d['titulo']) ?></<?= $etiqueta_titulo ?>>
                        <p><?= e($d['texto']) ?></p>
                        <a class="pub-boton pub-boton-blanco" href="<?= e(url($d['boton'][1])) ?>"><?= e($d['boton'][0]) ?></a>
                    </div>
                    <img class="diapositiva-imagen" src="<?= e(url('/assets/img/' . $d['imagen'])) ?>"
                         alt="" width="480" height="360" <?= $i > 0 ? 'loading="lazy"' : '' ?>>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Controles (los activa portada.js) -->
        <button class="carrusel-flecha carrusel-anterior" type="button" data-anterior
                aria-label="Diapositiva anterior" hidden>&#8249;</button>
        <button class="carrusel-flecha carrusel-siguiente" type="button" data-siguiente
                aria-label="Diapositiva siguiente" hidden>&#8250;</button>
        <div class="carrusel-puntos" data-puntos hidden>
            <?php foreach ($diapositivas as $i => $d): ?>
                <button type="button" data-ir="<?= $i ?>"
                        aria-label="Ir a la diapositiva <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Horarios en una franja que se monta sobre el carrusel (datos ficticios) -->
    <section class="pub-franja aparecer" aria-label="Horarios de atención">
        <div>
            <?= $icono_publico('i-calendario') ?>
            <span>Lunes a viernes</span>
            <strong>7:00 – 20:00</strong>
        </div>
        <div>
            <?= $icono_publico('i-calendario') ?>
            <span>Sábados</span>
            <strong>8:00 – 14:00</strong>
        </div>
        <div>
            <?= $icono_publico('i-alerta') ?>
            <span>Emergencias</span>
            <strong>24 horas</strong>
        </div>
        <div>
            <?= $icono_publico('i-telefono') ?>
            <span>Teléfono</span>
            <strong>(01) 555-0100</strong>
        </div>
    </section>
</div>

<!-- 2. Por qué elegirnos: título a la izquierda, razones numeradas a la derecha -->
<section class="pub-seccion pub-dividida aparecer">
    <div class="pub-dividida-titulo">
        <p class="pub-etiqueta">Clínica Vida Sana</p>
        <h2>Por qué elegirnos</h2>
    </div>
    <ol class="pub-razones">
        <li>
            <h3>Atención cercana</h3>
            <p>Te llamamos por tu nombre. Dedicamos el tiempo que haga falta a escucharte.</p>
        </li>
        <li>
            <h3>Todo en un solo lugar</h3>
            <p>Consulta, laboratorio y seguimiento bajo el mismo techo, sin vueltas.</p>
        </li>
        <li>
            <h3>Citas fáciles</h3>
            <p>Elige el día y la hora que te quedan mejor desde la web, y tu cita queda confirmada al instante.</p>
        </li>
    </ol>
</section>

<!-- 3. Servicios: mosaico con uno destacado -->
<section class="pub-seccion aparecer">
    <div class="pub-seccion-cabecera">
        <div>
            <p class="pub-etiqueta">Servicios</p>
            <h2>Nuestros servicios</h2>
        </div>
        <a class="pub-enlace-flecha" href="<?= e(url('/servicios.php')) ?>">Ver todos los servicios <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="pub-mosaico">
        <article class="pub-mosaico-item pub-mosaico-destacado">
            <span class="pub-mosaico-icono"><?= $icono_publico('i-cruz') ?></span>
            <h3>Medicina General</h3>
            <p>Controles, chequeos y atención de molestias del día a día.</p>
        </article>
        <article class="pub-mosaico-item">
            <span class="pub-mosaico-icono"><?= $icono_publico('i-personas') ?></span>
            <h3>Pediatría</h3>
            <p>Cuidado y seguimiento del crecimiento de los más pequeños.</p>
        </article>
        <article class="pub-mosaico-item">
            <span class="pub-mosaico-icono"><?= $icono_publico('i-documento') ?></span>
            <h3>Laboratorio</h3>
            <p>Análisis clínicos con resultados claros y a tiempo.</p>
        </article>
    </div>
</section>

<!-- 4. Llamado final -->
<section class="pub-llamado aparecer">
    <div>
        <h2>¿Necesitas una consulta?</h2>
        <p>Crea tu cuenta o ingresa al Portal del paciente y pide tu cita en línea.</p>
    </div>
    <a class="pub-boton pub-boton-blanco" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<!-- Aviso emergente. <dialog> es la ventana modal propia del navegador:
     se cierra con la X, con la tecla Esc o con el botón "Ahora no".
     portada.js lo abre una sola vez cada 7 días por visitante. -->
<dialog class="aviso-emergente" data-aviso aria-labelledby="aviso-titulo">
    <button class="aviso-cerrar" type="button" data-cerrar aria-label="Cerrar aviso">&times;</button>
    <img src="<?= e(url('/assets/img/slide-citas.svg')) ?>" alt="" width="160" height="120">
    <p class="aviso-etiqueta">Campaña de noviembre</p>
    <h2 id="aviso-titulo">Chequeo preventivo para toda la familia</h2>
    <p>
        Este mes, pide tu cita de <strong>Medicina General</strong> o
        <strong>Pediatría</strong> desde el Portal del paciente y evita las colas.
    </p>
    <div class="aviso-botones">
        <a class="pub-boton" href="<?= e(url('/registro.php')) ?>">Crear mi cuenta</a>
        <button class="pub-boton pub-boton-secundario" type="button" data-cerrar>Ahora no</button>
    </div>
    <p class="aviso-nota">Campaña ficticia: clínica simulada con fines académicos.</p>
</dialog>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
