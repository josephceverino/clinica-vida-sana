<?php

require_once __DIR__ . '/../../app/auth.php';

requerir_login();
requerir_rol(['admin', 'admision', 'medico']);

$usuario = usuario_actual();
$titulo_pagina = 'Panel del personal';

$puede_gestionar = in_array(
    $usuario['rol'],
    ['admin', 'admision'],
    true
);

// Prepara la sesión y el token antes de imprimir HTML.
csrf_token();

require __DIR__ . '/../../app/layout/header.php';
?>

<section class="seccion">
    <h1>Panel del personal</h1>

    <p>
        Hola, <strong><?= e($usuario['nombre']) ?></strong>.
        Tu rol es: <strong><?= e($usuario['rol']) ?></strong>.
    </p>

    <div class="rejilla-3">
        <?php if ($puede_gestionar): ?>
            <article class="punto">
                <h2>Pacientes</h2>
                <p>Consulta y registra pacientes.</p>

                <p>
                    <a
                        class="boton"
                        href="<?= e(url('/panel/pacientes.php')) ?>"
                    >
                        Ver pacientes
                    </a>
                </p>

                <p>
                    <a href="<?= e(url('/panel/registrar-paciente.php')) ?>">
                        Registrar paciente
                    </a>
                </p>
            </article>

            <article class="punto">
                <h2>Citas</h2>
                <p>
                    Consulta citas, asigna médicos
                    y gestiona sus estados.
                </p>

                <p>
                    <a
                        class="boton"
                        href="<?= e(url('/panel/citas.php')) ?>"
                    >
                        Ver citas
                    </a>
                </p>

                <p>
                    <a href="<?= e(url('/panel/registrar-cita.php')) ?>">
                        Registrar cita
                    </a>
                </p>
            </article>

            <article class="punto">
                <h2>Solicitudes</h2>
                <p>
                    Revisa las solicitudes recibidas
                    desde el formulario público.
                </p>

                <p>
                    <a
                        class="boton"
                        href="<?= e(url('/panel/solicitudes.php')) ?>"
                    >
                        Ver solicitudes
                    </a>
                </p>
            </article>
        <?php else: ?>
            <article class="punto">
                <h2>Mis citas</h2>
                <p>
                    Consulta tus citas asignadas
                    y los datos básicos de los pacientes.
                </p>

                <p>
                    <a
                        class="boton"
                        href="<?= e(url('/panel/citas.php')) ?>"
                    >
                        Ver mis citas
                    </a>
                </p>
            </article>
        <?php endif; ?>
    </div>

    <p>Clínica simulada: utiliza únicamente datos ficticios.</p>

    <form method="post" action="<?= e(url('/logout.php')) ?>">
        <?= csrf_campo() ?>

        <button class="boton boton-claro" type="submit">
            Cerrar sesión
        </button>
    </form>
</section>

<?php require __DIR__ . '/../../app/layout/footer.php'; ?>
