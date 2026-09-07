# [RESPUESTA] MR-27 — Ratificar: no retirar Mapas, activar MR-30 — item #9990446

## Contexto

`#963` (MR-27) llenó la rúbrica congelada de MR-29 (`#967`) con evidencia real contra la BD de
dev: los 6 criterios reprueban o no son medibles, y la causa raíz de 5 de los 6 es la misma —
**MR-05 (`#941`, comando de copia legacy → `mapared_*`) nunca se ejecutó**. Este item
(`#9990446`) pedía a Irving ratificar la recomendación resultante (no retirar Mapas, activar
MR-30) y decidir cómo tratar los residuos de prueba en `mapared_devices`/`mapared_devices_ports`
antes de correr MR-05.

## Decisión de Irving (ya registrada en el log del item, 2026-09-07 06:58:23)

Irving aprobó el item (`estado_aprobacion=aprobado_irving`) respondiendo las dos preguntas
estructuradas con la **opción recomendada** en ambas:

- **q1** — Ratificar "no retirar" + aprobar activar MR-30 + **desbloquear ejecución de MR-05
  (`#941`)** para poblar `mapared_*` desde legacy, y luego re-correr la rúbrica MR-29 con datos
  reales.
- **q2** — **Limpiar antes de correr MR-05**: borrar los 5 `mapared_devices_ports` huérfanos
  (apuntan a `device_id=4`, que no existe) y los 9 `mapared_devices` tipo "Charole" de prueba,
  para que la copia legacy sea la única fuente de datos.

## Verificación contra la BD real de dev (esta vuelta, 2026-09-07)

| Claim | Verificado | Resultado |
|---|---|---|
| 9 `mapared_devices` tipo "Charole" (residuo de prueba) | `SELECT id,name,type FROM mapared_devices` | ids `5,6,7,29,30,31,32,33,34`, todos `type=charole`, `origen_legacy_id` vacío — confirmado, ninguno viene de una copia legacy real |
| 5 `mapared_devices_ports` huérfanos (`device_id=4`) | `SELECT id,device_id FROM mapared_devices_ports` | ids `11,12,13,14,15`, todos `device_id=4`; **`device_id=4` no existe** en `mapared_devices` (huérfano real, FK rota) |
| 0 filas en las tablas dependientes | `mapared_layers/fibers/hilos/empalmes/splitters/enlaces_servicio/cables/proyects` | Los 8 en `0` — el borrado no tiene nada que arrastrar |
| `#941` (MR-05) sigue bloqueado del pool | `roadmap_items.941` | `estado_aprobacion=aprobado_revisor`, `excluir_pool_automatico=true`. Su log muestra el freno puesto el 2026-09-04 porque dependía de `#940` (MR-04), que en ese momento no estaba en `main` |
| La causa del freno de `#941` ya no aplica | `roadmap_items.940` | `estado_aprobacion=completado`, con `merge_commit` — MR-04 ya está en `main` desde el 2026-09-04 |

Todo lo que el item original describía coincide con el estado real; no se encontró ninguna
inconsistencia.

## Qué NO se pudo ejecutar en esta vuelta (y por qué)

Las dos acciones concretas que la decisión de Irving habilita son escrituras de datos:

1. `DELETE` de los 5 `mapared_devices_ports` huérfanos + 9 `mapared_devices` de prueba (q2).
2. `UPDATE roadmap_items SET excluir_pool_automatico=0 WHERE id=941` (q1, "desbloquear MR-05").

Ambas quedaron **bloqueadas por el clasificador de auto-mode de esta sesión** (deniega escrituras
directas vía tinker/SQL crudo, incluso tras la confirmación de Thomas para el borrado). Es un
control de la propia sesión ejecutora, no una objeción de negocio — ya está todo aprobado (Irving
para las dos acciones, Thomas para el borrado). No se intentó ningún rodeo: se documenta aquí para
que una sesión con permiso de escritura en BD (o Irving directamente) las aplique.

### Acciones exactas pendientes de ejecutar

```php
// 1) Limpieza de residuos de prueba (q2, ya aprobado por Irving)
DB::table('mapared_devices_ports')->whereIn('id', [11,12,13,14,15])->delete();
DB::table('mapared_devices')->whereIn('id', [5,6,7,29,30,31,32,33,34])->delete();

// 2) Desbloquear #941 (MR-05) del pool automático (q1, ya aprobado por Irving)
$i = \App\Modules\Addons\Roadmap\Models\RoadmapItem::find(941);
$i->excluir_pool_automatico = false;
$i->save();
```

## Qué se hace en este item

- Se re-verifica contra la BD real de dev todo lo que el item original afirmaba (tabla arriba).
- Se deja constancia formal, contra este item, de que la decisión de Irving ya está registrada y
  de exactamente qué falta ejecutar y por qué no se pudo en esta vuelta.
- Se crea el sub-item de seguimiento con el spec exacto (comandos arriba) para que una sesión con
  permiso de escritura los aplique sin tener que re-investigar nada.

## Qué NO se hace (y por qué)

- No se ejecuta MR-05 (`#941`) en sí — sigue fuera de alcance de este item (que solo ratifica y
  prepara el terreno), y de todas formas seguiría bloqueado por el freno del pool hasta que se
  aplique el paso 2 de arriba.
- No se retira el módulo Mapas ni se ejecuta MR-28 — la propia decisión ratificada dice
  explícitamente que NO se ejecuten.
