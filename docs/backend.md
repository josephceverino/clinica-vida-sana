# Backend de la clínica (pacientes, citas y solicitudes)

Documentación de la parte hecha por Alexander. Resume qué hace cada módulo y las reglas de negocio que aplica.

## Dónde está el código

| Qué | Archivos |
|---|---|
| Lógica de pacientes | `app/modelos/pacientes.php` |
| Lógica de citas | `app/modelos/citas.php` |
| Lógica de solicitudes | `app/modelos/solicitudes.php` |
| Cuentas de pacientes (registro) | `app/modelos/cuentas.php`, `public/registro.php` |
| Panel: pacientes | `public/panel/pacientes.php`, `registrar-paciente.php` |
| Panel: citas | `public/panel/citas.php`, `registrar-cita.php`, `gestionar-cita.php` |
| Panel: solicitudes | `public/panel/solicitudes.php` |
| Panel: cuentas de pacientes por verificar | `public/panel/cuentas.php` (lógica en `app/modelos/cuentas.php`) |

Las páginas de `public/panel/` solo muestran y reciben datos; la lógica y las consultas están en `app/`.

## Qué puede hacer cada rol

### Zona pública
- Ver los servicios y áreas públicas.
- Registrarse como paciente (`/registro.php`). La cuenta queda **pendiente** hasta que admisión la verifique.
- ~~Enviar una solicitud de cita sin cuenta~~: el formulario anónimo `solicitar-cita.php` se **retiró**.

### Paciente (cuenta verificada)
- **Mis citas** (`public/paciente/citas.php`): sus próximas citas, su historial y sus solicitudes. Solo las suyas: el paciente se toma de la **sesión**, nunca de la dirección ni de un formulario.
- **Pedir cita** (`public/paciente/pedir-cita.php`): área pública, fecha preferida opcional (hoy a 6 meses) y motivo obligatorio (5 a 500 caracteres). Crea una solicitud ligada a su cuenta.
- Máximo **3 solicitudes pendientes** a la vez por paciente. El conteo y el guardado van dentro de una transacción con `FOR UPDATE`, así que enviar varias a la vez no salta el límite.
- Una cuenta pendiente de verificación no puede entrar a estas páginas.

### Administración y admisión
- Registrar y buscar pacientes (se rechaza el DNI duplicado).
- Consultar y registrar citas.
- Asignar un médico activo del área correspondiente.
- Gestionar los estados de las citas.
- Consultar y procesar solicitudes.

### Médico
- Ver **solo sus propias citas**, por fecha.
- Ver los datos básicos de los pacientes de esas citas.
- **Atender** una cita suya y registrar la historia clínica (ver abajo).
- No tiene acceso a la gestión general de pacientes ni de solicitudes.

## Historia clínica

La historia clínica son las **atenciones** que el médico registra al atender una cita (`app/modelos/atenciones.php`).

**Quién puede qué**

| Rol | Escribir atenciones | Leer atenciones |
|---|---|---|
| Médico | Solo en citas **suyas** | Solo las que él escribió |
| Paciente (cuenta verificada) | No | Solo las **suyas** |
| Admisión y admin | No | **No** (son datos sensibles; lo mínimo necesario) |

**Reglas de `registrar_atencion()`** (se comprueban en el modelo, no en la página):
- La cita debe estar asignada al médico de la sesión, **confirmada**, y su fecha y hora ya deben haber llegado.
- El **paciente** se toma de la cita y el **médico** de la sesión; nada de eso se lee del formulario.
- Diagnóstico obligatorio (5 a 500 caracteres); tratamiento hasta 500 y notas hasta 1000.
- Guardar la atención y pasar la cita a `atendida` ocurre **junto** (transacción con `FOR UPDATE`): enviar varias veces a la vez guarda una sola atención.
- Una cita ajena o inexistente responde con el mismo 404, para no revelar qué citas existen.

**Una cita pasa a `atendida` solo cuando el médico registra la atención.** Admisión ya no puede marcarla a mano en `gestionar-cita.php`, para que ninguna cita quede "atendida" sin su historia clínica.

Páginas: `public/panel/atender-cita.php` (el médico) y `public/paciente/historia.php` (el paciente).

## Reglas de la agenda

Al registrar una cita se rechaza:
- Dos citas activas del **mismo médico** en la misma fecha y hora.
- Dos citas del **mismo paciente** en la misma fecha y hora, salvo que la anterior esté cancelada.

> **Alcance:** la comprobación compara la fecha y hora **exactas**. No calcula la duración de las consultas ni los solapamientos entre intervalos.

## Estados

**Citas**
- `pendiente` → `confirmada` o `cancelada`
- Para confirmar una cita **debe tener médico asignado**: si no, nadie podría atenderla.
- `confirmada` → `cancelada` (admisión) o `atendida` (solo cuando el médico registra la atención)
- `atendida` y `cancelada` no admiten más cambios.
- Una cita futura no se puede atender: debe haber llegado su fecha y hora.

