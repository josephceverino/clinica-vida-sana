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

$visuales = [
    'Medicina General' => ['clinica/consulta-general.webp', 'foto', 1774, 887, 'Una médica conversa con un paciente.'],
    'Pediatría' => ['slide-pediatria.svg', 'pediatria', 480, 360, ''],
    'Laboratorio' => ['slide-laboratorio.svg', 'laboratorio', 480, 360, ''],
];
?>

<!-- Encabezado de la página -->
<section class="pub-encabezado pub-servicios-encabezado aparecer">
    <p class="pub-etiqueta">Servicios</p>
    <h1>Cuidamos cada etapa de tu salud</h1>
    <p class="pub-bajada">
        Estas son las áreas donde te atendemos. Si no sabes a cuál ir,
        pide tu cita y te orientamos.
    </p>
</section>

<!-- Áreas publicadas, con recursos visuales para las especialidades conocidas. -->
<section class="pub-seccion aparecer">
    <?php if (count($areas) === 0): ?>
        <p class="pub-bajada">Pronto publicaremos nuestros servicios.</p>
    <?php else: ?>
        <ul class="pub-tarjetas-servicio" role="list">
            <?php foreach ($areas as $area): ?>
                <?php $visual = $visuales[$area['nombre']] ?? null; ?>
                <li class="pub-tarjeta-servicio">
                    <?php if ($visual): ?>
                        <?php [$archivo, $tipo, $ancho, $alto, $alt] = $visual; ?>
                        <div class="pub-servicio-visual<?= $tipo === 'foto' ? '' : ' pub-servicio-visual-' . e($tipo) ?>">
                            <img<?= $tipo === 'foto' ? ' class="pub-servicio-foto"' : '' ?>
                                 src="<?= e(recurso('/assets/img/' . $archivo)) ?>" alt="<?= e($alt) ?>"
                                 width="<?= $ancho ?>" height="<?= $alto ?>" loading="lazy" decoding="async">
                        </div>
                    <?php else: ?>
                        <div class="pub-servicio-visual"><?= $icono_publico('i-cruz') ?></div>
                    <?php endif; ?>
                    <div class="pub-servicio-texto">
                        <h2><?= e($area['nombre']) ?></h2>
                        <p><?= e($area['descripcion']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
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
