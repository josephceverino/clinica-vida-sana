# Clínica Vida Sana

Portal web de una **clínica simulada**, hecho para el curso **Administración de Servidores Multiplataforma** (Ingeniería de Sistemas, UCV, 2026).

> Todos los datos del proyecto son **ficticios**. No hay personas reales.

El curso evalúa la infraestructura (Fedora Linux + Windows Server). Esta web es el servicio HTTP que publica el servidor Fedora.

## Qué incluye

- **Zona pública:** inicio, servicios, nosotros, contacto y el botón **Portal del paciente**, que lleva al login.
- **Portal del paciente:** el paciente se registra (`/registro.php`) y entra con su correo. La cuenta nace **pendiente** hasta que admisión comprueba su identidad.
- **Zona privada (con login):** panel, pacientes, citas, solicitudes de cita y gestión de usuarios.
- **Roles:** `admin` (todo), `admision` (pacientes, citas y solicitudes) y `medico` (sus citas y datos básicos del paciente).

Las reglas de pacientes, citas y solicitudes (estados, conflictos de agenda) están en [`docs/backend.md`](docs/backend.md).

## Tecnologías

PHP 8.2+ sin frameworks, MariaDB con PDO, HTML/CSS/JavaScript puros y Apache.

## Estructura

```
public/     Lo único accesible desde el navegador
  panel/    Zona del personal (requiere login)
  errores/  Páginas 403 y 404
  assets/   CSS, JS e imágenes
app/        Lógica PHP (no accesible desde el navegador)
  core/     Motor: conexión, login, helpers, errores
  modelos/  Lógica de pacientes, citas y solicitudes
  layout/   Plantillas (cabecera y pie)
config/     Configuración (config.php NO se sube a GitHub)
sql/        schema.sql (tablas) y seed.sql (datos ficticios)
docs/       Plan de trabajo y guías del equipo
```

## Cómo probarlo en tu PC (Windows + XAMPP)

1. Instala XAMPP y deja el repo en `C:\xampp\htdocs\clinica-vida-sana`.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Importa `sql/schema.sql` y después `sql/seed.sql` (phpMyAdmin → Importar).
4. Copia `config/config.example.php` como `config/config.php` y revisa los datos de conexión.
5. Abre `http://localhost/clinica-vida-sana/public`.

Todos entran por el mismo login (`/login.php`): el personal con su **usuario** y los pacientes con su **correo**. Según el rol, el sistema los lleva al panel o al portal.

### Usuarios de prueba (solo desarrollo)

| Usuario | Rol | Contraseña |
|---|---|---|
| `admin` | admin | `Clinica2026!` |
| `admision1` | admision | `Clinica2026!` |
| `medico1` | medico (Medicina General) | `Clinica2026!` |
| `medico2` | medico (Pediatría) | `Clinica2026!` |

Cuentas de **paciente** (entran con su correo):

| Correo | Estado | Contraseña |
|---|---|---|
| `paciente1@example.test` | verificada | `Clinica2026!` |
| `pendiente@example.test` | pendiente de verificación | `Clinica2026!` |

Estas contraseñas son **solo para desarrollo**. En el servidor deben cambiarse.

## Seguridad

Consultas preparadas con PDO, contraseñas con `password_hash()`, todo lo que se imprime se escapa con `e()`, token CSRF en los formularios, sesiones con cookies `HttpOnly` y `SameSite=Lax`, y validación en el servidor. Las credenciales reales nunca van al repositorio.

## Flujo de trabajo

- `main` es lo que funciona y se publica. No se hacen commits directos en `main`.
- Una rama por tarea (`feature/...`, `fix/...`, `docs/...`).
- Commits pequeños, en español e imperativo.
- Cada rama se une a `main` con un Pull Request revisado por el otro integrante.

Más detalles en [`CLAUDE.md`](CLAUDE.md), [`docs/plan-de-trabajo.md`](docs/plan-de-trabajo.md), [`docs/guia-alexander.md`](docs/guia-alexander.md) y [`docs/backend.md`](docs/backend.md).

## Equipo

Joseph y Alexander.
