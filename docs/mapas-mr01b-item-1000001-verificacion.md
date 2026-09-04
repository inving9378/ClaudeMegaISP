# MR-01b — Mapas: entidades de puerto/hilo, conteo por tipo de elemento y árbol de carpetas

Auditoría READ-ONLY (item roadmap #1000001, sub-item de seguimiento de #937, puntos 2, 4 y 6 de
su prompt). Cero escrituras a código/esquema/datos de la aplicación — solo `SELECT`/lectura de
modelos, controllers y migraciones. Verificado contra la BD de dev el 2026-09-03.

---

## (2) ¿Puerto e hilo son entidad propia o solo un contador/texto?

Conviven **dos sistemas** en el módulo Mapas:

- **Legacy** — tablas sin prefijo `map_` (`ports`, `fibers`, `splitters`, `box_inputs`, `tubes`,
  `trays`, `box_types`, `boxes`, `passive_equipment_types`, `active_equipment_types`,
  `passive_equipments`, `active_equipments`). Rutas y controllers siguen registrados
  (`BoxController`, `PortController`, `SplitterController`, `BoxTypeController`, …), pero **las
  11 tablas están en 0 filas en dev** — sin datos vivos hoy.
- **Live** — tablas con prefijo `map_` (`map_devices`, `map_devices_ports`, `map_ports`,
  `map_fibers`, `map_proyects`, …). Es el sistema que realmente contiene la red mapeada.

### Puerto — SÍ es entidad propia (tabla + columnas), en ambos sistemas

- Legacy: `ports` (`id, number, type, portable_id, portable_type` — polimórfica sobre
  `Box`/`Splitter`/`Tray`; 0 filas).
- Live: `map_devices_ports` (**19,229 filas**: `id, name, type, orientation, device_id, client_id,
  connected, transfer, transfer_type, card, note, zone, data`) — puerto de cualquier `map_device`
  (OLT/rack/organizer/switch/router/splitter/etc), `belongsTo(MapDevice::class, 'device_id')`.
- Live (variante): `map_ports` (**17 filas**: `id, name, type, splitter_id, connected,
  position_x/y, orientation, transfer, transfer_type, note`, con `device()` polimórfico vía
  `device_type`/`device_id`). En dev, **el 100% de sus filas tiene `device_type =
  App\Models\MapSplitter`** — hoy es la tabla de puertos IN/OUT específica de splitters,
  separada de `map_devices_ports`.
- Ninguna variante es un contador entero suelto: cada puerto es una fila individual con su propio
  `id`.

### Hilo (fibra/strand) — SÍ es entidad propia (tabla + columnas), en ambos sistemas

- Legacy: `fibers` (`id, number, color_id, buffer_id` — cada hilo pertenece a un `Buffer`; 0
  filas) + `buffers`/`colors` como catálogo de color.
- Live: `map_fibers` (**17,242 filas**: `id, parent_buffer, buffer, number, color, fiber_id`
  [FK a `map_layers.id`, la ruta/tendido], `zone`) — cada hilo de cada tubo de cada ruta es una
  fila individual.
- `grep -ri 'hilo\|strand'` en `app/Models` y `app/Modules/Addons/Mapas` solo encuentra
  `MapLayer.php` (columna no relacionada a fibra). No existe ninguna columna `fiber_count` ni
  `capacity` en ningún modelo/migración de Mapas.

### Hallazgo colateral — el patrón capacidad-entero → filas reales

`Splitter.outputs` (legacy, entero) y `box_types.{inputs,trays,mergers_by_tray,ports}` (legacy,
enteros) **no son contadores decorativos**: `PortRepository::createByBox()` (`app/Repositories/PortRepository.php:29-39`),
`TrayRepository::createByBox()` (`app/Repositories/TrayRepository.php:46-64`) y
`BoxInputRepository::createByBox()` (`app/Repositories/BoxInputRepository.php:27-35`) los leen
como capacidad declarada en el catálogo y generan, en un `for`, **exactamente esa cantidad** de
filas reales (`Port`/`Tray`/`BoxInput`) al dar de alta una caja — igual `SplitterController::store`
(`app/Modules/Addons/Mapas/Controllers/Mapas/SplitterController.php:32-45`) crea 1 `splitter_in` +
`$object->outputs` filas `splitter_out` en `ports`. El entero es la ESPECIFICACIÓN del catálogo;
la fila es la INSTANCIA real. Este patrón vive en el sistema legacy (vacío en dev); no se localizó
en el tiempo de esta auditoría un generador equivalente para el sistema `map_*` vivo (sus altas
parecen originarse directo desde el canvas, request por request).

