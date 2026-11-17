# Plan de trabajo — Clínica Vida Sana

Checklist por fases. **No se avanza a la siguiente fase hasta que todos los ítems de la fase actual pasen.**

## Cómo se califica

Cada ítem se califica de **0 a 100**. Escribe tu nota en la columna **Puntos**.

| Puntos | Resultado | ¿Pasa? | Qué se hace |
|---|---|---|---|
| 0 – 39 | Malo | No | Se rehace. |
| 40 – 69 | Ok, pero no pasa | No | Se corrige lo que falta y se vuelve a calificar. |
| 70 – 100 | Pasa | Sí | Se da por terminado. |

**Reglas de la secuencia**
1. Una fase se aprueba cuando **todos** sus ítems tienen 70 o más (no vale el promedio).
2. Los ítems marcados con 🔒 son de **seguridad**: aunque el resto vaya bien, un 🔒 con menos de 70 detiene el avance.
3. La nota se pone después de **hacer la prueba** de la columna "Cómo se comprueba", no antes.
4. Cada ítem que se corrige se vuelve a calificar y se anota la fecha.

**Cómo escribir la nota:** reemplaza `__` por tu número (ejemplo: `85`) y en "Resultado" escribe Malo, Ok o Pasa.

---

## Fase 0 — Lo ya construido (calificar para confirmar)

Dueño: Joseph. Ya está hecho y subido a las ramas; falta tu calificación.

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 0.1 | Entorno local funcionando | Abrir `http://localhost/clinica-vida-sana/public` y ver la página sin errores. | __ | |
| 0.2 | Base de datos importada | Existen las 9 tablas en `clinica_vida_sana` y `seed.sql` cargó datos. | __ | |
| 0.3 | Plantilla base y página de inicio | Header, footer y portada se ven bien en PC y en celular (ancho angosto). | __ | |
| 0.4 | Páginas Servicios, Nosotros y Contacto | Las tres abren y los enlaces del menú funcionan. Servicios muestra las áreas de la base de datos. | __ | |
| 0.5 🔒 | Login y logout | Clave mala → error genérico. Clave buena → panel. Salir → vuelve al login y el panel queda cerrado. | __ | |
| 0.6 🔒 | Protección del panel y roles | Sin sesión, el panel redirige al login. `medico1` y `admision1` reciben 403 en Usuarios. | __ | |
| 0.7 | Panel (diseño y datos) | Muestra tarjetas y próximas citas con datos reales. El médico no ve "Solicitudes". | __ | |
| 0.8 🔒 | Gestión de usuarios | Crear, activar y desactivar. Contraseña guardada como hash. Un usuario desactivado pierde la sesión. | __ | |

**Fase 0 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Fase 1 — Revisión y unión a `main` (Joseph + Alexander)

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 1.1 | Alexander es colaborador del repo | Aparece en Settings → Collaborators como colaborador (no "Pending"). | __ | |
| 1.2 | Pull Requests abiertos en orden | Un PR por rama, en este orden: `base` → `servicios` → `nosotros` → `contacto` → `login` → `panel-maqueta` → `usuarios`. Cada uno con descripción. | __ | |
| 1.3 | Revisión cruzada | Alexander revisa y aprueba cada PR. Joseph revisa lo de Alexander. | __ | |
| 1.4 | `schema.sql` y `seed.sql` revisados por Alexander | Alexander confirma tablas y datos, y quita o ajusta los `DROP TABLE`. | __ | |
| 1.5 | Merge a `main` sin conflictos | Se usa "Create a merge commit" (no Squash). `main` abre igual que las ramas. | __ | |

**Fase 1 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Fase 2 — Parte de Alexander (backend de la clínica)

