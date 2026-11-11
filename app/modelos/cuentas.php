<?php
// Cuentas de pacientes: validación y registro.
// El personal (admin, admisión, médico) se crea desde public/panel/usuarios.php.
require_once __DIR__ . '/../core/db.php';

/**
 * Tipos de documento permitidos y su formato.
 * Clave = valor guardado en la base de datos.
 */
function tipos_documento(): array
{
    return [
        'DNI'       => ['nombre' => 'DNI',                    'patron' => '/^[0-9]{8}$/',            'ayuda' => '8 dígitos'],
        'CE'        => ['nombre' => 'Carné de extranjería',   'patron' => '/^[A-Z0-9]{9,12}$/',      'ayuda' => '9 a 12 letras o números'],
        'PASAPORTE' => ['nombre' => 'Pasaporte',              'patron' => '/^[A-Z0-9]{6,12}$/',      'ayuda' => '6 a 12 letras o números'],
    ];
}

/**
 * Valida lo que llegó del formulario de registro (regla 8: en el servidor).
 * Devuelve [errores, datos_limpios]. Si no hay errores, los datos están listos.
 * Los errores de aquí son de FORMATO (no revelan si algo ya existe).
 */
function validar_registro(array $entrada): array
{
    $errores = [];
    $tipos = tipos_documento();

    $nombres   = trim((string) ($entrada['nombres'] ?? ''));
    $apellidos = trim((string) ($entrada['apellidos'] ?? ''));
    $tipo      = (string) ($entrada['tipo_documento'] ?? '');
    $numero    = strtoupper(trim((string) ($entrada['numero_documento'] ?? '')));
    $correo    = mb_strtolower(trim((string) ($entrada['correo'] ?? '')));
    $celular   = trim((string) ($entrada['celular'] ?? ''));
    $password  = (string) ($entrada['password'] ?? '');
    $confirmar = (string) ($entrada['confirmar_password'] ?? '');

    // Letras (con tildes), espacios, apóstrofo, punto y guion.
    $patron_nombre = '/^[\p{L}][\p{L}\p{M} \'.-]{1,99}$/u';

    if (!preg_match($patron_nombre, $nombres)) {
        $errores[] = 'Escribe tus nombres (solo letras, entre 2 y 100 caracteres).';
    }
    if (!preg_match($patron_nombre, $apellidos)) {
        $errores[] = 'Escribe tus apellidos (solo letras, entre 2 y 100 caracteres).';
    }

    if (!isset($tipos[$tipo])) {
        $errores[] = 'Elige un tipo de documento.';
    } elseif (!preg_match($tipos[$tipo]['patron'], $numero)) {
        $errores[] = 'El número de documento no es válido para ' . $tipos[$tipo]['nombre']
            . ' (' . $tipos[$tipo]['ayuda'] . ').';
    }

    if (mb_strlen($correo) > 120 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Escribe un correo válido.';
    }

    if (!preg_match('/^[0-9+() -]{7,20}$/', $celular)) {
        $errores[] = 'Escribe un número de celular válido (7 a 20 caracteres: números, +, - o paréntesis).';
    }

    // bcrypt solo usa los primeros 72 bytes, por eso ese es el máximo.
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errores[] = 'La contraseña debe incluir al menos una letra y un número.';
    }

    if ($password !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    return [$errores, [
        'nombres'          => $nombres,
        'apellidos'        => $apellidos,
        'tipo_documento'   => $tipo,
        'numero_documento' => $numero,
        'correo'           => $correo,
        'celular'          => $celular,
        'password'         => $password,
    ]];
}

/**
 * Crea la cuenta de un paciente. La cuenta nace PENDIENTE (verificado = 0):
 * admisión comprueba la identidad antes de que pueda ver su historial.
 *
 * Devuelve true si se creó, y false si NO se pudo porque el correo o el
 * documento ya están en uso. A propósito no decimos cuál de los dos: así
 * nadie puede usar este formulario para averiguar quién está registrado.
 *
 * Si el documento ya tiene una ficha (la registró admisión), la cuenta se
 * enlaza a esa ficha SIN modificarla: quien se registra no puede cambiar
 * los datos de una ficha existente.
 */
function registrar_cuenta_paciente(array $datos): bool
{
    $pdo = db();

    // El hash se calcula SIEMPRE antes de mirar la base de datos, para que
    // tarde lo mismo exista o no el documento (no filtra información).
    $hash = password_hash($datos['password'], PASSWORD_DEFAULT);
    $nombre_cuenta = $datos['nombres'] . ' ' . $datos['apellidos'];

    $pdo->beginTransaction();

    try {
        // ¿Ese correo ya tiene cuenta?
        $consulta = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
        $consulta->execute([$datos['correo']]);
        if ($consulta->fetch() !== false) {
            $pdo->rollBack();
            return false;
        }

        // ¿Ya existe una ficha con ese documento?
        $consulta = $pdo->prepare(
            'SELECT id FROM pacientes
             WHERE tipo_documento = ? AND numero_documento = ?
             FOR UPDATE'
        );
        $consulta->execute([$datos['tipo_documento'], $datos['numero_documento']]);
        $ficha = $consulta->fetch();

        if ($ficha !== false) {
            $paciente_id = (int) $ficha['id'];

            // ¿Esa ficha ya tiene cuenta?
            $consulta = $pdo->prepare('SELECT id FROM usuarios WHERE paciente_id = ?');
            $consulta->execute([$paciente_id]);
            if ($consulta->fetch() !== false) {
                $pdo->rollBack();
                return false;
            }
        } else {
            $insertar = $pdo->prepare(
                'INSERT INTO pacientes
                    (tipo_documento, numero_documento, nombres, apellidos, correo, telefono)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insertar->execute([
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['nombres'],
                $datos['apellidos'],
                $datos['correo'],
                $datos['celular'],
            ]);
            $paciente_id = (int) $pdo->lastInsertId();
        }

        $insertar = $pdo->prepare(
            "INSERT INTO usuarios
                (nombre, correo, password_hash, rol, paciente_id, activo, verificado)
             VALUES (?, ?, ?, 'paciente', ?, 1, 0)"
        );
        $insertar->execute([$nombre_cuenta, $datos['correo'], $hash, $paciente_id]);

        $pdo->commit();
        return true;
    } catch (PDOException $error) {
        $pdo->rollBack();

        // 1062 = valor duplicado (dos registros a la vez con el mismo dato).
        if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
            return false;
        }

        throw $error;
    }
}
