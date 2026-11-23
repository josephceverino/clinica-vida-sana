# Clínica Vida Sana

Portal web de una **clínica simulada**, hecho para el curso **Administración de Servidores Multiplataforma** (Ingeniería de Sistemas, UCV, 2026).

> Todos los datos del proyecto son **ficticios**. No hay personas reales.

El curso evalúa la infraestructura (Fedora Linux + Windows Server). Esta web es el servicio HTTP que publica el servidor Fedora.

## Qué incluye

- **Zona pública:** inicio, servicios, nosotros, contacto y el botón **Portal del paciente**, que lleva al login.
- **Portal del paciente:** el paciente se registra (`/registro.php`) y entra con su correo. La cuenta nace **pendiente** hasta que admisión comprueba su identidad. Ya verificado, su inicio muestra su próxima cita, un resumen y su última atención; puede **reservar un horario libre**, cancelar sus citas (hasta 2 horas antes), ver su historia clínica y cambiar su contraseña.
- **Zona privada (con login):** panel, pacientes, citas, **agenda semanal** (calendario), **horarios de los médicos**, solicitudes de cita, cuentas por verificar, gestión de usuarios, **auditoría** (quién vio datos clínicos) y **Mi perfil** (cambio de contraseña).
- **Indicadores (solo admin):** citas, solicitudes y atenciones del período, con gráficos y filtros. Solo lectura y solo cantidades. Fórmulas en [`docs/indicadores.md`](docs/indicadores.md).
- **Recorrido de una cita:** el paciente elige área y día → ve los horarios libres de los médicos → reserva y la cita queda **confirmada** al momento → aparece en la agenda del médico y en "Mis citas" del paciente → el médico la atiende y queda en la historia clínica. Si no hay horarios, el paciente puede enviar una **solicitud** y admisión la programa.
- **Roles:** `admin` (todo), `admision` (pacientes, citas, solicitudes, verificar cuentas y marcar "no asistió") y `medico` ("Mi día" con su siguiente paciente y un gráfico de sus atenciones, su agenda y la historia clínica).

Las reglas de pacientes, citas, horarios y solicitudes (estados, citas que se cruzan, límites) están en [`docs/backend.md`](docs/backend.md).

## Tecnologías

PHP 8.2+ sin frameworks, MariaDB con PDO, HTML/CSS/JavaScript puros y Apache.

## Estructura

```
public/      Lo único accesible desde el navegador
  paciente/  Portal del paciente (mis citas, pedir cita, historia clínica)
  panel/     Zona del personal (requiere login)
  errores/   Páginas 403 y 404
  assets/    CSS, JS e imágenes
app/         Lógica PHP (no accesible desde el navegador)
  core/      Motor: conexión, login, helpers, errores, límites y auditoría
  modelos/   Lógica de pacientes, citas, horarios, solicitudes y cuentas
  layout/    Plantillas (cabecera y pie de cada zona)
config/      Configuración (config.php NO se sube a GitHub)
sql/         schema.sql (tablas), seed.sql (datos ficticios) y migraciones
docs/        Plan de trabajo, guías del equipo y despliegue
deploy.sh    Actualiza el servidor Fedora
```

## Cómo probarlo en tu PC (Windows + XAMPP)

1. Instala XAMPP y deja el repo en `C:\xampp\htdocs\clinica-vida-sana`.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Importa `sql/schema.sql` y después `sql/seed.sql` (phpMyAdmin → Importar).
4. Copia `config/config.example.php` como `config/config.php` y revisa los datos de conexión.
5. Abre `http://localhost/clinica-vida-sana/public`.

> Importar `schema.sql` **borra** todas las tablas y las crea vacías. Si después no importas `seed.sql`, la web queda sin datos y nadie puede entrar.

Todos entran por el mismo login (`/login.php`): el personal con su **usuario** y los pacientes con su **correo**. Según el rol, el sistema los lleva al panel o al portal.

### Usuarios de prueba (solo desarrollo)

| Usuario | Rol | Contraseña |
|---|---|---|
| `admin` | admin | `Clinica2026!` |
| `admision1` | admision | `Clinica2026!` |
| `medico1` | medico (Medicina General) | `Clinica2026!` |
| `medico2` | medico (Pediatría) | `Clinica2026!` |
| `medico3` | medico (Laboratorio) | `Clinica2026!` |

Cuentas de **paciente** (entran con su correo):

| Correo | Estado | Contraseña |
|---|---|---|
| `paciente1@example.test` | verificada (Ana, con citas, historial y solicitudes) | `Clinica2026!` |
| `paciente2@example.test` | verificada (Carlos; sirve para comprobar que un paciente no ve lo de otro) | `Clinica2026!` |
| `pendiente@example.test` | pendiente de verificación | `Clinica2026!` |

Estas contraseñas son **solo para desarrollo**. En el servidor deben cambiarse.

### Pruebas automáticas (terminal)

```bash
php app/pruebas/indicadores_prueba.php   # indicadores: crea y BORRA su propia base de pruebas
```

## Publicar en el servidor (Fedora)

La guía paso a paso está en [`docs/despliegue.md`](docs/despliegue.md). Después de la primera instalación, cada actualización es:

```bash
cd /var/www/clinica && sudo ./deploy.sh
```

`deploy.sh` actualiza el código, pero **no la base de datos**: si avisa que cambió `sql/`, sigue el paso 7.1 de la guía.

## Seguridad

- Consultas preparadas con PDO; contraseñas con `password_hash()`.
- Todo lo que se imprime se escapa con `e()`; token CSRF en todos los formularios.
- Sesiones con cookies `HttpOnly` y `SameSite=Lax` (y `Secure` en HTTPS). Cambiar la contraseña cierra las otras sesiones abiertas.
- Validación en el servidor, también ante datos manipulados.
- Límite de intentos de login y registro, guardado en la base (borrar la cookie no lo salta).
- Auditoría de quién consultó datos clínicos.
- Errores genéricos al usuario; las credenciales reales nunca van al repositorio.

Las 9 reglas se comprobaron una por una; los resultados están en la fase 4 de [`docs/plan-de-trabajo.md`](docs/plan-de-trabajo.md).

## Flujo de trabajo

- `main` es lo que funciona y se publica. No se hacen commits directos en `main`.
- Una rama por tarea (`feature/...`, `fix/...`, `docs/...`).
- Commits pequeños, en español e imperativo.
- Cada rama se une a `main` con un Pull Request revisado por el otro integrante.

Más detalles en [`CLAUDE.md`](CLAUDE.md), [`docs/plan-de-trabajo.md`](docs/plan-de-trabajo.md), [`docs/guia-alexander.md`](docs/guia-alexander.md) y [`docs/backend.md`](docs/backend.md).

## Equipo

Joseph y Alexander.
