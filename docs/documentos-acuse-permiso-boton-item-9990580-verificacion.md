# Item #9990580 — Permiso `exportar_acuse` + botón en el índice de Plantillas (RESUELTO — ya entregado por el propio #9990572)

## Contexto

El item #9990580 se creó como **sub-item de seguimiento de #9990572** ("Fase 3"), con spec
detallado para: (1) crear un permiso Spatie nuevo `documentacion.plantillas.exportar_acuse`
vía `PermissionSyncService::syncPermissionToBaseRoles` + declararlo en el `module.json` de
`Core/Documentos`, y (2) agregar un botón "Acuse de avance" en el índice de Plantillas
(`DocumentTemplateListar.vue`), gateado server-side y client-side, que descargue el PDF
generado por la Fase 2.

Irving aprobó el brief de decisión del item (3 preguntas, todas Opción 1): crear el permiso
y el botón (q1), asignar el permiso solo al nivel "admin" del sistema — es decir
`super-administrator` + `DESARROLLADOR`, el tier `FULL_ACCESS_ROLES` que ya trata este
codebase como equivalente (q2), y formato PDF con tabla de plantillas + encabezado + fecha de
emisión (q3).

## Hallazgo

Al investigar el código real de `main` antes de tocar nada, se encontró que **la propia sesión
que trabajó #9990572** (el mismo día, la tarde/noche del 2026-09-07, con autoría
`Irving MegaISP` en los commits) ya implementó las tres piezas completas, **sin decomponer en
un sub-item aparte**:

| Commit | Qué hace |
|---|---|
| `90166fb7` "Agrega permiso documentos.template.exportar_acuse (item #9990572)" | Migración `2026_09_08_000000_grant_documentos_exportar_acuse_permission.php`: `Permission::firstOrCreate(...)` + `PermissionSyncService::syncPermissionToBaseRoles(...)` + `forgetCachedPermissions()`. Declara el permiso en `app/Modules/Core/Documentos/module.json`. |
| `e1892fe9` "Genera acuse de avance en PDF del catálogo de plantillas (item #9990572)" | `DocumentTemplateController::exportarAcuse()` (Fase 2): arma la tabla de plantillas (nombre/tipo/estado Publicada-Borrador/autor/fecha) + resumen global (%avance) y genera el PDF vía dompdf, vista `export/acuse.blade.php`. |
| `991c22f4` "Agrega botón Acuse de avance en el índice de Plantillas (item #9990572)" | Botón "Acuse de avance" en `DocumentTemplateListar.vue::getButtonDatatable()`, gateado con `hasPermission.data.canView("documentos.template.exportar_acuse")`, `href` directo a la ruta GET con `target="_blank"`. |

Los tres commits ya están en `main` (verificado con `git merge-base --is-ancestor 90166fb7 HEAD`
→ `YES on main`) — **antes** de que este seguimiento (#9990580) llegara a ejecutarse. Es el
mismo patrón de carrera de timing ya documentado varias veces en `CLAUDE.md` (#733/#741/#753,
#9990003, #9990353): el sub-item de seguimiento nació de un spec escrito cuando el trabajo
todavía no estaba, pero la propia sesión del padre lo completó de corrido sin necesitar
descomponerlo.

### Única diferencia: el nombre del permiso

El spec de #9990580 asumía el nombre `documentacion.plantillas.exportar_acuse` (copiado del
título del item). La implementación real usa **`documentos.template.exportar_acuse`** —
consistente con el resto de permisos ya declarados para este mismo controlador
(`documentos.view/create/edit/delete`, ver `config/route_permission.php:1454-1481`). Verificado
que `documentacion.plantillas.exportar_acuse` **no existe en ningún archivo del repo** (0
matches) — no es un permiso duplicado ni huérfano, solo una diferencia de nombre entre el spec
especulativo y el nombre real elegido, coherente con la convención del módulo.

## Verificación (dev, solo lectura)

1. **Permiso en BD y asignación de roles** (tinker):
   ```
   Permission::where('name','documentos.template.exportar_acuse')->first()
   → id=820, existe.
   $permiso->roles()->pluck('name') → ["super-administrator", "DESARROLLADOR"]
   ```
   Coincide exactamente con la decisión de Irving en q2 (tier "admin" del sistema —
   `PermissionSyncService::FULL_ACCESS_ROLES`, el mismo patrón usado por el permiso de
   referencia `documentacion-corporativa.bitacora.export`).

2. **Botón gateado client-side**: `DocumentTemplateListar.vue:104-112` — el botón "Acuse de
   avance" solo se agrega al arreglo de botones del Datatable si
   `hasPermission.data.canView("documentos.template.exportar_acuse")` es verdadero.

3. **Ruta gateada server-side (403/denegación real, no solo UI oculta)**:
   `config/route_permission.php:1479-1482` registra
   `/administracion/document_template/acuse/exportar` **únicamente** bajo la llave
   `documentos.template.exportar_acuse` — no aparece en ningún otro bloque de permisos legado
   (`config_view_system` de la migración/documentos, líneas 1433-1442, no lo incluye). Revisado
   `CheckRoutePermission::handle()`: para un usuario autenticado sin admin/DESARROLLADOR/
   super-admin, si ninguna llave de `route_permission` con ese path matchea sus permisos, cae al
   bloque 5 (denegación) — mismo mecanismo que protege ya `documentos.view/create/edit/delete` en
   todo el resto del sistema. Nota: para navegación de página completa (como el `target="_blank"`
   del botón) el sistema aplica **denegación silenciosa** (redirect al Dashboard, decisión de
   Irving en el item #537) en vez de un 403 HTTP literal — es el comportamiento estándar del
   proyecto para TODAS las rutas protegidas por este middleware, no algo específico de este item;
   el efecto de seguridad real (nadie sin permiso descarga el PDF) es idéntico.

4. **Declaración en `module.json`**: `app/Modules/Core/Documentos/module.json:16` incluye
   `documentos.template.exportar_acuse` en el arreglo `permissions[]`, junto a
   `documentos.view/create/edit/delete`.

## Conclusión

Nada pendiente de la lista original de #9990580. El permiso, el endpoint PDF (Fase 2) y el
botón gateado (Fase 3) ya están en `main`, verificados de punta a punta en dev. **Sin cambio de
código** — el trabajo ya estaba hecho por la propia sesión de #9990572 antes de que este
seguimiento se ejecutara.
