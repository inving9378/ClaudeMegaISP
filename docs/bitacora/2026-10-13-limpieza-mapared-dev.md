## 2026-10-13 06:45 — Limpieza de las 25 tablas `mapared_*` en dev (con respaldo previo)

### Contexto

Pedido directo: borrar los datos de Mapa de Red en dev (la copia fresca de producción importada
el 2026-10-12, ver `docs/bitacora/2026-10-11-mapared-backfill-prod.md`), con respaldo previo por
si hicieran falta después.

Antes de tocar nada se investigó el alcance: se confirmó que Mapa de Red usa EXCLUSIVAMENTE las
25 tablas con prefijo `mapared_*` (verificado contra `Schema::create()` de sus propias
migraciones). **Ninguna tabla `map_*`** (sin "red") pertenece a este módulo — esas 11 tablas son
del módulo separado y SIGUE ACTIVO "Mapas" (`addon-mapas`, rutas `/maps/*` en vivo) y no se
tocaron.

### Qué se respaldó

Conteo de filas antes de borrar:

| Tabla | Filas |
|---|---|
| `mapared_cables` | 250 |
| `mapared_devices` | 9,735 |
| `mapared_devices_ports` | 20,626 |
| `mapared_devices_ports_connections` | 9,211 |
| `mapared_fibers` | 18,994 |
| `mapared_fibers_cut` | 3,832 |
| `mapared_hilos` | 12,320 |
| `mapared_layers` | 7,041 |
| `mapared_layers_routes` | 2,319 |
| `mapared_proyects` | 1,797 |
| `mapared_puertos` | 10,987 |
| `mapared_tipo_cable` | 1 |
| `mapared_tipo_caja` | 3 |
| `mapared_tipo_conector` | 3 |
| `mapared_tipo_splitter` | 14 |
| (el resto: `correlativos`, `coverage_areas`, `empalmes`, `enlaces`, `enlaces_servicio`, `fotos`, `historial`, `ports`, `sectores_inalambricos`, `splitters`) | 0 cada una |

### Dónde quedó el respaldo

```
storage/app/backups-mapared-red/mapared-pre-limpieza-2026-10-13.sql.gz   (1.9 MB)
storage/app/backups-mapared-red/mapared-pre-limpieza-2026-10-13.sql.gz.sha256
```

Dump de `mysqldump` solo de las 25 tablas `mapared_*` (con su estructura e INSERT completos),
comprimido con `gzip`, verificado con `sha256sum` antes de borrar. **Vive en `storage/app/` del
propio checkout de dev** — no está en git (gitignored por convención de `storage/`), ni se movió
a un servidor externo. Si se necesita restaurar: `zcat mapared-pre-limpieza-2026-10-13.sql.gz |
mysql -u megaisp_user -h 127.0.0.1 megaisp`.

### Qué se hizo

`TRUNCATE TABLE` sobre las 25 tablas (con `FOREIGN_KEY_CHECKS=0` temporalmente, por las llaves
foráneas entre ellas; restaurado a `1` al terminar). Verificado: las 25 en 0 filas después.

### Estado

Dev queda con Mapa de Red completamente vacío (listo para empezar de cero), con el respaldo
completo de lo que había (la copia exacta de producción del 11-oct) disponible en disco por si
hiciera falta consultarlo o restaurarlo más adelante.
