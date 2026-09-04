# MR-02 — Respaldo verificado de las tablas de Mapas + prueba de restauración (item #938)

Fecha: 2026-09-03. Entorno: DEV (`megaisp`). Objetivo: tener un respaldo del módulo Mapas del que
sepamos que se puede volver, y probar la restauración antes de que MR-32 (cascada de items del
módulo Mapas) empiece a mover datos — recordando el incidente del 25-ago donde `megaisp` quedó en
0 tablas (ver `docs/bitacora-sesiones.md` 2026-08-24).

## 0. Universo de tablas (38, no la BD completa)

Reusa el inventario ya auditado en `docs/mapas-inventario-item-1000000.md` (MR-01a, item
#1000000): las 39 tablas del módulo Mapas menos `MapConnection` (modelo huérfano, su tabla
`map_connections` no existe). Se verificó cada una contra `information_schema` antes de dumpear
(`Schema::hasTable`) — las 38 existen:

```
map_devices_ports, map_fibers, map_devices, map_devices_ports_connections, map_layers,
map_fibers_cut, map_layers_routes, map_proyects, map_ports, box_zones, system_map_credentials,
map_links, map_routes, active_equipments, active_equipment_types, active_equipment_peripherals,
boxes, box_inputs, box_types, brands, cards, cut_fibers, equipment_links, fibers,
passive_equipments, passive_equipment_types, poles, pole_accessories, ports, racks, sites,
splitters, transceivers, trays, trenche_types, trenches, tubes, tube_types
```

No se incluyeron los catálogos auxiliares fuera del módulo (`buffers`, `colors`, `zones`,
`points`, `point_accessories`, `positions`) — son compartidos/externos, tal como los deslindó
MR-01a.

## 1. Dump — solo tablas de Mapas, no la BD completa

Ruta usada: `/var/backups/mysql/` (la misma que usa `backup_db:process`, sin inventar esquema
paralelo).

```bash
mysqldump --defaults-extra-file=<credenciales .env> \
  --single-transaction --routines=FALSE --triggers=FALSE --add-drop-table \
  megaisp map_devices_ports map_fibers map_devices map_devices_ports_connections map_layers \
  map_fibers_cut map_layers_routes map_proyects map_ports box_zones system_map_credentials \
  map_links map_routes active_equipments active_equipment_types active_equipment_peripherals \
  boxes box_inputs box_types brands cards cut_fibers equipment_links fibers \
  passive_equipments passive_equipment_types poles pole_accessories ports racks sites \
  splitters transceivers trays trenche_types trenches tubes tube_types \
  | gzip > /var/backups/mysql/mapas-20260903-2007.sql.gz
```

Credenciales tomadas de `config('database.connections.mysql.*')` (nunca `env()` directo, regla
del proyecto) vía un `--defaults-extra-file` temporal `chmod 600`, borrado (`shred -u`) al
terminar — no quedó password en `ps aux` ni en el historial de shell.

**Resultado:** `/var/backups/mysql/mapas-20260903-2007.sql.gz`, 1,191,147 bytes, `gzip -t` OK, 38
bloques `-- Table structure for table` (uno por tabla, ninguna faltante). `mysqldump` emitió un
único warning inofensivo (`Access denied ... PROCESS privilege ... trying to dump tablespaces`,
ruido conocido de `mysqldump` cuando el usuario no tiene privilegio `PROCESS`; no afecta la data
dumpeada — exit code 0).

⚠️ **Nota operativa (a diferencia de `backup_db:process`):** este dump es de tablas específicas,
no de la BD completa, así que **no** entra en la retención automática de 14 días del comando
programado — es un artefacto puntual de este item. Si se quiere repetir el patrón después de MR-32
(post-migración), correr el mismo comando con un `STAMP` nuevo.

## 2. Restauración de prueba en `megaisp_restore` + verificación de conteos

`megaisp_restore` es la base de sandbox que ya existe para este propósito (usada antes en el
incidente del 25-ago, ver `docs/bitacora-sesiones.md` 2026-08-24). Se restauró el dump completo
ahí (el `--add-drop-table` de `mysqldump` hace `DROP TABLE IF EXISTS` + `CREATE TABLE` por tabla,
así que sobrescribe limpio cualquier copia previa de esas 38 tablas en `megaisp_restore` sin
afectar el resto de su esquema):

