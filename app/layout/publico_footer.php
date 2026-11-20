</main>
<footer class="pub-pie">
    <div class="pub-pie-interior">
        <div class="pub-pie-marca">
            <a class="pub-marca pub-marca-clara" href="<?= e(url('/')) ?>">
                <img class="logo-oficial" src="<?= e(recurso('/assets/img/branding/logo.png')) ?>" width="2172" height="724" alt="Clínica Vida Sana">
            </a>
            <p>Clínica Vida Sana &mdash; clínica simulada, proyecto académico UCV 2026.</p>
        </div>
        <nav class="pub-pie-enlaces" aria-label="Enlaces del pie">
            <a href="<?= e(url('/servicios.php')) ?>">Servicios</a>
            <a href="<?= e(url('/nosotros.php')) ?>">Nosotros</a>
            <a href="<?= e(url('/contacto.php')) ?>">Contacto</a>
            <a href="<?= e(url('/privacidad.php')) ?>">Política de privacidad</a>
        </nav>
    </div>
</footer>
<?php
$whatsapp = (require __DIR__ . '/../../config/contacto_publico.php')['whatsapp'];
$numero_whatsapp = ltrim(trim($whatsapp['numero']), '+');
$mensaje_whatsapp = trim($whatsapp['mensaje']);
$icono_whatsapp = recurso('/assets/img/branding/whatsapp.webp');
?>
<?php if (preg_match('/\A\+?[1-9][0-9]{6,14}\z/', trim($whatsapp['numero']))): ?>
    <?php $enlace_whatsapp = 'https://wa.me/' . $numero_whatsapp
        . ($mensaje_whatsapp === '' ? '' : '?text=' . rawurlencode($mensaje_whatsapp)); ?>
    <a class="pub-whatsapp" href="<?= e($enlace_whatsapp) ?>" aria-label="Abrir WhatsApp" referrerpolicy="no-referrer">
        <img src="<?= e($icono_whatsapp) ?>" alt="" width="30" height="30">
    </a>
<?php else: ?>
<button class="pub-whatsapp" type="button" data-whatsapp-demo aria-haspopup="dialog"
        aria-label="WhatsApp — Demostración" aria-controls="pub-whatsapp-demo" hidden>
    <img src="<?= e($icono_whatsapp) ?>" alt="" width="30" height="30">
</button>
<dialog class="pub-whatsapp-dialogo" id="pub-whatsapp-demo" aria-labelledby="pub-whatsapp-titulo"
        aria-describedby="pub-whatsapp-descripcion">
    <h2 id="pub-whatsapp-titulo">WhatsApp de demostración</h2>
    <p id="pub-whatsapp-descripcion">Clínica Vida Sana es un proyecto académico. Esta demostración no tiene un número asociado, no abre conversaciones ni envía datos.</p>
    <button class="pub-boton" type="button" data-whatsapp-cerrar autofocus>Entendido</button>
</dialog>
<?php endif; ?>
</body>
</html>
