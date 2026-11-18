<?php
// Servicios y especialidades (zona pública).
// Las áreas se leen de la base de datos, así que si se agrega una nueva
// en la tabla "areas", aparece aquí sin tocar este archivo.
require_once __DIR__ . '/../app/core/db.php';

$titulo_pagina = 'Servicios';

// Solo las áreas marcadas como públicas. No hay datos del usuario en esta
// consulta, pero igual usamos prepare() por costumbre (regla de seguridad 1).
$consulta = db()->prepare(
    'SELECT nombre, descripcion FROM areas WHERE publica = 1 ORDER BY nombre'
);
$consulta->execute();
$areas = $consulta->fetchAll();

require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- Encabezado de la página -->
<section class="pub-encabezado aparecer">
    <p class="pub-etiqueta">Servicios</p>
    <h1>Cuidamos cada etapa de tu salud</h1>
    <p class="pub-bajada">
        Estas son las áreas donde te atendemos. Si no sabes a cuál ir,
        pide tu cita y te orientamos.
    </p>
</section>

<!-- Áreas (salen de la base de datos): lista numerada en dos columnas -->
<section class="pub-seccion aparecer">
    <?php if (count($areas) === 0): ?>
        <p class="pub-bajada">Pronto publicaremos nuestros servicios.</p>
    <?php else: ?>
        <ol class="pub-servicios">
            <?php foreach ($areas as $area): ?>
                <li class="pub-servicio">
                    <span class="pub-servicio-icono"><?= $icono_publico('i-cruz') ?></span>
                    <div>
                        <h2><?= e($area['nombre']) ?></h2>
                        <p><?= e($area['descripcion']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<section class="pub-llamado aparecer">
    <div>
        <h2>¿Listo para tu consulta?</h2>
        <p>Ingresa al Portal del paciente y reserva tu cita en el horario que prefieras.</p>
    </div>
    <a class="pub-boton pub-boton-blanco" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
