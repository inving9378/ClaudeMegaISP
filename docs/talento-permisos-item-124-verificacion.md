# Item #124 — "Talento: revisar alcance de permisos talento.\*.view (asignados a 17 roles)" (VERIFICACIÓN — premisa desactualizada)

## Premisa del item

> Los permisos `talento.dashboard.view` / `escalafon.view` / `embajadores.view` se asignaron a
> TODOS los roles existentes (17) en la migración de Fase 8. Solo `talento.escalafon.manage`
> quedó restringido a super-administrator + DESARROLLADOR. Confirmar si el alcance amplio es
> intencional o si conviene restringir a roles técnicos. **NO modificar permisos hasta decidir.**

## Hallazgo — el alcance amplio ya no existe

La migración `2026_06_04_950300_seed_talento_fase8_permissions.php` (Fase 8, commit `52c50009`)
**nunca** asignó estos permisos a los 17 roles directamente: solo los otorga de forma explícita a
`super-administrator` + `DESARROLLADOR`. El alcance ancho que describe el item vino de un mecanismo
aparte: `module.json` de Talento declara estos 4 permisos como permisos del módulo, y
`PermissionSyncService::syncFromModuleManifests()` (invocado en cada install/upgrade de módulo)
repartía automáticamente cada permiso `*.view` a "todos los demás roles" — ese reparto amplio era
el comportamiento **default en 2026-06-04**, cuando se creó este item.

Ese mecanismo ya fue corregido, **de forma general para todo el sistema, no solo Talento**, por el
item #309 (commits `b9effb5f`/`c07497e1`/`4ef071f7`, 2026-07-10):
`config/permission_sync.php` → `auto_grant_view_base_roles` default cambió a `false`, y se agregó
`view_excluded_roles` (`client`, `conductor`, `PUBLICADOR`, `Socio`) para el reparto residual.

**Estado real verificado hoy (2026-08-26) contra la BD de dev**, de 17 roles totales:

| Permiso | Roles que lo tienen | Roles que NO lo tienen |
|---|---|---|
| `talento.dashboard.view` | super-administrator, DESARROLLADOR, Super Administrador, Administrador, ADMINISTRADOR_COMPLETO, **TECNICO, TECNICO_PLANTA, TECNICO_INSTALADOR** (8) | Almacen, CONTADOR, Mostrador, PUBLICADOR, SUPERVISOR_MOSTRADOR, Socio, Vendedor, client, conductor (9) |
| `talento.embajadores.view` | idéntico al anterior (8) | idéntico al anterior (9) |
| `talento.escalafon.view` | super-administrator, DESARROLLADOR, Super Administrador, Administrador, ADMINISTRADOR_COMPLETO (5 — solo roles admin) | los 12 restantes, incluidos los 3 técnicos |
| `talento.escalafon.manage` | super-administrator, DESARROLLADOR (2, como decía el item) | los 15 restantes |

No hay "17 de 17" en ningún permiso. El conjunto actual de `dashboard.view`/`embajadores.view` son
exactamente: los roles mega-admin (que por diseño acumulan cientos de permisos, ya registrados
aparte en la Hoja de Ruta como candidatos a consolidación — Fase 4 de permisos) **más** los 3 roles
técnicos reales (`TECNICO`, `TECNICO_PLANTA`, `TECNICO_INSTALADOR`). Ningún rol de mostrador, ventas,
almacén, contabilidad, cliente o legacy-sin-uso lo tiene. Es decir: **el sistema ya está en el
estado que el item proponía como alternativa** ("restringir a roles técnicos"), sin que nadie lo
haya tocado a mano — fue un efecto colateral correcto de la corrección general de #309.

`escalafon.view` quedó incluso más acotado que `dashboard.view`/`embajadores.view`: solo roles
admin, ningún técnico. Esto es consistente con que el escalafón es una tabla comparativa entre
técnicos (visibilidad gerencial), mientras que dashboard/embajadores son datos que el propio
técnico consulta sobre sí mismo. No hay señal de que esto sea un error — es una gradación más
estricta, no más laxa, así que no contradice el objetivo del item.

## Lo que NO se tocó (y por qué)

Tal como exige el item, **no se modificó ningún permiso**. La investigación confirma que el alcance
actual ya es el razonable (admin + técnicos reales, sin roles de oficina/ventas/legacy), así que no
hay una decisión pendiente de Irving que tomar aquí — la decisión de fondo (no auto-ensanchar
`.view` a roles base) ya se tomó y aplicó de forma general en el item #309.

## Conclusión

Sin cambio de código. La premisa del item (17/17 roles) corresponde al comportamiento que existía
el día en que se creó (2026-06-04, antes de #309) y ya no representa el estado real del sistema.
Verificado en vivo contra `role_has_permissions` de la BD de dev: 8/17, 8/17, 5/17 y 2/17 según el
permiso — ninguno "todos los roles". Item cerrado por investigación, no requiere restricción manual
adicional.
