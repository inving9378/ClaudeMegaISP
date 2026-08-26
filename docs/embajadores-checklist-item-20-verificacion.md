# Item #20 — Cerrar módulo Embajadores Meganet contra checklist

**Fecha:** 2026-08-26 · **Roadmap:** item #20 (nivel_riesgo B, aprobado por Irving)

## Contexto

El item pedía "cerrar" el módulo Embajadores Meganet contra un checklist implícito en su
propia descripción: programa multinivel hasta 5 niveles, vigencia de 12 meses, comisiones
por paquete, bloqueado antes por el "motor de servicios contratables", y UI que siempre
diga "Embajadores Meganet" (nunca "piramidal"). El item no traía spec adicional ni
criterio de aceptación explícito (así lo marcó el Revisor #338 al escalarlo).

## Verificación punto por punto

| Requisito del item | Estado | Evidencia |
|---|---|---|
| Bloqueador "motor de servicios contratables" | ✅ Ya no aplica | Resuelto para el sistema en general desde 2026-07-15 (`docs/megafamilia-checklist-clasificado-2026-07-15.md`, existe `App\Models\Contratable\ContratableService`). Además, el motor de comisiones de Embajadores **nunca dependió** de ese sistema: calcula el tier de comisión directo desde `ClientInternetService`/`Internet.download_speed` (`ProcessReferralCommissions::getClientSpeedMbps()`), sin tocar `Contratable*`. |
| Multinivel hasta 5 niveles | ✅ Ya implementado | `ReferralCommissionTier` tiene `level_1_pct`..`level_5_pct`; `Referral::getUpstreamChain($settings->max_levels)` resuelve la cadena de hasta 5 ancestros (`app/Jobs/Referrals/ProcessReferralCommissions.php:159-165`). |
| Vigencia de 12 meses | ✅ Ya implementado | Plan A (mensualidad única): `ReferralReward` expira a `now()->addMonths(12)` (`processPlanA`). Plan B (multinivel): tope de `commissions_paid_count >= 12` pagos (`processPlanB`). |
| Comisiones por paquete | ✅ Ya implementado | `ReferralCommissionTier::resolveForSpeedMbps()` resuelve el tier (y por tanto el % de cada nivel) según el rango de velocidad del paquete de Internet del cliente referido — cada paquete/tier tiene sus propios `level_N_pct`, y el monto de la comisión es `% * invoice->total` (varía con el precio real del paquete pagado). |
| UI dice siempre "Embajadores Meganet" | ✅ Ya implementado | `module.json` (`name`, `menu.label`, `title`), `ReferralSetting::program_name` default, las 6 vistas Blade del módulo, y hasta un recordatorio explícito en `EmbajadoresConfiguracion.vue:85` ("El nombre del programa en UI siempre debe ser 'Embajadores Meganet'"). |
| Nunca usar "piramidal" | ✅ Confirmado | `grep -rniI "piramidal\|pirámide\|piramide"` sobre `app/`, `resources/`, `routes/`, `config/` → **0 resultados**. La palabra solo aparece en documentación (`CLAUDE.md`, manuales de criterios) como recordatorio de la prohibición, nunca en código/UI. |

## Gap real encontrado y corregido

El único punto del "checklist" que SÍ estaba roto: `module.json` declara
`client_tab.component = "EmbajadoresClientTab"` para mostrar una pestaña "Embajador" en
la ficha de cliente, pero ese componente Vue **nunca se creó** ni se registró en
`resources/js/app.js` (gap documentado previamente en la memoria operativa
`project-client-tab-infra`, que listaba a Embajadores entre los módulos con `client_tab`
declarado pero sin componente). Como `ClientCrud.vue` oculta silenciosamente cualquier
pestaña cuyo componente no exista, esto no rompía la ficha — simplemente la pestaña
"Embajador" nunca aparecía.

**Corregido en este item:**
- `resources/js/components/module/embajadores/EmbajadoresClientTab.vue` (nuevo) — resumen
  del perfil de embajador del cliente (plan, referidos, comisiones ganadas, tamaño de red,
  comisiones recientes).
- `GET /embajadores/clientes/por-cliente-isp/{clientId}` (nuevo, en `ClientesController`) —
  el `show()` existente indexa por el PK propio del perfil, no por el `client_id` ISP que
  recibe la pestaña; se agregó un endpoint dedicado indexado por `client_id`, mismo patrón
  que MegaFamilia resolvió con su propio endpoint `por-cliente-isp/{clientId}`.
- Registrado globalmente en `app.js` (`app.component("EmbajadoresClientTab", ...)`) —
  requisito duro de la infra de pestañas extensibles (un descendiente de `ClientCrud` solo
  resuelve componentes globales).

Verificado: `php -l` limpio en los dos archivos PHP tocados, ruta nueva presente en
`Route::getRoutes()`, `porClienteIsp()` probado en tinker contra un `client_id` real con
perfil (devuelve comisiones/red) y contra un `client_id` sin perfil (devuelve
`{"profile":null}` sin error), `npm run dev` (vía semáforo del circuito) compiló sin
errores y el componente quedó embebido en `public/js/app.js`.

## Fuera de alcance de esta pasada

No se encontró ningún otro gap de código bloqueando el cierre del módulo — el motor de
comisiones, los niveles, la vigencia y el naming de UI ya cumplían el checklist descrito
en el item antes de esta sesión. No se tocó lógica de negocio de comisiones (dinero) ni se
inventaron nuevas reglas de porcentajes/paquetes: los tiers y porcentajes existentes son
configurables por Irving desde `/embajadores/tiers`, no se requería ni se hizo ningún
cambio de diseño ahí.

**Pendiente de validación visual de Irving:** abrir la ficha de un cliente que sea
embajador (ej. `client_id=6703` en dev) y confirmar que la pestaña "Embajador" ahora
aparece y muestra sus datos.
