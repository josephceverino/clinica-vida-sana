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
- ~~Enviar una solicitud de cita sin cuenta~~: el formulario anónimo `solicitar-cita.php` se **retiró**. Las solicitudes saldrán de la cuenta del paciente (en construcción).

### Administración y admisión
- Registrar y buscar pacientes (se rechaza el DNI duplicado).
- Consultar y registrar citas.
- Asignar un médico activo del área correspondiente.
- Gestionar los estados de las citas.
- Consultar y procesar solicitudes.

### Médico
- Ver **solo sus propias citas**, por fecha.
- Ver los datos básicos de los pacientes de esas citas.
- No tiene acceso a la gestión general de pacientes ni de solicitudes.

## Reglas de la agenda

Al registrar una cita se rechaza:
- Dos citas activas del **mismo médico** en la misma fecha y hora.
- Dos citas del **mismo paciente** en la misma fecha y hora, salvo que la anterior esté cancelada.

> **Alcance:** la comprobación compara la fecha y hora **exactas**. No calcula la duración de las consultas ni los solapamientos entre intervalos.

## Estados

**Citas**
- `pendiente` → `confirmada` o `cancelada`
- `confirmada` → `atendida` o `cancelada`
- `atendida` y `cancelada` no admiten más cambios.
- Una cita futura no puede marcarse como `atendida`.

**Solicitudes**
- `nueva` → `atendida` o `descartada`
- Una solicitud procesada no puede modificarse otra vez.
- Marcar una solicitud como `atendida` **no** crea una cita.

## Seguridad aplicada

- Consultas preparadas con PDO.
- Salida HTML escapada con `e()`.
- Token CSRF en todos los formularios POST.
- Control de acceso por sesión y por rol.
- La identidad del personal sale de la **sesión**, nunca de un campo del formulario.
- Las áreas internas (Admisión, Sistemas) se rechazan en el formulario del panel y en el backend (`publica = 1`).

## Datos de prueba

`sql/seed.sql` crea 5 áreas, 6 cuentas (admin, admisión, 2 médicos y 2 pacientes), 6 pacientes, 7 citas, 2 atenciones (historia clínica) y 3 solicitudes. Las citas de Medicina General son de `medico1` y las de Pediatría de `medico2`; Laboratorio aún no tiene médico. Las fechas son ejemplos fijos de octubre y noviembre de 2026.

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
