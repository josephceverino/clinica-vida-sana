<?php
// Gestión de usuarios del personal (solo rol admin).
require_once __DIR__ . '/../../app/core/auth.php';

// Solo el admin puede entrar aquí (requerir_rol ya llama a requerir_login).
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

    $accion = $_POST['accion'] ?? '';

    // ---------- Crear usuario ----------
    if ($accion === 'crear') {
        $form['nombre']  = trim($_POST['nombre'] ?? '');
        $form['usuario'] = strtolower(trim($_POST['usuario'] ?? ''));
        $form['rol']     = $_POST['rol'] ?? '';
        $form['area_id'] = $_POST['area_id'] ?? '';
        $password        = $_POST['password'] ?? '';

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
            $cambiar = $pdo->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?');
            $cambiar->execute([$id]);
            mensaje_guardar('Estado del usuario actualizado.');
        }
        redirigir('/panel/usuarios.php');
    }
}

// Lista de usuarios (con el nombre del área).
$usuarios = $pdo->query(
    'SELECT u.id, u.nombre, u.usuario, u.rol, u.activo, a.nombre AS area
     FROM usuarios u
     LEFT JOIN areas a ON a.id = u.area_id
     ORDER BY u.activo DESC, u.nombre'
)->fetchAll();

$mensaje = mensaje_tomar();

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div>
        <h1>Usuarios</h1>
        <p>Personal que puede entrar al panel.</p>
    </div>
</header>

<?php if ($mensaje !== null): ?>
    <p class="aviso aviso-<?= e($mensaje['tipo']) ?>" role="status"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<div class="panel-columnas panel-columnas-usuarios">

    <!-- Lista -->
    <section class="caja">
        <h2>Usuarios registrados (<?= count($usuarios) ?>)</h2>
        <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Área</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['nombre']) ?></strong><br>
                                <small><?= e($u['usuario']) ?></small>
                            </td>
                            <td><?= e($u['rol']) ?></td>
                            <td><?= e($u['area'] ?? '—') ?></td>
                            <td>
                                <?php if ((int) $u['activo'] === 1): ?>
                                    <span class="estado estado-confirmada">activo</span>
                                <?php else: ?>
                                    <span class="estado estado-cancelada">inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $u['id'] !== $yo['id']): ?>
                                    <form method="post" action="<?= e(url('/panel/usuarios.php')) ?>">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="boton-tabla">
                                            <?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <small>(tú)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Formulario para crear -->
    <section class="caja">
        <h2>Nuevo usuario</h2>

        <?php if (count($errores) > 0): ?>
            <div class="aviso aviso-error" role="alert">
                <?php foreach ($errores as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/panel/usuarios.php')) ?>" class="form-panel">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="crear">

            <div class="campo">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" maxlength="100"
                       value="<?= e($form['nombre']) ?>" required>
            </div>

            <div class="campo">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" maxlength="50"
                       value="<?= e($form['usuario']) ?>" autocomplete="off" required>
            </div>

            <div class="campo">
                <label for="password">Contraseña (mínimo 8)</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" required>
            </div>

            <div class="campo">
                <label for="rol">Rol</label>
                <select id="rol" name="rol" required>
                    <?php foreach ($roles_validos as $rol_opcion): ?>
                        <option value="<?= e($rol_opcion) ?>"
                            <?= $form['rol'] === $rol_opcion ? 'selected' : '' ?>>
                            <?= e($rol_opcion) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="area_id">Área (opcional)</label>
                <select id="area_id" name="area_id">
                    <option value="">— Sin área —</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?= (int) $area['id'] ?>"
                            <?= (string) $form['area_id'] === (string) $area['id'] ? 'selected' : '' ?>>
                            <?= e($area['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="boton">Crear usuario</button>
        </form>
    </section>

</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
