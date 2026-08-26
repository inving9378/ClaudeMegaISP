# Item #114 — "Refactor opcional: PermissionAssignmentModal.vue compartido + endpoint /api/permissions/all-grouped" (VERIFICACIÓN — ya resuelto)

## Premisa del item

Deuda técnica registrada en Ola 2 de permisos (2026-06-04): duplicación entre `PermissionUser.vue`
(328 líneas) y `PermissionRole.vue` (291 líneas), ambos con la misma lógica de catálogo/tabs/
acordeones. Proponía un modal compartido `PermissionAssignmentModal.vue` con prop `targetType` +
un endpoint nuevo `/api/permissions/all-grouped` con descripciones desde `module.json`.

## Hallazgo — el refactor ya está hecho y en `main`

El dedup se implementó y se mergeó a `main` el 2026-06-29, tres commits, ya ancestros de `HEAD`
antes de arrancar esta sesión (verificado con `git merge-base --is-ancestor`):

- `3042a86f` — `feat(admin): #114 PermissionAssignmentModal compartido (dedup user/role)`
- `2b901a55` — `refactor(admin): #114 PermissionUser.vue pasa a wrapper delgado del modal compartido`
- `8b93683e` — `refactor(admin): #114 PermissionRole.vue pasa a wrapper delgado del modal compartido`

`PermissionAssignmentModal.vue` (`resources/js/components/module/adminstration/`) concentra hoy
toda la lógica (tabs/acordeones, catálogo "Otros", `applyPermissions`, `preparePermissionsData`,
toggle "Agregar/Quitar todos", carga+guardado), parametrizada por `entityType` (`'user'|'role'`).
`PermissionRole.vue` quedó como wrapper de 13 líneas y es el que usa `ListarRol.vue` en producción
para asignar permisos a roles — **vivo y funcional**, verificado (`rg PermissionRole` → único
consumidor real).

## El endpoint `/api/permissions/all-grouped` — decisión ya tomada: NO duplicar

No se creó un endpoint nuevo con ese nombre. En su lugar, el modal compartido reusa el endpoint
**ya existente** `GET /administracion/permisos/catalog` (`PermissionController::catalog`,
`app/Modules/Core/Usuarios/Controllers/PermissionController.php`), que desde la Reforma B3 ya
devuelve `permissions` (nombres) + `contexts` (panel|portal) — cubre la necesidad de "catálogo
agrupado" que pedía el item, sin duplicar una fuente de permisos. Esto es consistente con la
convención del proyecto "SERVICIOS COMPARTIDOS ÚNICOS — PROHIBIDO DUPLICAR" (CLAUDE.md): un
endpoint nuevo que sirviera lo mismo habría sido la duplicación que el propio item buscaba eliminar.
La parte "descripciones desde `module.json`" no se implementó — el catálogo sigue usando
`constants.js` + la pestaña dinámica "Otros" (item #71, ya documentado en
`docs/modulos/usuarios.md`) — y no hay evidencia de que se necesite: no quedó ningún caso de uso
real sin cubrir.

## Hallazgo adicional — `PermissionUser.vue` quedó huérfano por una reforma posterior (B1.3)

Al verificar el estado actual, `PermissionUser.vue` (el wrapper de 13 líneas) ya **no tiene ningún
consumidor** en todo `resources/js` (`rg -ln "PermissionUser\b"` solo encuentra menciones en
comentarios de `PermissionAssignmentModal.vue`, no un uso real). Causa: la **Reforma de permisos
B1.3** (posterior al dedup) retiró el candado de permisos individuales por usuario — "el rol es la
única fuente de verdad" — y con eso:

- `UserListar.vue` ya no abre ningún modal de permisos por usuario (comentario explícito en el
  archivo: "Reforma de permisos B1.3: eliminado el candado de permisos individuales por usuario").
- Las rutas backend que ese wrapper necesitaba (`/administracion/permisos/get-permission-for-user/{id}`,
  `/administracion/permisos/update-permission-for-user/{id}`) están **comentadas/retiradas** en
  `app/Modules/Core/Usuarios/routes.php` (los métodos de controller correspondientes ahora abortan
  410).

Es decir: el archivo quedó como código muerto sin superficie de invocación posible (ni ruta viva,
ni consumidor Vue). Confirmado por grep exhaustivo (`app/`, `resources/`, `routes/`, `config/`):
cero coincidencias de uso real.

## Acción tomada en esta sesión

Se borró `resources/js/components/module/adminstration/user/PermissionUser.vue` (dead-code, cero
consumidores — regla del circuito: código muerto confirmado se remueve directo, no se consulta).
No se tocó `PermissionAssignmentModal.vue` (el branch `entityType==='user'` queda como generalidad
inerte del componente compartido — parametrización legítima, no específicamente "muerta"; tocar más
a fondo el modal compartido que sí sirve el flujo vivo de roles está fuera del alcance mínimo de
este cierre). Verificado: `bash deploy/circuito/npm-build.sh` compila sin errores tras el borrado.

## Conclusión

El objetivo central del item #114 (modal compartido, deduplicación user/role) **ya estaba resuelto
en `main`** desde el 2026-06-29. El endpoint sugerido no se creó a propósito (se reusó el existente,
evitando la duplicación que el item quería eliminar). Cierre de esta sesión: limpieza del último
residuo huérfano (`PermissionUser.vue`), expuesto por una reforma posterior no relacionada (B1.3).
