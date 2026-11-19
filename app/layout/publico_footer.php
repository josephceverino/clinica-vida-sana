</main>
<footer class="pub-pie">
    <div class="pub-pie-interior">
        <div class="pub-pie-marca">
            <a class="pub-marca pub-marca-clara" href="<?= e(url('/')) ?>">
                <?php if (in_array($pagina_actual, ['/login.php', '/registro.php'], true)): ?>
                    <span>Clínica Vida Sana</span>
                <?php else: ?>
                    <img class="logo-oficial" src="<?= e(recurso('/assets/img/branding/logo.png')) ?>" width="2172" height="724" alt="Clínica Vida Sana">
                <?php endif; ?>
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
</body>
</html>
