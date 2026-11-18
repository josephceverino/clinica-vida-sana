# Rediseño del frontend — inventario y pruebas

Acompaña a `docs/rediseno-frontend.md`. Sirve para comprobar, al final de cada
fase, que el rediseño **no cambió lo que hace el sistema**: mismas páginas,
mismos permisos, mismos formularios.

- **Estado inicial medido:** 17/11/2026, rama `feature/rediseno` (desde `main` en `2960521`), XAMPP local.
- **Datos:** los de `sql/seed.sql` ya importados. No se ejecutó ningún SQL para estas pruebas.

---

## 1. Inventario de pantallas

Leyenda de plantilla: **Pub** = `publico_header/footer`, **Pac** = `paciente_header/footer`, **Pan** = `panel_header/footer`.
Todas cargan `base.css`; las de paciente añaden `paciente.css` y las del panel `panel.css`.

### Sitio público

| Pantalla | Ruta | Quién entra | Plantilla | Formularios | Notas |
|---|---|---|---|---|---|
| Inicio | `/index.php` | Todos | Pub | — | Usa `portada.js` (carrusel, aviso emergente, aparecer al bajar) |
| Servicios | `/servicios.php` | Todos | Pub | — | |
| Nosotros | `/nosotros.php` | Todos | Pub | — | |
| Contacto | `/contacto.php` | Todos | Pub | — | |
| Privacidad | `/privacidad.php` | Todos | Pub | — | |
| Login | `/login.php` | Sin sesión (con sesión redirige a su inicio) | Pub | 1 POST (`identificador`, `password`, `csrf_token`) | |
| Registro | `/registro.php` | Sin sesión (con sesión redirige a su inicio) | Pub | 1 POST | La cuenta nace pendiente |
| Error 403 | `/errores/403.php` | Todos | `mostrar_error()` | — | También 404 y 500 |
| Error 404 | `/errores/404.php` y cualquier ruta inexistente | Todos | `mostrar_error()` | — | |
| Cerrar sesión | `/logout.php` | Con sesión | — | Recibe el POST del botón Salir | No tiene pantalla |

### Portal del paciente

| Pantalla | Ruta | Quién entra | Formularios | Notas |
|---|---|---|---|---|
| Inicio | `/paciente/index.php` | Paciente (verificado o pendiente) | — | Si está pendiente, muestra el aviso de cuenta por verificar |
| Mis citas | `/paciente/citas.php` | Paciente verificado | 1 POST (cancelar cita) | Pendiente → redirige a Inicio |
| Pedir cita | `/paciente/pedir-cita.php` | Paciente verificado | 1 GET (buscar horario) + 2 POST (reservar, solicitud) | Pendiente → redirige a Inicio |
| Historia clínica | `/paciente/historia.php` | Paciente verificado | — | Pendiente → redirige a Inicio |
| Mi perfil | `/paciente/perfil.php` | Paciente (verificado o pendiente) | 1 POST (`form_password.php`) | |

Todas las pantallas del portal tienen además el botón **Salir** (POST con CSRF) de la cabecera.

### Panel del personal

| Pantalla | Ruta | Roles | Formularios | Notas |
|---|---|---|---|---|
| Resumen | `/panel/index.php` | admin, admision, medico | — | Al médico le muestra "Mi día" (`inicio-medico.php`) |
| Mi día (médico) | `/panel/inicio-medico.php` | medico | — | Gráfico SVG de atenciones |
| Pacientes | `/panel/pacientes.php` | admin, admision | 1 GET (buscar) | |
| Registrar paciente | `/panel/registrar-paciente.php` | admin, admision | 1 POST | |
| Citas | `/panel/citas.php` | admin, admision, medico | 1 GET (filtros) | |
| Registrar cita | `/panel/registrar-cita.php` | admin, admision | 1 POST | |
| Gestionar cita | `/panel/gestionar-cita.php?id=N` | admin, admision | 2 POST | Sin `id` válido → 400 |
| Atender cita | `/panel/atender-cita.php?id=N` | medico | 1 POST (atención) | Sin `id` o cita ajena → 404 |
| Agenda semanal | `/panel/agenda.php` | admin, admision, medico | 1 GET (semana/filtro) | |
| Solicitudes | `/panel/solicitudes.php` | admin, admision | 1 POST (descartar) | Globito en el menú |
| Programar solicitud | `/panel/programar-solicitud.php?id=N` | admin, admision | 1 POST | Sin `id` válido → 404 |
| Cuentas por verificar | `/panel/cuentas.php` | admin, admision | 2 POST (verificar, rechazar) | Globito en el menú |
| Usuarios del personal | `/panel/usuarios.php` | admin | 2 POST | |
| Horarios de médicos | `/panel/horarios.php` | admin | 1 GET + 2 POST | |
| Auditoría | `/panel/auditoria.php` | admin | 1 GET (filtros) | |
| Mi perfil | `/panel/perfil.php` | admin, admision, medico | 1 POST (`form_password.php`) | |

Todas las pantallas del panel tienen el botón **Salir** (POST con CSRF) y el menú lateral, que se arma según el rol en `panel_header.php`.

### Piezas que usa el JavaScript (no cambiarles el nombre)

`portada.js` busca: `[data-carrusel]`, `[data-pista]`, `[data-anterior]`, `[data-siguiente]`, `[data-ir]`, `[data-aviso]`, `[data-cerrar]`, `.aparecer`, y pone las clases `animar`, `carrusel-activo`, `visible` y `activo`.

---

## 2. Respuestas de cada página por cuenta (estado inicial)