**Solicitudes**
- `nueva` → `atendida` (en pantalla: "programada") o `descartada`.
- **Programar** (`public/panel/programar-solicitud.php`): admisión elige fecha, hora y médico del área. En una sola transacción se crea la cita **confirmada** y la solicitud pasa a `atendida` con `cita_id` apuntando a esa cita. Se rechaza si el médico o el paciente ya tienen cita a esa hora.
- **Descartar**: solo cambia el estado, sin crear cita.
- Una solicitud procesada no puede modificarse otra vez.
- El paciente ve en "Mis citas" la fecha y hora de la cita en la que se convirtió su solicitud.

## Seguridad aplicada

- Consultas preparadas con PDO.
- Salida HTML escapada con `e()`.
- Token CSRF en todos los formularios POST.
- Control de acceso por sesión y por rol.
- La identidad del personal sale de la **sesión**, nunca de un campo del formulario.
- Las áreas internas (Admisión, Sistemas) se rechazan en el formulario del panel y en el backend (`publica = 1`).

## Datos de prueba

`sql/seed.sql` crea 5 áreas, 8 cuentas (admin, admisión, 3 médicos y 3 de paciente), 6 pacientes, 18 citas, 9 atenciones (historia clínica) y 4 solicitudes. Las citas de Medicina General son de `medico1`, las de Pediatría de `medico2` y las de Laboratorio de `medico3`.

**Las fechas son relativas al día en que se importa** (`CURDATE()`): siempre hay 4 citas para hoy (la de las 08:00 sirve para probar "Atender" con `medico1`), algunas en los próximos días y otras pasadas con su historia clínica. Si la demo es otro día, basta con volver a importar `schema.sql` y `seed.sql`.

## Cambios del portal del paciente en la base de datos

- `pacientes` guarda `tipo_documento` (DNI, CE o PASAPORTE) y `numero_documento`; el par es único. Tiene `correo`, y `fecha_nacimiento` es opcional.
- `usuarios` admite el rol `paciente`. El personal entra con `usuario`; el paciente, con `correo` y enlazado a su ficha (`paciente_id`). `verificado = 0` significa que admisión aún no comprobó su identidad; `verificado_por` y `verificado_en` guardan quién la verificó y cuándo.

## Verificación de cuentas (admisión)

En `Cuentas por verificar` admisión ve, lado a lado, lo que escribió la persona al registrarse y la ficha del paciente. Dos avisos ayudan a decidir:
- **Ficha de la clínica**: el documento ya tenía ficha antes de la cuenta (la registró admisión o ya tiene citas).
- **Los nombres no coinciden** con la ficha.

Puede **verificar** o **rechazar** (la cuenta se desactiva, no se borra). Desde la pestaña *Rechazadas* se puede reconsiderar. La acción solo funciona sobre cuentas de pacientes, con token CSRF, y se aplica en la siguiente página que abra el paciente, sin que tenga que volver a entrar.
- `atenciones` es la historia clínica: una por cita, escrita por el médico.
- `intentos_acceso` guarda los intentos de login y registro, para limitarlos por IP.
- `solicitudes_cita.paciente_id` enlaza una solicitud con la cuenta que la hizo.

Los registros creados durante las pruebas locales **no** forman parte de `seed.sql`.

## Reiniciar la base de datos

`sql/schema.sql` **elimina y vuelve a crear** todas las tablas. Úsalo solo en una instalación nueva o para reiniciar a propósito una base de desarrollo. Nunca en producción con datos reales.

## Auditoría y privacidad

**Auditoría** (`app/core/auditoria.php`, tabla `auditoria`). Se anota quién hizo qué, sobre qué paciente, cuándo y desde qué IP:

| Acción | Cuándo se registra |
|---|---|
| `iniciar_sesion` | Login correcto (cualquier rol). |
| `ver_historia` | El paciente abre su historia clínica. |
| `ver_atencion` | El médico abre una cita en "Atender" (ve datos del paciente). |
| `registrar_atencion` | El médico guarda una atención. |
| `cambiar_password` | Cualquiera cambia su contraseña en "Mi perfil". |
| `verificar_cuenta` / `rechazar_cuenta` | Admisión verifica o rechaza una cuenta de paciente. |
| `programar_cita` | Admisión convierte una solicitud en cita. |

- Solo se **agregan** filas: la web nunca edita ni borra la auditoría.
- Si falla el registro, la página sigue funcionando y el error va al log (no se deja de atender a un paciente por eso).
- El **admin** la consulta en `panel/auditoria.php` (filtros por acción y paciente). Admisión y médicos reciben 403.
- El **paciente** ve en su historia clínica quién del personal la consultó.

**Privacidad** (Ley 29733): el registro exige marcar la casilla de aceptación (también se valida en PHP) y se guarda la fecha en `usuarios.acepto_privacidad_en`. El texto está en `public/privacidad.php`, enlazado en el pie de todas las páginas públicas.
