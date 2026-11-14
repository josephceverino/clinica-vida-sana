<?php

require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/pacientes.php';

// Solo administración y admisión pueden acceder.
requerir_login();
requerir_rol(['admin', 'admision']);

$titulo_pagina = 'Pacientes';
$busqueda = '';
$error = '';
$pacientes = [];

if (isset($_GET['busqueda'])) {
    if (
        !is_string($_GET['busqueda'])
        || mb_strlen($_GET['busqueda']) > 100
    ) {
        $error = 'La búsqueda admite hasta 100 caracteres.';
    } else {
        $busqueda = trim($_GET['busqueda']);
    }
}

if ($error === '') {
    try {
        $pacientes = buscar_pacientes($busqueda);
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudieron consultar los pacientes.';
    }
}

// Sección del menú lateral que se marca como activa.
$pagina_activa = 'pacientes.php';
require __DIR__ . '/../../app/layout/panel_header.php';
?>

<section class="seccion">
    <h1>Pacientes</h1>
<p>
    <a
        class="boton"
        href="<?= e(url('/panel/registrar-paciente.php')) ?>"
    >
        Registrar paciente
    </a>
</p>
    <p>
        <a href="<?= e(url('/panel/index.php')) ?>">
            Volver al panel
        </a>
    </p>

    <form method="get" action="<?= e(url('/panel/pacientes.php')) ?>">
        <label for="busqueda">Buscar por DNI, nombres o apellidos</label>
        <p>
            <input
                id="busqueda"
                name="busqueda"
                type="search"
                maxlength="100"
                value="<?= e($busqueda) ?>"
            >
            <button class="boton" type="submit">Buscar</button>
        </p>
    </form>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php elseif ($pacientes === []): ?>
        <p>No se encontraron pacientes.</p>
    <?php else: ?>
        <p>Resultados mostrados: <?= e(count($pacientes)) ?>.</p>
        <p>Se muestran hasta 50 pacientes por búsqueda.</p>

        <div class="tabla-scroll">
            <table>
                <caption>Pacientes encontrados</caption>
                <thead>
                    <tr>
                        <th scope="col">Documento</th>
                        <th scope="col">Nombres</th>
                        <th scope="col">Apellidos</th>
                        <th scope="col">Nacimiento</th>
                        <th scope="col">Teléfono</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pacientes as $paciente): ?>
                        <tr>
                            <td><?= e($paciente['tipo_documento']) ?> <?= e($paciente['numero_documento']) ?></td>
                            <td><?= e($paciente['nombres']) ?></td>
                            <td><?= e($paciente['apellidos']) ?></td>
                            <td><?= e($paciente['fecha_nacimiento']) ?></td>
                            <td><?= e($paciente['telefono']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>