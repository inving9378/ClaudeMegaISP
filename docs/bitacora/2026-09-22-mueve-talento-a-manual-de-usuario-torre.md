# Mueve el capítulo Talento de /empresa/manual a /manual (Manual de Usuario) + estilo Torre

## 2026-09-22 16:00 — Corrección de rumbo de Irving

Tras el renombre a "Manual Operativo de Meganet", Irving pidió lo contrario a lo que
había confirmado antes: sacar Talento de `/empresa/manual` y ponerlo en `/manual` (el
módulo "Manual de Usuario", `addon-manual`) — y de paso, ponerle el estilo Torre a esa
pantalla porque "está un poco fea".

### Por qué no fue un simple copiar-pegar

`/manual` es un sistema **completamente distinto** al que se usó antes:

| | `/empresa/manual` (usado antes) | `/manual` (destino nuevo) |
|---|---|---|
| Contenido | HTML crudo | **Markdown** — parser propio en el Vue, escapa cualquier HTML que se le mande |
| Estructura | capítulo → sección → versión | una fila por **pantalla** (`module_slug` único) |
| Navegación | índice generado del propio documento | **menú fijo** hardcodeado en el componente Vue |
| Estilo | propio (ya en Torre desde antes) | Bootstrap genérico, azul, sin modo oscuro |
| Visibilidad por rol | ya existía (recién construida) | no existía |

Cada fila de contenido tuvo que reescribirse en Markdown real (nada de `<p>`/`<ul>`
crudo, se habría visto como texto escapado literal), y hubo que **agregar** la entrada
"Talento" (30 pantallas) al menú fijo del componente — si no, el contenido existiría
en la base pero nadie podría llegar a él desde la barra lateral.

### Cambios

**Backend**
- `App\Support\Manual\HasManualRoleVisibility` (trait nuevo): la lógica de
  `ROLES_BYPASS`/`ROLES_ASSIGNABLE`/`ROLE_ADMIN_ONLY`/`isVisibleFor()` que antes vivía
  solo en el modelo de `/empresa/manual`, ahora compartida entre los dos sistemas —
  para no mantener dos copias de la misma regla.
- Migración: `visible_roles` (JSON nullable) en `manual_sections`.
- `ManualController::index()`/`show()`: filtran por `isVisibleFor()` igual que el otro
  manual; `index()` además agrupa por un fallback razonable ("talento-colaboradores" →
  grupo "Talento") cuando el `module_slug` no calza con ningún módulo real del
  catálogo — antes de esto, cualquier contenido agregado a mano habría caído sin
  agrupar bajo "General".
- Confirmado (leyendo `ManualGeneratorService::loadActiveModules()`) que la
  regeneración automática por Claude API solo toca `module_slug` que calzan con una
  fila real de `modules`/`module_registry` — los `talento-*` que se agregaron a mano
  quedan fuera de ese barrido, no se van a sobreescribir solos.

**Frontend (`ManualIndex.vue`)**
- Estilo Torre: `tc-wrap` + `tc-dark` en el elemento raíz (mismo patrón que Vendedores
  y Talento — Órdenes de Trabajo), toda la paleta propia del componente (azul
  `#0d6efd`, grises sueltos) reemplazada por los tokens `--tc-*` — claro y oscuro
  salen del mismo CSS, sin duplicar bloques.
- Soporte de imágenes en el parser de Markdown a mano (`![alt](src)`), que no existía
  — sin esto las 29 capturas no se habrían podido insertar.
- Menú fijo: nuevo grupo "Talento" con sus 30 pantallas (mismas etiquetas que antes),
  cada una con un `search` que calza exacto con el `module_slug` de su fila en BD.

**Contenido**
- `TalentoUserManualSeeder` (nuevo, reemplaza a `TalentoManualSeeder` que se borró):
  las mismas 30 pantallas, mismo mapeo de `visible_roles` por rol real, reescritas en
  Markdown, reusando las 29 capturas que ya existían en `public/images/manual/talento/`
  (no se volvieron a tomar).
- El capítulo Talento en `/empresa/manual` ya se había dado de baja (soft delete) en la
  vuelta anterior, antes de saber exactamente a dónde iba.

### Verificación
`php -l` limpio en todo lo PHP tocado; `npm run dev` compiló sin errores nuevos.
Pendiente: correr migración + seeder + `npm run prod` + verificación visual completa
(estilo Torre en claro/oscuro, las 30 pantallas navegables, filtro por rol) antes de
dar el cierre por bueno.
