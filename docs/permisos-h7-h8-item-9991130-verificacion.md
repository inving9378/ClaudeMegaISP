# Fase 3a — verificación H7 (`@can` en Blade) y H8 (gating hardcodeado por rol)

Item: #9991130 (sub-item de #9990744, rama de #9990721 "permisos" — caso Diana/SUPERVISOR_MOSTRADOR)
Fecha: 2026-09-14 · Entorno: DEV (192.168.105.11) · Alcance: **SOLO LECTURA**, cero cambios en
permisos/roles/asignaciones/código.

## Resumen (lenguaje llano)

**Ninguna de las dos hipótesis explica el síntoma de Diana.** `@can()` SÍ funciona correctamente
en Blade en este proyecto (lo probé en vivo, no solo grepeando) — la afirmación de que "nunca
funciona" es una convención repetida en 3 comentarios de código que **contradice** tanto lo
documentado en la skill `megaisp-conventions` como el comportamiento real medido. Y no existe
ningún gating hardcodeado por el nombre del rol `SUPERVISOR_MOSTRADOR` ni `Mostrador` en todo el
código — cero ocurrencias.

## H7 — `@can()` en Blade

### Paso previo obligatorio: ¿lo documenta CLAUDE.md?

`grep -n "@can" CLAUDE.md` → 3 resultados (líneas 102, 1001, 1023), **ninguno afirma que `@can`
esté roto**. Los tres lo mencionan como directiva EN USO (sidebar de Flotas, `@canany`/`@can`
guardando menús, verificado renderizando como admin). **La premisa del item original
("según el item original NO funciona en este proyecto — convención documentada en CLAUDE.md")
es FALSA: CLAUDE.md no contiene esa afirmación.**

### ¿De dónde sale entonces la convención "nunca @can()"?

De 3 comentarios de código (no de CLAUDE.md):
- `app/Modules/Addons/VozMayorista/views/panel.blade.php:6` — "Nunca @can() (convención del proyecto)."
- `app/Modules/Core/Layout/views/sidebar.blade.php:142` — "NO @can(): usar auth()->user()->can()"
- `app/Modules/Core/Layout/views/topbar.blade.php:125-126` — más específico: "Se usa
  `auth()->user()->hasRole(...)` y no `@role`/`@can`, que **en este layout** no evalúan."

Y **contradiciéndolos directamente**, `.claude/skills/megaisp-conventions/SKILL.md:30`:
> "`@can()` en Blade SÍ funciona y se usa ampliamente (sidebar, tablas de acciones, ~40 usos).
> No asumir que está roto; si un caso puntual falla, verificar primero que el permiso
> exista/esté sincronizado antes de descartar la directiva."

Dos fuentes vivas del propio repo se contradicen entre sí.

### Grep exacto pedido por el item

```
grep -rn "@can(" resources/views/ app/Modules/
```

**6 resultados**, de los cuales **solo 1 es uso real y vivo de la directiva** en una vista Blade:

| # | Archivo:línea | Tipo | Nota |
|---|---|---|---|
| 1 | `app/Modules/Core/CRM/views/index.blade.php:12` | **USO REAL** | `@can('crm_document_view_huerfanos')` gatea el botón "Documentos huérfanos" en `/crm/listar`. Convive en el MISMO archivo (línea 8) con el patrón "correcto" `@if(auth()->user()->can(...))` — inconsistencia interna, no evidencia de que uno falle. |
| 2 | `app/Modules/Addons/Roadmap/Console/SembrarMapaRedCommand.php:334` | Texto de spec (heredoc) | Instrucción para un item futuro ("nunca `@can()`"), no código ejecutable. |
| 3 | `app/Modules/Addons/VozMayorista/views/panel.blade.php:6` | Comentario | Explica por qué se evitó `@can()` ahí; no es uso de la directiva. |
| 4 | `app/Modules/Core/Layout/views/sidebar.blade.php:142` | Comentario | Idem. |
| 5-6 | `app/Modules/Core/Security/Console/AuditarPermisosCommand.php:17,481` | Texto de criterio de auditoría | Lista `@can(` como patrón a detectar, no lo usa. |

