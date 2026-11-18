<?php
// Gestión de usuarios del personal (solo rol admin).
require_once __DIR__ . '/../../app/core/auth.php';

// Solo el admin puede entrar aquí (regla 6: primero el login, luego el rol).
requerir_login();
requerir_rol(['admin']);

$titulo_pagina = 'Usuarios';
$pagina_activa = 'usuarios.php';
$pdo = db();
$yo = usuario_actual();

$roles_validos = ['admin', 'admision', 'medico'];
$errores = [];
// Valores del formulario (para no borrarlos si hay un error).
$form = ['nombre' => '', 'usuario' => '', 'rol' => 'admision', 'area_id' => ''];

// Lista de áreas para el desplegable.
$areas = $pdo->query('SELECT id, nombre FROM areas ORDER BY nombre')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Regla 4: todo formulario POST lleva token CSRF.
    if (!csrf_verificar()) {
        mensaje_guardar('La sesión expiró. Inténtalo otra vez.', 'error');
        redirigir('/panel/usuarios.php');
    }

    $accion = campo_texto($_POST, 'accion');

    // ---------- Crear usuario ----------
    if ($accion === 'crear') {
        $form['nombre']  = trim(campo_texto($_POST, 'nombre'));
        $form['usuario'] = strtolower(trim(campo_texto($_POST, 'usuario')));
        $form['rol']     = campo_texto($_POST, 'rol');
        $form['area_id'] = campo_texto($_POST, 'area_id');
        $password        = campo_texto($_POST, 'password');

        // Regla 8: validar siempre en el servidor.
        if (mb_strlen($form['nombre']) < 3 || mb_strlen($form['nombre']) > 100) {
            $errores[] = 'El nombre debe tener entre 3 y 100 caracteres.';
        }
        if (!preg_match('/^[a-z0-9._]{3,50}$/', $form['usuario'])) {
            $errores[] = 'El usuario debe tener 3 a 50 caracteres: letras, números, punto o guion bajo.';
        }
        // bcrypt solo usa los primeros 72 bytes; más allá se ignorarían en silencio.
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        }
        if (!in_array($form['rol'], $roles_validos, true)) {
            $errores[] = 'Elige un rol válido.';
        }

        // El área es opcional, pero si viene debe existir.
        $area_id = null;
        if ($form['area_id'] !== '') {
            $consulta = $pdo->prepare('SELECT id FROM areas WHERE id = ?');
            $consulta->execute([(int) $form['area_id']]);
            if ($consulta->fetch() === false) {
                $errores[] = 'El área elegida no existe.';
            } else {
                $area_id = (int) $form['area_id'];
            }
        }

        if (count($errores) === 0) {
            try {
                $insertar = $pdo->prepare(
                    'INSERT INTO usuarios (nombre, usuario, password_hash, rol, area_id)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insertar->execute([
                    $form['nombre'],
                    $form['usuario'],
                    // Regla 2: nunca guardamos la contraseña en texto plano.
                    password_hash($password, PASSWORD_DEFAULT),
                    $form['rol'],
                    $area_id,
                ]);
                mensaje_guardar('Usuario "' . $form['usuario'] . '" creado.');
                redirigir('/panel/usuarios.php');
            } catch (PDOException $error) {
                // 23000 = el nombre de usuario ya existe (columna UNIQUE).
                if ($error->getCode() === '23000') {
                    $errores[] = 'Ese nombre de usuario ya está en uso.';
                } else {
                    throw $error;
                }
            }
        }
    }

    // ---------- Activar o desactivar ----------
    if ($accion === 'estado') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $yo['id']) {
            // Evita que el admin se desactive a sí mismo y se quede afuera.
            mensaje_guardar('No puedes desactivar tu propio usuario.', 'error');
        } else {
            // Solo personal: las cuentas de pacientes no se gestionan desde aquí.
            $cambiar = $pdo->prepare(
                "UPDATE usuarios SET activo = 1 - activo WHERE id = ? AND rol <> 'paciente'"
            );
            $cambiar->execute([$id]);
            mensaje_guardar('Estado del usuario actualizado.');
        }
        redirigir('/panel/usuarios.php');
    }
}

// Lista del personal (con el nombre del área). Los pacientes no aparecen.
$usuarios = $pdo->query(
    "SELECT u.id, u.nombre, u.usuario, u.rol, u.activo, a.nombre AS area
     FROM usuarios u
     LEFT JOIN areas a ON a.id = u.area_id
     WHERE u.rol <> 'paciente'
     ORDER BY u.activo DESC, u.nombre"
)->fetchAll();

$mensaje = mensaje_tomar();

require __DIR__ . '/../../app/layout/panel_header.php';

