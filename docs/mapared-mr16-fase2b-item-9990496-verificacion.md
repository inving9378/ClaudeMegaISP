# MR-16 Fase 2b — Verificación de DoD con enlace real de Tultitlán (item #9990496)

**Fecha:** 2026-09-07
**Alcance:** solo lectura, sin modificar datos de clientes/enlaces (spec del item).

## 1. Dependencia (Fase 2a) — ✅ cumplida

El sub-item hermano **#9990495** ("MR-16 Fase 2a — Botón 'Trazar ruta a OLT' + capa Leaflet
resaltada") ya está `completado` y mergeado a `main` (commit `9fe6cfd3`, integrado vía
`609c50d7`, toca `LeafletMapRed.vue` + `ElementSidePanel.vue` + `enlaces-request.js` +
`ProjectsComponent.vue`). No hace falta reprogramarse.

## 2. Búsqueda de un enlace real de Tultitlán — ❌ no hay ninguno (ni de otra zona)

```
mapared_enlaces_servicio: 0 filas
```

Confirmado con `SELECT COUNT(*)` directo. No es que falte un enlace *de Tultitlán* — la tabla
completa está vacía en dev hoy. El fallback que pedía el item ("si no hay de Tultitlán, usar el
más completo disponible de cualquier zona") tampoco tiene sobre qué aplicarse: no existe **ningún**
`MapaRedEnlaceServicio` en la base.

Ya se había documentado exactamente este mismo hallazgo en 2026-08
(`docs/mapared-comparativa-item-963-verificacion.md`, criterio 3), bloqueado en ese momento por
`#941` (MR-05). Esta vuelta reverifica que **sigue igual el 2026-09-07**, pese a que tanto `#941`
(MR-05) como `#951` (MR-15) ya están marcados `completado` en el roadmap.

## 3. Causa raíz (más profunda de lo que documentaba #963)

- `#941` (MR-05, `mapared:importar-legacy`) **sí corrió**: el espejo 1:1 del legado está poblado
  (`mapared_layers`=6689, `mapared_devices`=8785, `mapared_fibers`=17242, etc.).
- `#951` (MR-15, `mapared:backfill`) está marcado `completado` como entregable de **código**, pero
  el comando **nunca se ejecutó de verdad** contra la base de dev — `mapared_puertos` y
  `mapared_hilos` siguen en **0 filas**.
- `EnlacesServicioController::store()` exige `puerto_nap_id` con
  `exists:mapared_puertos,id` — con `mapared_puertos` en 0, **no se puede crear ni un enlace de
  prueba** aunque se quisiera (y el item lo prohíbe: "solo lectura, NO modificar datos").

Verificación adicional (100% de solo lectura, `php artisan mapared:backfill --dry-run`, sin
escribir nada) para dimensionar el impacto si algún día se corre de verdad:

```
Convertido: 1660 | Parcial: 226 | No convertible: 0
⚠ Zona 'TULTITLAN' con cobertura 50% (< 80%)
| TULTITLAN | Total 10 | Convertido 5 | 50% |
```

Es decir: incluso si se corriera el backfill real hoy, Tultitlán solo llegaría al 50% de
cobertura de puertos (5 de 10 cajas) — por debajo del 80% que el propio DoD de `#951` exige para
considerarla resuelta sin una nota de excepción. Y aun con esos puertos, seguiría faltando el paso
de crear el/los `mapared_enlaces_servicio` reales (alta operativa vía UI, no vía código).

## 4. Endpoint `GET /mapa-red/api/enlaces-servicio/{id}/trazo` — no ejecutable

Sin ningún `id` de `mapared_enlaces_servicio` existente, el paso 3 del item (llamar el endpoint y
confirmar que el trazo llega a `puerto_pon`) no tiene sobre qué correr. No se fuerza ni se inventa
un id — el item es explícito en no modificar/inventar datos.

## 5. Validación visual — no aplica

Sin un enlace real que abrir en la ficha del Mapa de Red, no hay pantalla que visitar para probar
el botón "Trazar ruta a OLT" con datos reales (la UI en sí, del lado de Fase 2a, ya quedó
verificada por su propio item).

## Conclusión

El DoD de MR-16 (#952, sigue `requiere_irving`) **no se puede verificar hoy con un enlace real**
— ni de Tultitlán ni de ninguna otra zona — porque la cadena de datos que lo alimenta
(MR-05 → MR-15 → alta real de enlace) está incompleta en dev: el espejo legado sí está, pero el
backfill a `mapared_puertos`/`mapared_hilos` nunca se ejecutó de verdad, y sin eso no puede existir
ningún `mapared_enlaces_servicio`. Esto no es un bug de código de este item ni de Fase 2a — es un
prerrequisito de datos pendiente, ya señalado en agosto por `#963` y reconfirmado aquí sin cambios.

**Siguiente paso real (fuera de alcance de este item de solo lectura):** que alguien con criterio
de negocio decida correr `mapared:backfill` de verdad (es idempotente y aditivo, no toca datos de
cliente) y luego dar de alta un enlace real de Tultitlán vía la UI/API existente, para que un
futuro intento de este mismo DoD sí tenga datos reales sobre los que verificar.

**Sin cambio de código** — este item fue de investigación read-only, tal como su spec lo pedía.