Medido con `curl`, sin seguir redirecciones. `302 → X` significa "redirige a X".
Cuentas de `sql/seed.sql` (contraseña `Clinica2026!`). Los inicios de sesión de las cinco cuentas dieron `302` a su zona: personal → `/panel/index.php`, pacientes → `/paciente/index.php`.

| Ruta | Sin sesión | admin | admision1 | medico1 | paciente1 (verificado) | pendiente |
|---|---|---|---|---|---|---|
| `/index.php` `/servicios.php` `/nosotros.php` `/contacto.php` `/privacidad.php` | 200 | 200 | 200 | 200 | 200 | 200 |
| `/login.php` `/registro.php` | 200 | 302 → panel | 302 → panel | 302 → panel | 302 → paciente | 302 → paciente |
| `/errores/403.php` | 403 | 403 | 403 | 403 | 403 | 403 |
| `/errores/404.php`, ruta inexistente | 404 | 404 | 404 | 404 | 404 | 404 |
| `/paciente/index.php` `/paciente/perfil.php` | 302 → login | 403 | 403 | 403 | 200 | 200 |
| `/paciente/citas.php` `/paciente/pedir-cita.php` `/paciente/historia.php` | 302 → login | 403 | 403 | 403 | 200 | 302 → paciente |
| `/panel/index.php` `/panel/citas.php` `/panel/agenda.php` `/panel/perfil.php` | 302 → login | 200 | 200 | 200 | 403 | 403 |
| `/panel/inicio-medico.php` | 302 → login | 403 | 403 | 200 | 403 | 403 |
| `/panel/pacientes.php` `/panel/registrar-paciente.php` `/panel/registrar-cita.php` `/panel/solicitudes.php` `/panel/cuentas.php` | 302 → login | 200 | 200 | 403 | 403 | 403 |
| `/panel/gestionar-cita.php` (sin `id`) | 302 → login | 400 | 400 | 403 | 403 | 403 |
| `/panel/gestionar-cita.php?id=12` | — | 200 | — | 403 | — | — |
| `/panel/programar-solicitud.php` (sin `id`) | 302 → login | 404 | 404 | 403 | 403 | 403 |
| `/panel/programar-solicitud.php?id=2` | — | 200 | — | — | — | — |
| `/panel/atender-cita.php` (sin `id`) | 302 → login | 403 | 403 | 404 | 403 | 403 |
| `/panel/usuarios.php` `/panel/horarios.php` `/panel/auditoria.php` | 302 → login | 200 | 403 | 403 | 403 | 403 |

Carpetas que no deben abrirse desde el navegador: `../app/core/db.php`, `../config/config.php`, `../sql/seed.sql` → **403** las tres.

**No se pudo medir:** `atender-cita.php?id=N` con una cita propia de `medico1`, porque en los datos actuales no aparece ninguna cita suya para atender (las fechas de `seed.sql` se cuentan desde el día en que se importó). Se prueba a mano cuando haya una cita del día, sin reimportar datos que deban conservarse.

---

## 3. Qué se puede comprobar y cómo

| Comprobación | ¿Quién/cómo? | Necesita |
|---|---|---|
| Sintaxis de todos los PHP (`php -l`) | Claude, automático | Solo PHP de XAMPP |
| Tabla de respuestas de la sección 2 | Claude, script con `curl` | Apache y MariaDB encendidos, datos de `seed.sql` |
| Que solo cambien los archivos previstos (`git diff --stat`) | Claude, automático | Git |
| Colores escritos a mano fuera de las variables | Claude, búsqueda en el CSS | — |
| Contraste de los colores (mínimo 4.5:1 texto normal, 3:1 texto grande) | Claude, cálculo con script | — |
| Que el HTML conserve `method`, `action`, `name`, campos ocultos y `csrf_token` | Claude, comparar el HTML antes/después con `curl` | Apache y MariaDB |
| Que los formularios POST sigan funcionando (reservar, cancelar, verificar, atender…) | **Manual** (Joseph/Alexander) | Navegador y base de datos; cambian datos |
| Aspecto visual de cada pantalla, en computadora y en celular | **Manual** | Navegador (o Chrome con permiso para Claude) |
| Carrusel, aviso emergente, teclado, foco visible | **Manual** | Navegador |
| Modo claro/oscuro, menú contraíble y su preferencia guardada (fases 2+) | **Manual** | Navegador |
| Errores en la consola de JavaScript | **Manual** | Navegador (F12) |

### Recorrido manual sugerido al terminar cada fase

1. Sin sesión: abrir las 7 páginas públicas y una ruta inexistente.
2. `pendiente@example.test`: ver el aviso de cuenta pendiente; Mis citas debe redirigir a Inicio.
3. `admision1`: verificar la cuenta pendiente en **Cuentas por verificar**.
4. `paciente1@example.test`: pedir una cita, verla en **Mis citas**, cancelarla y volver a pedirla.
5. `admision1`: programar una solicitud y gestionar una cita.
6. `medico1`: ver **Mi día** y atender una cita del día.
7. `paciente1@example.test`: ver la atención en **Historia clínica**.
8. `admin`: abrir Usuarios, Horarios y Auditoría.
9. Cada cuenta: **Mi perfil** y **Salir**.
10. Repetir lo visual con el navegador angosto (≈ 375 px).

---

## 4. Registro por fase

| Fase | Fecha | `php -l` | Respuestas iguales a la sección 2 | Revisión manual | Observaciones |
|---|---|---|---|---|---|
| 0 — Estado inicial | 17/11/2026 | 53 archivos sin errores | Es la referencia | — | Respaldo de MariaDB: pendiente de confirmar por Joseph |
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |
| 4 | | | | | |
| 5 | | | | | |
| 6 | | | | | |
| 7 | | | | | |
