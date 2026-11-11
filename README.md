# Clínica Vida Sana

Portal web de una **clínica simulada**, hecho para el curso **Administración de Servidores Multiplataforma** (Ingeniería de Sistemas, UCV, 2026).

> Todos los datos del proyecto son **ficticios**. No hay personas reales.

El curso evalúa la infraestructura (Fedora Linux + Windows Server). Esta web es el servicio HTTP que publica el servidor Fedora.

## Qué incluye

- **Zona pública:** inicio, servicios, nosotros, contacto y formulario "Solicitar cita".
- **Zona privada (con login):** panel, pacientes, citas, solicitudes de cita y gestión de usuarios.
- **Roles:** `admin` (todo), `admision` (pacientes, citas y solicitudes) y `medico` (sus citas y datos básicos del paciente).

## Tecnologías

PHP 8.2+ sin frameworks, MariaDB con PDO, HTML/CSS/JavaScript puros y Apache.

## Estructura

```
public/     Lo único accesible desde el navegador (páginas, CSS, JS, imágenes)
  panel/    Zona privada (requiere login)
app/        Lógica PHP (conexión, login, helpers, plantillas)
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

El acceso del personal está en `/login.php`.

### Usuarios de prueba (solo desarrollo)

| Usuario | Rol | Contraseña |
|---|---|---|
| `admin` | admin | `Clinica2026!` |
| `admision1` | admision | `Clinica2026!` |
| `medico1` | medico | `Clinica2026!` |

Estas contraseñas son **solo para desarrollo**. En el servidor deben cambiarse.

## Seguridad

Consultas preparadas con PDO, contraseñas con `password_hash()`, todo lo que se imprime se escapa con `e()`, token CSRF en los formularios, sesiones con cookies `HttpOnly` y `SameSite=Lax`, y validación en el servidor. Las credenciales reales nunca van al repositorio.

## Flujo de trabajo

- `main` es lo que funciona y se publica. No se hacen commits directos en `main`.
- Una rama por tarea (`feature/...`, `fix/...`, `docs/...`).
- Commits pequeños, en español e imperativo.
- Cada rama se une a `main` con un Pull Request revisado por el otro integrante.

Más detalles en [`CLAUDE.md`](CLAUDE.md), [`docs/plan-de-trabajo.md`](docs/plan-de-trabajo.md) y [`docs/guia-alexander.md`](docs/guia-alexander.md).

## Equipo

Joseph y Alexander.
