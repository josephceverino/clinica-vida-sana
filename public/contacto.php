<?php
// Página "Contacto" (zona pública).
// Solo muestra información (HTML). No tiene formulario: para pedir una
// cita está el Portal del paciente.
// Los datos de contacto son ficticios y el mapa muestra una referencia pública.
$titulo_pagina = 'Contacto';
$ubicacion = (require __DIR__ . '/../config/contacto_publico.php')['ubicacion'];
require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- 1. Introducción -->
<section class="pub-encabezado pub-contacto-encabezado aparecer">
    <p class="pub-etiqueta">Contacto</p>
    <h1>Estamos para ayudarte</h1>
    <p class="pub-bajada">
        <?php if ($ubicacion['demostrativa']): ?>
            Datos de contacto y horarios de ejemplo, con una ubicación de referencia.
        <?php else: ?>
            Consulta la ubicación y los datos de contacto y horarios de ejemplo.
        <?php endif; ?>
    </p>
</section>

<div class="pub-seccion pub-contacto aparecer">
    <section class="pub-contacto-mapa" aria-labelledby="contacto-mapa-titulo">
        <div class="pub-contacto-mapa-texto" data-whatsapp-proteger>
            <p class="pub-etiqueta"><?= $ubicacion['demostrativa'] ? 'Ubicación referencial — Proyecto académico' : 'Ubicación' ?></p>
            <h2 id="contacto-mapa-titulo"><?= e($ubicacion['nombre']) ?></h2>
            <p><?= e($ubicacion['direccion']) ?></p>
            <?php if ($ubicacion['demostrativa']): ?>
                <p>Esta referencia pública no es una sede de Clínica Vida Sana.</p>
            <?php endif; ?>
        </div>
        <iframe class="pub-contacto-mapa-marco" src="<?= e(url('/assets/mapas/ubicacion.php')) ?>"
                title="<?= e('Mapa interactivo: ' . $ubicacion['nombre']) ?>" loading="lazy"></iframe>
        <div class="pub-contacto-mapa-texto">
            <a class="pub-enlace-flecha" href="<?= e($ubicacion['enlace']) ?>">
                Abrir mapa externo <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </section>

    <div class="pub-contacto-detalles">
        <section class="pub-contacto-datos">
            <h2>Datos de contacto demostrativos</h2>
            <ul class="pub-lista-datos" role="list">
                <li>
                    <span class="pub-lista-icono"><?= $icono_publico('i-ubicacion') ?></span>
                    <span>Dirección ficticia — solo demostración</span>
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
        </section>

        <section class="pub-horario">
            <h2>Horarios de atención demostrativos</h2>
            <ul>
                <li><span>Lunes a viernes</span><strong>7:00 – 20:00</strong></li>
                <li><span>Sábados</span><strong>8:00 – 14:00</strong></li>
                <li><span>Emergencias</span><strong>24 horas</strong></li>
            </ul>
            <p>Domingos y feriados: solo emergencias.</p>
        </section>
    </div>
</div>

<!-- 3. Llamado final -->
<section class="pub-llamado aparecer">
    <div>
        <h2>¿Quieres pedir una cita?</h2>
        <p>Hazlo desde el Portal del paciente: eliges el horario y tu cita queda confirmada.</p>
    </div>
    <a class="pub-boton pub-boton-blanco" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