### Prueba empírica (no solo grep) — `@can()` SÍ evalúa correctamente

Se renderizó un archivo Blade real (`view()->file()`, pipeline idéntico al de producción — NO
`Blade::render()` de tinker, que usa un compilador aislado y dio un falso negativo de sintaxis en
un primer intento) con:

```blade
@can('user_view_user')
SI-VISIBLE
@endcan
```

| Usuario | ¿Tiene `user_view_user`? | Resultado del render |
|---|---|---|
| `admin` | Sí | `SI-VISIBLE` |
| `Diana` (login_user=Diana, id=3) | No (hoy) | *(vacío)* |

El resultado es el correcto en ambos casos: la directiva `@can`/`@endcan` compila a
`Gate::check()`/`endif` (core de Laravel, `CompilesAuthorizations` trait, sin overrides — se
grepeó `Blade::if(`/`Blade::directive(`/`Blade::extend(` en todo `app/` y no hay ninguno) y
evalúa el permiso real del usuario autenticado en cada request.

### Veredicto H7: **DESCARTADA**

`@can()` SÍ funciona en Blade en este proyecto — confirmado con evidencia dura (compilación real
+ render con dos usuarios distintos, uno con el permiso y otro sin él, resultado correcto en
ambos). La única ocurrencia viva de la directiva en todo el árbol de vistas gatea un permiso
(`crm_document_view_huerfanos`) que **no** es ninguno de los 10 permisos netos que el diagnóstico
de Fase 1 (#9990743) identificó para el caso Diana/SUPERVISOR_MOSTRADOR. H7 no explica el síntoma.

La convención "nunca `@can()`" repetida en 3 comentarios es, con la evidencia disponible, una
superstición de equipo que contradice tanto la skill oficial del proyecto como el comportamiento
medido — **no es la causa raíz**, y tampoco hay evidencia de que sea inofensiva universalmente:
el comentario de `topbar.blade.php` afirma algo más acotado y distinto ("en este layout no
evalúan") que no se pudo reproducir con la prueba genérica de arriba porque haría falta simular
el layout completo (múltiples `ViewComposer`s, `@include`s anidados) — queda fuera del alcance de
esta Fase 3a (que es sobre H7/H8 en general, no una auditoría dedicada de `topbar.blade.php`); si
se quiere cerrar esa duda puntual, es candidato a un sub-item propio y acotado.

## H8 — Gating hardcodeado por rol en vez de por permiso

### Grep exacto pedido por el item

```
grep -rn "hasRole(\|hasAnyRole(\|super-administrator\|DESARROLLADOR" app/ resources/views/ routes/
```

**184 resultados.** Desglose:
- **20** son `hasRole(`/`hasAnyRole(` con `super-administrator` o `DESARROLLADOR` — el bypass de
  administrador documentado como diseño en CLAUDE.md (Fase 3a, "Bypass por nombre de rol... y
  short-circuit `isAdmin()` del trait intacto"). No es el bug que este ítem busca.
- El resto son ocurrencias de `super-administrator`/`DESARROLLADOR` como STRING en contextos no
  relacionados con gating de acceso (seeders, migraciones, comentarios, asignación de permisos a
  esos roles) o coinciden por ser substring de otra palabra en el archivo.
- **11 líneas** son `hasRole(`/`hasAnyRole(` con un rol **distinto** a super-administrator/DESARROLLADOR:

| Archivo:línea | Rol consultado | Relevancia para Diana |
|---|---|---|
| `app/Services/Tenant/CurrentClientResolver.php:53` | `INTERNAL_ROLES` (lista) | Resuelve tenant scope, no gating de feature |
| `app/Modules/Addons/Vendedores/Controllers/Vendors/SellerController.php:84` | `Vendedor` | Lógica de vendedor, no compite con SUPERVISOR_MOSTRADOR |
| `app/Modules/Addons/WhatsAppAgent/Controllers/WhatsAppPanelController.php:33,301` | `SELLER_ROLE` (constante) | Idem |
| `app/Modules/Addons/MegaFamilia/Controllers/ApiController.php:263` | variable `$r` (lista de roles MegaFamilia) | Módulo no relacionado |
| `app/Modules/Core/ModuleManager/Controllers/AdminPanelController.php:116` | `$section['role']` (declarativo, `admin_cards[]`) | Patrón documentado en CLAUDE.md, no hardcode ad-hoc |
| `app/Modules/Core/Layout/views/topbar.blade.php:125` | `super-administrator`/`DESARROLLADOR` (ya contado arriba) | — |
| `app/Modules/Core/Auditoria/Console/AuditoriaPermisosCaso0Command.php:85` | `TEST_ROLE` (constante) | Comando de auditoría/test, no producción |
| `app/Modules/Core/Usuarios/Controllers/UserController.php:452` | `$roleName` (variable, contexto de asignación) | Gestión de roles, no gating de feature |
| `app/Modules/Core/Auth/Controllers/LoginController.php:115` | `STAFF_ROLES` (lista) | Gate de login, no de una feature puntual |
| `app/Modules/Core/Security/Console/AuditarPermisosCommand.php:358` | `Vendedor` | Comando de auditoría |

**Cero** de estas 11 ocurrencias nombra `SUPERVISOR_MOSTRADOR` ni `Mostrador` directamente.
Confirmado con grep dedicado:

```
grep -rn "hasRole(\|hasAnyRole(" app/ resources/views/ routes/ | grep -i "mostrador\|supervisor"
```
→ **0 resultados.**

### Verificación cruzada con los 10 permisos netos del diagnóstico de Fase 1

Los 10 permisos que Diana ganaría al recibir SUPERVISOR_MOSTRADOR (`client_delete_client`,
`client_service_internet_delete_client`, `crm_delete_crm`, `crm_export_crm`,
`dashboard_view_info_invoice_transaction`, `finance_edit_payments`, `invoice_add_invoice`,
`scheduling_project_view_project`, `scheduling_task_update`, `user_view_user`) se rastrearon uno
por uno: **todos** se enforzan vía `config/route_permission.php` (middleware
`CheckRoutePermission`, que desde Fase 3a de #9990744 ya honra permisos por ROL vía
`getAllPermissions()`, no solo directos). Solo uno de ellos (`scheduling_project_view_project`)
tiene además un chequeo en Blade, y es el patrón CORRECTO
(`auth()->user()->can(...)`/`canAny(...)` en `resources/views/module-sidebar/scheduling.blade.php`,
no la directiva `@can()`). Ninguno pasa por un `hasRole()` hardcodeado.

### Veredicto H8: **DESCARTADA**

No existe gating hardcodeado por el nombre del rol `SUPERVISOR_MOSTRADOR`/`Mostrador` en ningún
punto del código (app/, resources/views/, routes/) — cero ocurrencias. Los `hasRole()` hardcoded
que sí existen son o (a) el bypass de super-administrator/DESARROLLADOR documentado como diseño,
o (b) lógica de otros módulos/roles sin relación con el caso Diana. Los 10 permisos concretos que
el diagnóstico de Fase 1 identificó como la brecha real se enforzan todos por el mecanismo
estándar de permiso (middleware de ruta), no por rol hardcodeado.

## Qué sigue (para el sub-item de consolidación, hermano de este)

Con H7 y H8 descartadas (y H2/H3/H6 ya descartadas por #9990743), quedan **H9** (deriva de
nombres de permiso) y **H10** (doble capa legacy de permisos) como las hipótesis de enforcement
sin verificar todavía en la Fase 3. Ninguna de las cuatro hipótesis de esta fase (H7, H8) explica
hoy por qué los permisos de un rol no llegarían a un administrador que lo tiene.