```bash
zcat /var/backups/mysql/mapas-20260903-2007.sql.gz | mysql --defaults-extra-file=<credenciales> megaisp_restore
```

Restauración: exit 0, sin errores.

**Conteo fila por fila, `megaisp` (origen) vs. `megaisp_restore` (restaurado), las 38 tablas:**

| Tabla | Origen | Restaurado |
|---|---:|---:|
| map_devices_ports | 19,229 | 19,229 |
| map_fibers | 17,242 | 17,242 |
| map_devices | 8,785 | 8,785 |
| map_devices_ports_connections | 8,740 | 8,740 |
| map_layers | 6,689 | 6,689 |
| map_fibers_cut | 3,596 | 3,596 |
| map_layers_routes | 2,130 | 2,130 |
| map_proyects | 1,715 | 1,715 |
| map_ports | 17 | 17 |
| box_zones | 1 | 1 |
| system_map_credentials | 1 | 1 |
| *(27 tablas restantes del inventario formal — Box/Pole/Site/Rack/Splitter/…)* | 0 c/u | 0 c/u |
| **TOTAL** | **68,145** | **68,145** |

`diff` entre los dos listados de conteos: **sin diferencias**. Coincide exacto con el inventario
de MR-01a (mismo total 68,145 documentado en `docs/mapas-inventario-item-1000000.md`).

**Verificación adicional más allá del conteo** (no pedía el DoD, pero confirma integridad de
contenido, no solo cantidad): `CHECKSUM TABLE` sobre las 4 tablas más grandes, origen vs.
restaurado — las 4 coinciden byte a byte:

| Tabla | Checksum origen | Checksum restaurado |
|---|---|---|
| map_devices_ports | 1631550883 | 1631550883 |
| map_fibers | 4112377621 | 4112377621 |
| map_layers | 2427729082 | 2427729082 |
| map_proyects | 84005160 | 84005160 |

## 3. Comando exacto de restauración de emergencia

Si `megaisp` pierde/corrompe las tablas de Mapas, la vuelta es:

```bash
zcat /var/backups/mysql/mapas-20260903-2007.sql.gz | mysql --defaults-extra-file=<credenciales .env> megaisp
```

(sustituir el nombre del `.sql.gz` por el respaldo vigente más reciente en
`/var/backups/mysql/mapas-*.sql.gz`). El dump trae `DROP TABLE IF EXISTS` por tabla — restaura
limpio incluso si las tablas quedaron en un estado intermedio/corrupto, sin tocar ninguna otra
tabla del sistema (el dump es exclusivamente de las 38 tablas de Mapas, nunca de la BD completa).

**Prerrequisito:** el usuario de BD de la app necesita permiso de escritura sobre `megaisp`
(`megaisp_user` ya lo tiene — es el usuario normal de la aplicación, sin necesidad de privilegios
extra para este restore).

## 4. Cumplimiento del "prohibido tocar `megaisp` más allá de lectura"

Todas las operaciones de escritura de esta verificación fueron sobre `megaisp_restore` (sandbox).
Sobre `megaisp` (dev) solo se hicieron: `mysqldump` (lectura, `--single-transaction`) y
`SELECT COUNT(*)` / `CHECKSUM TABLE` (lectura). Ninguna sentencia `INSERT`/`UPDATE`/`DELETE`/`DROP`
tocó `megaisp`.

## 5. Resumen

- ✅ Dump creado: `/var/backups/mysql/mapas-20260903-2007.sql.gz` (38 tablas del módulo Mapas,
  1.19 MB comprimido).
- ✅ Restauración probada en `megaisp_restore`: exit 0, sin errores.
- ✅ Conteos idénticos las 38 tablas (68,145 filas origen = 68,145 filas restaurado).
- ✅ Verificación extra por `CHECKSUM TABLE` en las 4 tablas más grandes: coincide byte a byte.
- ✅ Comando de restauración de emergencia documentado arriba (§3).
- ✅ `megaisp` (dev) solo se tocó en modo lectura durante toda la verificación.
