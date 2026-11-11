# Backend de la clínica (pacientes, citas y solicitudes)

Documentación de la parte hecha por Alexander. Resume qué hace cada módulo y las reglas de negocio que aplica.

## Dónde está el código

| Qué | Archivos |
|---|---|
| Lógica de pacientes | `app/pacientes.php` |
| Lógica de citas | `app/citas.php` |
| Lógica de solicitudes | `app/solicitudes.php` |
| Formulario público de cita | `public/solicitar-cita.php` |
| Panel: pacientes | `public/panel/pacientes.php`, `registrar-paciente.php` |
| Panel: citas | `public/panel/citas.php`, `registrar-cita.php`, `gestionar-cita.php` |
| Panel: solicitudes | `public/panel/solicitudes.php` |

Las páginas de `public/panel/` solo muestran y reciben datos; la lógica y las consultas están en `app/`.

## Qué puede hacer cada rol

### Zona pública
- Ver los servicios y áreas públicas.
- Enviar una **solicitud de cita** (validación en PHP y token CSRF).
- Recibe una confirmación de recepción. **No** se crea una cita automáticamente.

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
- Las áreas internas (Admisión, Sistemas) se rechazan tanto en el formulario público como en el backend.

## Datos de prueba

`sql/seed.sql` crea 5 áreas, 3 usuarios, 5 pacientes, 6 citas y 3 solicitudes. Las citas de Medicina General se asignan al médico de prueba (`medico1`). Las fechas son ejemplos fijos de noviembre de 2026.

Los registros creados durante las pruebas locales **no** forman parte de `seed.sql`.

## Reiniciar la base de datos

`sql/schema.sql` **elimina y vuelve a crear** todas las tablas. Úsalo solo en una instalación nueva o para reiniciar a propósito una base de desarrollo. Nunca en producción con datos reales.