Dueño: Alexander. Usa la plantilla `panel_header.php` y las reglas de seguridad del CLAUDE.md.

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 2.1 🔒 | "Pedir cita" del portal del paciente (reemplaza al antiguo formulario público, que se retiró) | Reserva un horario libre (cita confirmada) o envía una solicitud a `solicitudes_cita`. El paciente sale de la sesión. Tiene CSRF, validación en el servidor y consulta preparada. Datos inválidos muestran errores. | __ | |
| 2.2 | Panel: Solicitudes | Lista las solicitudes nuevas y permite marcarlas como atendida o descartada. Solo admin y admisión. | __ | |
| 2.3 🔒 | Panel: Pacientes | Listar, buscar por documento o nombre, crear y editar. Documento único junto con su tipo (error claro si se repite). | __ | |
| 2.4 🔒 | Panel: Citas | Crear cita para un paciente, cambiar estado, ver por fecha. El médico solo ve sus citas del día. | __ | |
| 2.5 | Datos ficticios | No hay nombres, DNI ni teléfonos de personas reales en el seed ni en pantalla. | __ | |
| 2.6 🔒 | Todas las páginas del panel protegidas | Cada archivo de `public/panel/` empieza con `requerir_login()` (y `requerir_rol()` si aplica). | __ | |

**Fase 2 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Fase 3 — Despliegue en Fedora (Joseph)

Los cambios del servidor se hacen a mano; el código no toca la configuración de Apache.

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 3.1 | Código en `/var/www/clinica` | El repo está clonado y `git pull` funciona en el servidor. | __ | |
| 3.2 | MariaDB en Fedora | Se importaron `schema.sql` y `seed.sql`. Existe un usuario de base de datos propio (no `root`) con permisos solo sobre `clinica_vida_sana`. | __ | |
| 3.3 🔒 | `config.php` de producción | `debug` en `false`, `base_url` vacío, credenciales propias. No está en GitHub. | __ | |
| 3.4 | Apache con `DocumentRoot` en `public/` | `http://www.clinica.lan` carga el inicio. `app/` y `config/` **no** se pueden abrir desde el navegador. | __ | |
| 3.5 🔒 | HTTPS | `https://www.clinica.lan` funciona y HTTP redirige a HTTPS. | __ | |
| 3.6 | SELinux y permisos | La web funciona con SELinux activo (`restorecon` aplicado) y sin permisos 777. | __ | |
| 3.7 | `deploy.sh` | Un solo comando actualiza el servidor (git pull + permisos + restorecon) y se puede repetir sin romper nada. | __ | |
| 3.8 🔒 | Contraseñas de prueba cambiadas | `Clinica2026!` ya no sirve en el servidor. | __ | |
| 3.9 | Acceso desde otro equipo de la red | Desde un cliente (Windows) se abre `https://www.clinica.lan` (DNS o hosts). | __ | |

**Fase 3 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Fase 4 — Revisión de seguridad (Joseph + Alexander)

Se comprueban las 9 reglas del CLAUDE.md sobre el sistema completo.

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 4.1 🔒 | Regla 1 — Solo PDO con consultas preparadas | Buscar en el código SQL armado con variables del usuario: no debe haber ninguno. | __ | |
| 4.2 🔒 | Regla 2 — Contraseñas con hash | Ninguna contraseña en texto plano en la base ni en el repo. | __ | |
| 4.3 🔒 | Regla 3 — Todo se escapa con `e()` | Probar `<script>alert(1)</script>` en cada campo: se muestra como texto, no se ejecuta. | __ | |
| 4.4 🔒 | Regla 4 — CSRF en todos los POST | Cada formulario tiene `csrf_campo()` y su página llama a `csrf_verificar()`. | __ | |
| 4.5 🔒 | Regla 5 — Sesiones seguras | Cookie `HttpOnly` y `SameSite=Lax`; en HTTPS también `Secure`. | __ | |
| 4.6 🔒 | Regla 6 — Panel protegido | Abrir cada URL de `/panel/` sin sesión: siempre redirige al login. | __ | |
| 4.7 🔒 | Regla 7 — Sin secretos en GitHub | Revisar el historial del repo: no hay `config.php` ni contraseñas reales. | __ | |
| 4.8 🔒 | Regla 8 — Validación en el servidor | Enviar datos inválidos saltándose el navegador: el servidor los rechaza. | __ | |
| 4.9 🔒 | Regla 9 — Errores genéricos | Con `debug` en `false`, un error muestra mensaje genérico, sin detalles técnicos. | __ | |

### Pruebas hechas en local (17/11/2026)

Para que Joseph y Alexander califiquen con datos. Se hicieron con datos ficticios en XAMPP, enviando peticiones a mano (sin el navegador), y lo que se creó se borró al terminar.

