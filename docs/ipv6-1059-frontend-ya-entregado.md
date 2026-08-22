# Item #1059 — Frontend IPv6 1.7a ya estaba entregado (sub-item redundante)

El item #1059 (sub-item de seguimiento de #999, creado cuando #999 quedó como paraguas con
"2 sub-items abiertos") pedía construir: blade standalone en `app/Modules/Addons/GestionRed/`
+ componente Vue `Ipv6ConfigPanel.vue` (Bootstrap 5) que consumiera los 4 endpoints de
`Ipv6ConfigController` (`routers`, `detectar-version`, `mapear-zonas`, `vista-previa`).

**Esa premisa ya no aplica: el frontend fue entregado antes, por otro camino.**

## Qué existe ya en `main` (verificado, no repetido)

- `resources/views/network/ipv6-config.blade.php` (commit `ffc2381a`, mergeado a main dentro
  de la rama `circuito/item-999-ipv6-17a-ruta-standalone-controlador`, merge commit
  `00b50811`) — extiende `core-layout::master`, gate `@can('ipv6.manage')`, renderiza
  `<ipv6-config></ipv6-config>`. Es la vista que `Ipv6ConfigController::index()` ya sirve
  (`return view('network.ipv6-config')` — **no** `addon-gestion-red::ipv6-config` como
  asumía el spec original del item; el backend real no pasó por el namespace de GestionRed).
- `resources/js/components/module/network/Ipv6Config.vue` (commit `903d011f`, mismo merge) +
  registrado en `resources/js/app.js:797` como `'ipv6-config': Ipv6Config`.
- El componente NO es el panel simple Bootstrap que describía el spec — es un wizard Quasar
  de 3 pasos (Alta del bloque → Mapeo de zonas → Vista previa/exportar), construido y ampliado
  después por los items **#1000** ("Pantalla 1 Alta + Pantalla 2 Mapeo de zonas") y **#1001**
  ("Pantalla 3 Vista previa + Pantalla 5 Exportar .rsc"), ambos ya integrados a main
  (commits `ee65b18d`/`47f45235`/`47bc9f76` y `6d28a0d8`/`f6ee29d9`).

## Verificación de que cubre el contrato pedido por #1059

- `GET /red/ipv6-config/routers` → puebla el `q-select` de routers (`loadRouters()`, llamado
  en `setup()` al montar).
- `POST /red/ipv6-config/detectar-version` → `detectarVersion()`; si falla, banner de error +
  `q-select` manual de respaldo (`forzarVersionManual`/`versionManual`) que alimenta el paso 3
  — cubre exactamente el "selector manual de respaldo" que pedía el item.
- `POST /red/ipv6-config/mapear-zonas` → `mapearZonas()`; pinta `vlans_candidatas`,
  `ppp_profiles_candidatos`, `queues_dedicadas_candidatas` en tabla de solo lectura y deriva
  `distritosDetectados` de `pppoe_servers_por_distrito` (equivalente a la "lista editable de
  zonas" del spec, aunque aquí es de solo confirmación, no textarea editable — decisión de
  diseño ya tomada y mergeada en #1000, no se revierte por criterio distinto).
- `POST /red/ipv6-config/vista-previa` → `vistaPrevia()`; banner fijo "vista previa SOLO
  LECTURA... nunca escribe en el router"; muestra plan/comandos/advertencias en `<pre>`
  (bloque "Comandos") + tabla diff + árbol del plan (ambos añadidos por #1001, superset de lo
  pedido).
- Errores de axios: los tres flujos capturan `e.response?.data?.error` y lo muestran en
  `q-banner` `bg-negative`, sin tumbar el resto del panel.
- Permiso `ipv6.manage` usado por el `@can` del blade existe y está asignado a
  `super-administrator` y `DESARROLLADOR` (los mismos roles que ya gatean la ruta por
  middleware) — confirmado en tinker.

## Conclusión

No hay nada que construir: el frontend que pedía #1059 ya está en `main`, funciona con el
backend real (no con el namespace de módulo que el spec original asumía sin haber verificado
el backend todavía en ese momento), y fue superado en alcance por #1000/#1001. Construir un
segundo componente Bootstrap paralelo (`Ipv6ConfigPanel.vue`) duplicaría UI para la misma
ruta y competiría con el wizard ya aprobado — va contra la regla de ESTABILIDAD (no reescribir
lo que ya funciona) y MINIMALISMO (no sumar una segunda pantalla para lo mismo).

**Sin cambio de código funcional.** Este documento deja la traza de por qué el item cierra sin
nuevo código, análogo al precedente de `docs/permisos-olt-item-414-verificacion.md` (#414).