// Nombres de los roles para mostrar (el valor guardado no cambia).
$nombres_rol = ['admin' => 'Administrador', 'admision' => 'Admisión', 'medico' => 'Médico'];
$clase_mensaje = $mensaje !== null && $mensaje['tipo'] === 'error' ? 'ui-aviso-error' : 'ui-aviso-exito';
?>

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Usuarios</h1>
            <p>Personal que puede entrar al panel.</p>
        </div>
    </header>

    <?php if ($mensaje !== null): ?>
        <div class="ui-aviso <?= e($clase_mensaje) ?>" role="<?= $mensaje['tipo'] === 'error' ? 'alert' : 'status' ?>">
            <?= $icono_panel($mensaje['tipo'] === 'error' ? 'i-alerta' : 'i-visto') ?>
            <p><?= e($mensaje['texto']) ?></p>
        </div>
    <?php endif; ?>

    <div class="ui-columnas ui-columnas-anchas">

        <!-- Lista -->
        <section class="ui-tarjeta">
            <div class="ui-tarjeta-cabecera">
                <h2>Usuarios registrados</h2>
                <span class="ui-insignia"><?= count($usuarios) ?> en total</span>
            </div>

            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable ui-tabla-apilable-ancha" role="table">
                    <caption class="solo-lectores">Usuarios del personal</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Nombre</th>
                            <th scope="col" role="columnheader">Rol</th>
                            <th scope="col" role="columnheader">Área</th>
                            <th scope="col" role="columnheader">Estado</th>
                            <th scope="col" role="columnheader"><span class="solo-lectores">Acción</span></th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($usuarios as $u): ?>
                            <tr role="row">
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Nombre">
                                    <span class="ui-celda-valor">
                                        <?= e($u['nombre']) ?>
                                        <small><?= e($u['usuario']) ?></small>
                                    </span>
                                </td>
                                <td role="cell" data-etiqueta="Rol"><?= e($nombres_rol[$u['rol']] ?? $u['rol']) ?></td>
                                <td role="cell" data-etiqueta="Área"><?= e($u['area'] ?? '—') ?></td>
                                <td role="cell" data-etiqueta="Estado">
                                    <?php if ((int) $u['activo'] === 1): ?>
                                        <span class="estado estado-confirmada">activo</span>
                                    <?php else: ?>
                                        <span class="estado estado-cancelada">inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td role="cell" data-etiqueta="Acción">
                                    <?php if ((int) $u['id'] !== $yo['id']): ?>
                                        <form method="post" action="<?= e(url('/panel/usuarios.php')) ?>" data-cargando>
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="accion" value="estado">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <?php if ((int) $u['activo'] === 1): ?>
                                                <button type="submit" class="ui-boton ui-boton-peligro ui-boton-chico"
                                                        data-texto-cargando="Guardando…">Desactivar</button>
                                            <?php else: ?>
                                                <button type="submit" class="ui-boton ui-boton-secundario ui-boton-chico"
                                                        data-texto-cargando="Guardando…">Activar</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="ui-insignia">tú</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Formulario para crear -->
        <section class="ui-tarjeta">
            <h2>Nuevo usuario</h2>

            <?php if (count($errores) > 0): ?>
                <div class="ui-aviso ui-aviso-error" role="alert">
                    <?= $icono_panel('i-alerta') ?>
                    <div>
                        <?php foreach ($errores as $error): ?>
                            <p><?= e($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/panel/usuarios.php')) ?>" data-cargando>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="crear">

                <div class="ui-rejilla">
                    <div class="ui-campo ui-campo-ancho">
                        <label for="nombre">Nombre completo</label>
                        <input class="ui-entrada" type="text" id="nombre" name="nombre" maxlength="100"
                               aria-describedby="ayuda-nombre"
                               value="<?= e($form['nombre']) ?>" required>
                        <span class="ui-ayuda" id="ayuda-nombre">Entre 3 y 100 caracteres.</span>
                    </div>

                    <div class="ui-campo ui-campo-ancho">
                        <label for="usuario">Usuario</label>
                        <input class="ui-entrada" type="text" id="usuario" name="usuario" maxlength="50"
                               aria-describedby="ayuda-usuario"
                               value="<?= e($form['usuario']) ?>" autocomplete="off" required>
                        <span class="ui-ayuda" id="ayuda-usuario">Letras, números, punto o guion bajo (3 a 50).</span>
                    </div>

                    <div class="ui-campo ui-campo-ancho">
                        <label for="password">Contraseña (mínimo 8)</label>
                        <input class="ui-entrada" type="password" id="password" name="password"
                               aria-describedby="ayuda-password"
                               autocomplete="new-password" required>
                        <span class="ui-ayuda" id="ayuda-password">Entre 8 y 72 caracteres.</span>
                    </div>

                    <div class="ui-campo">
                        <label for="rol">Rol</label>
                        <select class="ui-entrada" id="rol" name="rol" required>
                            <?php foreach ($roles_validos as $rol_opcion): ?>
                                <option value="<?= e($rol_opcion) ?>"
                                    <?= $form['rol'] === $rol_opcion ? 'selected' : '' ?>>
                                    <?= e($rol_opcion) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ui-campo">
                        <label for="area_id">Área (opcional)</label>
                        <select class="ui-entrada" id="area_id" name="area_id">
                            <option value="">— Sin área —</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= (int) $area['id'] ?>"
                                    <?= (string) $form['area_id'] === (string) $area['id'] ? 'selected' : '' ?>>
                                    <?= e($area['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ui-acciones">
                    <button type="submit" class="ui-boton" data-texto-cargando="Creando…">
                        <?= $icono_panel('i-agregar') ?>
                        Crear usuario
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
