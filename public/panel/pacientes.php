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

<div class="ui-pagina ui-animada">

    <a class="ui-migas" href="<?= e(url('/panel/index.php')) ?>">
        <?= $icono_panel('i-volver') ?>
        Volver al panel
    </a>

    <header class="ui-cabecera">
        <div>
            <h1>Pacientes</h1>
            <p>Busca un paciente por su DNI, nombres o apellidos.</p>
        </div>
        <a class="ui-boton" href="<?= e(url('/panel/registrar-paciente.php')) ?>">
            <?= $icono_panel('i-agregar') ?>
            Registrar paciente
        </a>
    </header>

    <!-- Buscador (GET: la búsqueda queda en la dirección de la página) -->
    <section class="ui-tarjeta" aria-label="Buscar pacientes">
        <form class="ui-buscador" method="get" action="<?= e(url('/panel/pacientes.php')) ?>" role="search">
            <div class="ui-campo">
                <label for="busqueda">Buscar por DNI, nombres o apellidos</label>
                <div class="ui-con-icono">
                    <?= $icono_panel('i-buscar') ?>
                    <input
                        class="ui-entrada"
                        id="busqueda"
                        name="busqueda"
                        type="search"
                        maxlength="100"
                        placeholder="Ej.: 70000001 o Mendoza"
                        value="<?= e($busqueda) ?>"
                    >
                </div>
            </div>
            <button class="ui-boton" type="submit">Buscar</button>
            <?php if ($busqueda !== ''): ?>
                <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/pacientes.php')) ?>">Ver todos</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Resultados -->
    <section class="ui-tarjeta">
        <?php if ($error !== ''): ?>
            <div class="ui-aviso ui-aviso-error" role="alert">
                <?= $icono_panel('i-alerta') ?>
                <p><?= e($error) ?></p>
            </div>
        <?php elseif ($pacientes === []): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-personas') ?></span>
                <strong>No se encontraron pacientes.</strong>
                <?php if ($busqueda !== ''): ?>
                    <p>No hay coincidencias para «<?= e($busqueda) ?>». Revisa el DNI o prueba con otro nombre.</p>
                <?php else: ?>
                    <p>Todavía no hay pacientes registrados.</p>
                <?php endif; ?>
                <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/registrar-paciente.php')) ?>">
                    <?= $icono_panel('i-agregar') ?>
                    Registrar paciente
                </a>
            </div>
        <?php else: ?>
            <div class="ui-tarjeta-cabecera">
                <h2><?= $busqueda !== '' ? 'Resultados de la búsqueda' : 'Pacientes registrados' ?></h2>
                <span class="ui-insignia">Resultados mostrados: <?= e(count($pacientes)) ?></span>
            </div>

            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable" role="table">
                    <caption class="solo-lectores">Pacientes encontrados</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Documento</th>
                            <th scope="col" role="columnheader">Nombres</th>
                            <th scope="col" role="columnheader">Apellidos</th>
                            <th scope="col" role="columnheader">Nacimiento</th>
                            <th scope="col" role="columnheader">Teléfono</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($pacientes as $paciente): ?>
                            <tr role="row">
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Documento"><?= e($paciente['tipo_documento']) ?> <?= e($paciente['numero_documento']) ?></td>
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Nombres"><?= e($paciente['nombres']) ?></td>
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Apellidos"><?= e($paciente['apellidos']) ?></td>
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Nacimiento">
                                    <?php
                                    // Se muestra como 31/12/2000; la base la guarda como 2000-12-31.
                                    $nacimiento = DateTimeImmutable::createFromFormat('Y-m-d', (string) $paciente['fecha_nacimiento']);
                                    ?>
                                    <?php if ($nacimiento !== false): ?>
                                        <time datetime="<?= e($paciente['fecha_nacimiento']) ?>"><?= e($nacimiento->format('d/m/Y')) ?></time>
                                    <?php elseif ((string) $paciente['fecha_nacimiento'] === ''): ?>
                                        <span aria-hidden="true">—</span><span class="solo-lectores">Sin fecha</span>
                                    <?php else: ?>
                                        <?= e($paciente['fecha_nacimiento']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Teléfono">
                                    <?php if ((string) $paciente['telefono'] !== ''): ?>
                                        <?= e($paciente['telefono']) ?>
                                    <?php else: ?>
                                        <span aria-hidden="true">—</span><span class="solo-lectores">Sin teléfono</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="ui-nota">Se muestran hasta 50 pacientes por búsqueda.</p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
