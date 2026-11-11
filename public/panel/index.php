<?php
// Panel (zona privada). Por ahora es una página mínima para comprobar
// que el login funciona. El diseño real del panel viene después.
require_once __DIR__ . '/../../app/auth.php';

// Regla 6: toda página del panel empieza con requerir_login().
requerir_login();

$usuario = usuario_actual();
$titulo_pagina = 'Panel';
require __DIR__ . '/../../app/layout/header.php';
?>

<h1>Panel del personal</h1>
<p>Hola, <strong><?= e($usuario['nombre']) ?></strong>. Tu rol es: <?= e($usuario['rol']) ?>.</p>

<form method="post" action="<?= e(url('/logout.php')) ?>">
    <?= csrf_campo() ?>
    <button class="boton boton-claro" type="submit">Cerrar sesión</button>
</form>

<?php require __DIR__ . '/../../app/layout/footer.php'; ?>
