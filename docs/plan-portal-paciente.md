# Plan: Portal del paciente y reorganización

Decisiones acordadas entre Joseph y Claude. Se califica igual que en [`plan-de-trabajo.md`](plan-de-trabajo.md): **0–39 Malo, 40–69 Ok (no pasa), 70–100 Pasa**. Un paso no empieza hasta que el anterior pase, y los ítems 🔒 (seguridad) deben tener 70 o más siempre.

## Por qué

Hoy cualquier persona puede enviar una solicitud de cita con datos inventados. No hay a quién pedirle cuentas, se puede llenar el panel de basura, y el visitante no sabe qué pasó con su solicitud. La solución: **cuentas de paciente**.

## Decisiones

| Tema | Decisión |
|---|---|
| Verificación | La cuenta de paciente nace **pendiente**. Admisión comprueba la identidad y la activa. Hasta entonces no ve historial. |
| Citas | El paciente **pide** la cita desde su cuenta y **admisión confirma**. No hay reserva directa (aún no existen horarios de médicos). |
| Historia clínica | El paciente puede **ver su historia clínica** (solo lectura). El médico registra las atenciones. |
| Acceso | **Un solo login** para todos. Se quita "Acceso personal". El botón del menú es **Portal del paciente**. |
| Estructura | Se reordenan las carpetas (`app/core`, `app/modelos`, `public/errores`) **antes** de construir el portal. |

## Flujo

```
VISITANTE ─► Inicio · Servicios · Nosotros · Contacto
    │
    └─► [Portal del paciente] ─► /login.php
                                    │
                    ¿No tienes cuenta? ─► /registro.php ─► cuenta PENDIENTE
                                    │                            │
                                    │              Admisión verifica identidad
                                    ▼                            ▼
                      ┌─────────────┴──────────────┐        cuenta ACTIVA
                   paciente                  admin · admision · medico
                      ▼                              ▼
                 /paciente/                       /panel/
```

## Cambios en la base de datos

```
usuarios                              pacientes
────────                              ─────────
rol: + 'paciente'                     + tipo_documento (DNI / CE / pasaporte)
+ correo (único; login del paciente)  + numero_documento (único junto al tipo)
+ paciente_id  ─────────────────────► + correo
+ estado de la cuenta (pendiente /    fecha_nacimiento pasa a opcional
  activa / inactiva)

NUEVA: atenciones (historia clínica)
  id, cita_id, paciente_id, medico_id, motivo, diagnostico, tratamiento, notas, creado_en
```

## Registro: medidas de seguridad

- Token CSRF y validación en el servidor de todos los campos.
- Contraseña: 8 a 72 caracteres, con confirmación, guardada con `password_hash()`.
- Documento único. Mensajes **genéricos** (no revelar si un documento o correo ya existe).
- **Límite de intentos guardado en la base de datos** (por IP), no en la sesión. Esto también corrige el hallazgo 6 de la auditoría.
- Si el documento ya tiene ficha en la clínica, **no se enlaza solo**: queda pendiente hasta que admisión verifique.
- El paciente solo accede a **sus propios** datos: el `paciente_id` sale siempre de la sesión, nunca de un campo del formulario.
- Datos médicos solo para cuentas verificadas. Todo el contenido es ficticio.

## Pasos

| # | Paso | Quién | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|---|
| 1 | Acuerdos y actualizar `CLAUDE.md` | Joseph y Claude | `CLAUDE.md` describe la estructura, zonas y roles nuevos. | 70 | Pasa |
| 2 | **Reordenar carpetas** sin cambiar el comportamiento | Claude | Los 3 roles abren las mismas páginas que antes; los flujos y los errores siguen igual; las carpetas internas dan 403. | 70 | Pasa |
| 3 | Cambios en la base y rol `paciente` | Claude | `schema.sql` y `seed.sql` importan sin errores; el rol existe. | 70 | Pasa |
| 4 🔒 | Registro + login único con redirección por rol + límite de intentos en la base | Claude | Registro crea cuenta pendiente; borrar la cookie ya no salta el bloqueo; paciente va a `/paciente/` y personal a `/panel/`. | 70 | Pasa |
| 5 🔒 | Portal del paciente: mis citas y pedir cita; se retira `solicitar-cita.php` público | Alexander con apoyo | Un paciente solo ve lo suyo; `solicitar-cita.php` ya no existe. | __ | |
| 6 | Panel del admin ordenado + "cuentas por verificar" | Claude | El menú agrupa Atención y Administración; admisión puede activar una cuenta pendiente. | 70 | Pasa |
| 7 🔒 | Historia clínica: el médico registra atenciones y el paciente las ve | Alexander y Claude | Solo el dueño verificado la ve; el médico solo escribe en sus citas. | __ | |
| 8 | Cuentas de prueba y pruebas con los 4 roles | Ambos | `admin`, `admision1`, `medico1`, un segundo médico y `paciente1` funcionan; un registro nuevo recorre todo el flujo. | __ | |
| 9 | Arreglos de interfaz | Joseph | Las casillas largas ya no se agrandan (`resize: none`); formularios consistentes. | __ | |

## Cuentas de prueba previstas

- `admin` se conserva.
- `admision1` y `medico1` se conservan.
- Un **segundo médico** (Pediatría), para probar que cada médico solo ve lo suyo.
- `paciente1`, con su ficha. Además, un paciente registrado a mano por el formulario nuevo.

## Aviso para Alexander

El paso 2 mueve archivos: `app/*.php` pasa a `app/core/` y `app/modelos/`. Antes de seguir trabajando debe traer la rama nueva (`git fetch` y `git merge`) para no chocar.
