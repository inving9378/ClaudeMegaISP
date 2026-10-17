## 2026-10-15 12:45 — Auditoría (Paso 0, solo lectura) del re-sync Mapas → Mapa de Red en DEV

**Pedido:** prompt de Cowork "Re-sincronizar Mapas (legado) → Mapa de Red (mapared_*) en DEV":
`/mapa-red/api/projects` devolvía 0 nodos en Red y Proyectos mientras `/maps/projects` devolvía
~14,015. Paso 0 = auditar sin escribir, reportar y detenerse.

### Hallazgo 1 — Hoy el espejo NO está vacío y la API ya cuadra

Conteos exactos en `megaisp` (dev) a las 12:40, después de la restauración de prod de las 12:36
(`docs/bitacora/2026-10-15-restauracion-prod-en-dev.md`):

| Tabla | `map_*` (legado) | `mapared_*` (espejo) | Nuevas en legado | Huérfanas en espejo | Modificadas |
|---|---|---|---|---|---|
| proyects | 1,797 | 1,797 | 0 | 0 | 0 |
| layers | 7,043 | 7,041 | 2 | 0 | 20 |
| devices | 9,746 | 9,735 | 11 | 0 | 0 |
| devices_ports | 20,643 | 20,626 | 17 | 0 | 0 |
| fibers | 18,994 | 18,994 | 0 | 0 | 0 |
| fibers_cut | 3,840 | 3,832 | 8 | 0 | 0 |
| layers_routes | 2,321 | 2,319 | 2 | 0 | 1 |
| devices_ports_connections | 9,215 | 9,211 | 12 | 8 | 0 |
| ports | 17 | 0 | 17 (omitidas, `App\Models\MapSplitter`, ya conocidas) | 0 | — |

Además, entidades de `mapared:backfill`: `mapared_cables` 250, `mapared_hilos` 12,320,
`mapared_puertos` 10,987 (creadas en prod el 2026-10-11).

Mismo instante, misma BD, repositorios llamados directo:
`/maps/projects` = **14,023** nodos · `/mapa-red/api/projects` = **14,021** nodos. Red (1,797
carpetas + 19 capas), Clientes (3,379) y carpetas de Proyectos (1,798) idénticos; la única
diferencia son las 2 capas de Proyectos que existen en el legado y aún no en el espejo.
La caché `mapared:nodes:v1` dura 45 s: no es un factor.

### Hallazgo 2 — Por qué estuvo vacío (no fue un bug)

Recorrido de los respaldos diarios de dev (`/var/backups/mysql/megaisp-2026MMDD0200.sql.gz`):

- 02 al 08-oct: `mapared_layers`/`proyects` vacías (solo el catálogo `tipo_splitter`).
- 09 al 12-oct: 7,003 capas / 1,793 carpetas (alguien importó).
- 13-oct 02:00: espejo completo (7,041 capas + cables/hilos/puertos), la copia de prod del 11-oct.
- **13-oct 06:45: `TRUNCATE` intencional de las 25 tablas `mapared_*`**, por pedido directo, con
  respaldo previo → `docs/bitacora/2026-10-13-limpieza-mapared-dev.md` ("dev queda con Mapa de Red
  completamente vacío, listo para empezar de cero").
- 14-oct y 15-oct 02:00, y el respaldo de 12:27: espejo en 0.
- 15-oct 12:36: la restauración del dump de prod lo volvió a llenar con el espejo de prod.

La tabla `migrations` no cambió entre el 13 y el 14 (no hubo migraciones re-ejecutadas). El candado
`GuardBaseDePruebas` sigue cableado en `tests/CreatesApplication.php`. Nada que corregir en código.

### Otros datos

- `mapared:importar-legacy --dry-run`: exit 0, "no se escribió nada". **Ojo:** en dry-run el comando
  no consulta los ids existentes, así que reporta TODO como "Insertadas" (1,797 / 7,043 / …). El
  delta real es la tabla de arriba.
- Las 8 conexiones huérfanas del espejo (`mapared_devices_ports_connections` sin id en el legado)
  no se borran: el comando hace upsert por id.
- Respaldo de `mapared_*` tomado ahora (25 tablas):
  `storage/app/backups-mapared-red/mapared-pre-resync-2026-10-15.sql.gz` + `.sha256`.
  Respaldos de BD completos: `megaisp-202610151227.sql.gz` (dev antes de la restauración) y
  `megaisp-202610150604.sql.gz` (el dump de prod que hoy es la BD de dev).

### Estado

Detenido en el Paso 0, como pide el prompt. No se escribió nada en la BD. Pendiente de decisión:
dejar el espejo como está, ponerlo al día con `importar-legacy` (sin `--truncar`), o respetar la
limpieza del 13-oct y volver a vaciarlo.