| ID | Qué se probó | Resultado |
|---|---|---|
| 4.1 | Búsqueda en el código de SQL armado con datos del usuario. | Ninguno. La única consulta sin `prepare` es fija (zona horaria). |
| 4.2 | Las 8 contraseñas de la base y búsqueda de claves en el repo. | Todas son hash bcrypt. En el repo solo está la contraseña de prueba documentada. |
| 4.3 | `<script>alert(1)</script>` en una solicitud de cita. | Se ve como texto en "Mis citas" y en el panel de solicitudes. |
| 4.4 | Cada formulario POST y cada página que recibe POST. | Todos tienen `csrf_campo()` y todas llaman a `csrf_verificar()`. |
| 4.5 | Cabecera de la cookie de sesión. | `HttpOnly` y `SameSite=Lax`; `Secure` se activa solo con HTTPS (falta verlo en Fedora). |
| 4.6 | Las 16 páginas del panel sin sesión, con admin y con paciente. | Sin sesión → login; paciente → 403. |
| 4.7 | Historial de Git. | `config/config.php` nunca se subió. |
| 4.8 | 13 envíos manipulados: médico de otra área, horario falso, fecha pasada, área interna, texto largo, cancelar la cita de otro paciente, `paciente_id` falso, DNI con letras, rol inventado; y campos enviados como lista (`campo[]=x`) en todas las páginas. | Todos rechazados. Los campos como lista rompían el login: **corregido** (PR #13). |
| 4.9 | Error de conexión con `debug` en `false`. | Página "Algo salió mal", sin detalles técnicos. |
| Extra | 5 pacientes reservan la misma hora a la vez; un paciente pide 5 horas a la vez. | Solo uno consigue la hora; el paciente se queda en 3 citas (el máximo). |
| Extra | Límite de intentos: 5 por cuenta, 20 por IP, registro, y 30 intentos a la vez. | Bloquea bien. Los 30 a la vez dejaban probar 10 contraseñas: **corregido** (PR #14), ahora 5. |

Pendiente para Fedora: ver la cookie con `Secure` y repetir 4.9 con el `config.php` de producción.

**Fase 4 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Fase 5 — Diseño final y contenido

Se deja para el final, como acordamos.

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 5.1 | Logo e identidad | Logo propio en la web y en el panel. | __ | |
| 5.2 | Imágenes | Fotos o ilustraciones libres de derechos (o propias) en portada y secciones. | __ | |
| 5.3 | Textos finales | Revisados sin faltas y sin datos reales. | __ | |
| 5.4 | Animaciones suaves | Mejoran la lectura y no estorban. | __ | |
| 5.5 | Vista en celular | Todas las páginas se ven bien con ancho angosto. | __ | |
| 5.6 | Accesibilidad básica | Contraste legible, formularios con `label`, se puede navegar con teclado. | __ | |

**Fase 5 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Fase 6 — Entrega

| ID | Ítem | Cómo se comprueba | Puntos | Resultado |
|---|---|---|---|---|
| 6.1 | README claro | Explica qué es, cómo instalarlo y los usuarios de prueba. | __ | |
| 6.2 | Instalación desde cero | Clonar el repo en otra PC y seguir "Cómo probar" del CLAUDE.md: funciona. | __ | |
| 6.3 | Repositorio ordenado | `main` limpio, ramas viejas borradas, commits en español. | __ | |
| 6.4 | Prueba completa del flujo | Visitante se registra → admisión verifica su cuenta → el paciente reserva un horario libre → el médico ve la cita y registra la atención → el paciente la ve en su historia clínica. | __ | |
| 6.5 | Demostración ensayada | Ambos pueden explicar cada parte del sistema. | __ | |

**Fase 6 aprobada:** [ ] Sí  [ ] No — Fecha: ______

---

## Resumen

| Fase | Aprobada | Fecha |
|---|---|---|
| 0 — Lo ya construido | [ ] | |
| 1 — Revisión y merge | [ ] | |
| 2 — Backend de Alexander | [ ] | |
| 3 — Despliegue en Fedora | [ ] | |
| 4 — Seguridad | [ ] | |
| 5 — Diseño final | [ ] | |
| 6 — Entrega | [ ] | |