---

## (4) Conteo por tipo de elemento

Los catálogos nombrados en el ítem (`box_types`, `passive_equipment_types`,
`active_equipment_types`) existen con las columnas esperadas (`type`, `model`, `brand_id`, …) pero
están **vacíos en dev** (0 filas cada uno), igual que sus tablas de instancia (`boxes`,
`passive_equipments`, `active_equipments`: 0 filas). No hay nada que agrupar ahí hoy.

El catálogo con datos vivos es `map_devices` (**8,785 filas**), tipado por la columna `type`
(string libre, sin tabla de catálogo aparte). Los nombres reales — que **no coinciden** con los
asumidos en el ítem (`pack, cupboard, junction_box, source`, que son literalmente tablas
comentadas y nunca creadas en la migración `2025_05_09_063649_add_layer_to_map.php`, líneas
57-95) — son:

| `map_devices.type` | filas |
|---------------------|------:|
| charole              | 5675 |
| client                | 1533 |
| splitter              | 1035 |
| drop                  |  518 |
| organizer             |   14 |
| rack                  |    6 |
| olt                   |    2 |
| router                |    1 |
| switch                |    1 |

### Huérfanos (parent_id/box_id apuntando a un padre que no existe)

Con las tablas legacy vacías el chequeo ahí es trivialmente 0/0. En el sistema vivo con datos
reales (`LEFT JOIN … WHERE padre.id IS NULL AND child.<fk> IS NOT NULL`):

| Relación | con FK no nulo | con FK NULL | huérfanos (apunta a id inexistente) |
|---|---:|---:|---:|
| `map_devices.parent_id` → `map_devices.id` | 2,574 | 6,211 | **0** |
| `map_devices_ports.device_id` → `map_devices.id` | 19,229 | 0 | **0** |
| `map_fibers.fiber_id` → `map_layers.id` | 17,242 | 0 | **0** |

Conclusión: la integridad referencial del sistema vivo está sana (cero huérfanos detectados en
las tres relaciones con datos reales); el catálogo legacy con los nombres que el ítem asumía está
vacío/sin uso en dev.

---

## (6) Árbol de carpetas

La tabla de carpetas es **`map_proyects`** (self-referencing `parent_id`, más `classification` y
`level`). Confirmación semántica: `MapProyect::getIconAttribute()`
(`app/Models/MapProyect.php:113-116`) devuelve literalmente `'mdi-folder-outline'` — es la
entidad "carpeta" que consume la UI del árbol de proyectos.

- **1,715 filas totales**, 4 raíces (`parent_id IS NULL`): `PRUEBA 2` (id 4), `Transformacion`
  (id 611), `Mapeo de Vendedores` (id 1457), `ODN MEGA ISP` (id 1493) — todas `classification =
  'project'`.
- **Profundidad máxima real: 11 niveles** (CTE recursiva `WITH RECURSIVE`, MySQL 8.4.6).
- Distribución por nivel (profundidad → nº de carpetas): 1→4, 2→14, 3→178, 4→214, 5→211, 6→455,
  7→392, 8→164, 9→45, 10→27, 11→11. La suma da 1,715 = total de filas de la tabla → confirma que
  el árbol recorrido cubre el 100% de las filas, sin ciclos ni huérfanos que rompan la recursión
  (consistente con la tabla de huérfanos del punto 4, que ya daba 0 para `map_devices.parent_id`;
  para `map_proyects.parent_id` el mismo chequeo también dio 0 huérfanos).
- **Carpetas con exactamente un hijo: 306 de 1,715 (≈18%)** (`GROUP BY parent_id HAVING
  COUNT(*) = 1`).

---

## Metodología

Todas las cifras se obtuvieron con `php artisan tinker` corriendo `SELECT`/`Schema::` de solo
lectura contra la BD de dev (`192.168.105.11`), sin abrir transacciones de escritura ni tocar
migraciones. Comandos y resultados completos quedan en el historial de la sesión del item
#1000001 (`comentarios_claude` del item).
