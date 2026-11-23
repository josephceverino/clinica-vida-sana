# Indicadores administrativos (Expotec 2026)

Página: `public/panel/indicadores.php` · Modelo: `app/modelos/indicadores.php` · Gráficos: `app/layout/graficos.php`

- **Quién la ve:** solo el rol `admin` (`requerir_login()` + `requerir_rol(['admin'])`). Admisión, médico y paciente reciben 403; sin sesión, va al login.
- **Qué lee:** solo `SELECT` con `COUNT`/`SUM` sobre `citas`, `solicitudes_cita`, `atenciones` y `areas`. No cambia ninguna tabla ni la lógica de citas.
- **Privacidad:** muestra solo cantidades y nombres de áreas. Nunca nombres de pacientes, documentos, diagnósticos ni notas.
- **Datos:** la clínica es simulada. Todo lo que hay en la base es ficticio (`sql/seed.sql` + lo creado al probar). La página lo avisa arriba, junto con la hora del cálculo.

## Período

| Filtro | Desde | Hasta |
|---|---|---|
| Este mes | día 1 del mes actual | último día del mes |
| Últimos 3 meses | día 1 de hace 2 meses | último día del mes actual |
| Últimos 6 meses (por defecto) | día 1 de hace 5 meses | último día del mes actual |
| Este año | 1 de enero | 31 de diciembre |
| Todo | primera fecha en `citas.fecha` o `solicitudes_cita.creado_en` | última fecha |
| Personalizado | fecha elegida | fecha elegida (máx. 24 meses; se valida en PHP) |

Los dos extremos se incluyen. Las fechas llegan por GET, se validan (`AAAA-MM-DD` real) y van como parámetros de consultas preparadas.

## Fórmulas

| Indicador | Fórmula (MariaDB) |
|---|---|
| Citas del período | `COUNT(*) FROM citas WHERE fecha BETWEEN :desde AND :hasta` |
| Atendidas / Canceladas | lo mismo con `estado = 'atendida'` / `'cancelada'` |
| % sobre las citas | `estado ÷ citas del período × 100`, redondeado a 1 decimal. Si el total es 0 se muestra «—» (no 0 %) |
| Pendientes | `estado IN ('pendiente', 'confirmada')` |
| Citas sin cerrar (aviso) | pendientes o confirmadas con `fecha < CURDATE()` dentro del período |
| Solicitudes en revisión | `solicitudes_cita` con `estado = 'nueva'` y `creado_en` dentro del período |
| Solicitudes resueltas | `estado = 'atendida'` (se programó una cita) + `estado = 'descartada'` |
| Distribución de estados | `GROUP BY estado` en `citas` del período; siempre se muestran los 5 estados del ENUM |
| Atenciones por especialidad | `atenciones JOIN citas ON citas.id = atenciones.cita_id JOIN areas`, por área, con `citas.fecha` en el período. Áreas públicas sin atenciones salen con 0 |
| Evolución mensual | por `DATE_FORMAT(citas.fecha, '%Y-%m')`: `COUNT(*)` de citas y `COUNT(atenciones.id)` (citas con atención registrada). Máximo 24 meses en el gráfico |

**Control de consistencia que hace la página:** compara «atenciones registradas» con «citas atendidas». Si no coinciden, lo avisa (una cita marcada como atendida sin atención en la historia clínica, o al revés).

## Pruebas

```bash
php app/pruebas/indicadores_prueba.php
```

Crea una base aparte (`cvs_pruebas_indicadores`) desde `sql/schema.sql`, carga datos con cantidades conocidas, compara cada indicador con un `COUNT` independiente, prueba bordes del período, base vacía, 5 000 citas en un mes y fechas inválidas, y **borra la base al final**. Nunca toca `clinica_vida_sana`.

## Rendimiento (Fedora, 2 GB)

- 6 consultas agregadas por carga de página; `citas` usa el índice `idx_citas_fecha`.
- Con 5 000 citas extra: ~15 ms y < 1 MB de memoria en PHP (prueba automática).
- Los gráficos son SVG armados en PHP: el navegador no descarga ninguna biblioteca ni hace peticiones extra. Funciona sin CDN y respeta la CSP (`default-src 'self'`): los colores van en `panel.css` como clases, no en atributos `style`.

## Accesibilidad y diseño

- Reutiliza las tarjetas (`.resumen-*`) y cajas del panel; claro y oscuro con variables propias en `.indicadores`.
- Paleta de las dos series validada para daltonismo (azul `#2A78D6` / naranja `#EB6834`; en oscuro `#3987E5` / `#D95926`). Leyenda siempre visible y valores escritos junto a cada barra: el color nunca es la única pista.
- Cada barra tiene un globito (`<title>`) y el gráfico mensual tiene su tabla de datos («Ver los datos en una tabla»).
- En el celular, el gráfico mensual se desplaza dentro de su caja en vez de achicar el texto.
