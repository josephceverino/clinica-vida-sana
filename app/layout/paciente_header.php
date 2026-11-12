<?php
// Cabecera común del Portal del paciente.
// Antes de incluirla, la página debe haber llamado a requerir_paciente() (o
// requerir_paciente_verificado()) y definir:
//   $titulo_pagina  -> título de la pestaña
//   $pagina_activa  -> archivo del menú (ej. 'index.php')
require_once __DIR__ . '/../core/auth.php';
enviar_cabeceras_seguridad();

date_default_timezone_set('America/Lima');

$usuario = usuario_actual();
$titulo_pagina = $titulo_pagina ?? 'Mi cuenta';
$pagina_activa = $pagina_activa ?? 'index.php';
$verificada = ($usuario['verificado'] ?? 0) === 1;

// Menú del portal: archivo => texto. Lo que aún no existe sale apagado.
$menu_paciente = [
    'index.php'      => 'Inicio',
    'citas.php'      => 'Mis citas',
    'pedir-cita.php' => 'Pedir cita',
    'historia.php'   => 'Historia clínica',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titulo_pagina) ?> | Portal del paciente</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/paciente.css')) ?>">
</head>
<body>
<header class="paciente-barra">
    <a class="marca" href="<?= e(url('/paciente/index.php')) ?>">Vida Sana <span>Portal del paciente</span></a>

    <nav class="paciente-menu" aria-label="Menú del portal">
        <?php foreach ($menu_paciente as $archivo => $texto): ?>
            <?php if (file_exists(__DIR__ . '/../../public/paciente/' . $archivo)): ?>
                <a href="<?= e(url('/paciente/' . $archivo)) ?>"
                   class="<?= $archivo === $pagina_activa ? 'activo' : '' ?>"><?= e($texto) ?></a>
            <?php else: ?>
                <span class="deshabilitado"><?= e($texto) ?> <small>pronto</small></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="paciente-sesion">
        <span><?= e($usuario['nombre']) ?></span>
        <form method="post" action="<?= e(url('/logout.php')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="boton boton-claro boton-chico">Salir</button>
        </form>
    </div>
</header>
<main class="contenido">
