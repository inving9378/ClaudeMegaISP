# Item #9990823 (Fase C de #9990804) — verificación end-to-end del middleware de bloqueo por documento firma pendiente

**Fecha:** 2026-09-11 · **Entorno:** DEV (`/home/meganet/circuito/wt-1`, worktree del circuito) · **Alcance:** solo lectura + datos sintéticos temporales, sin tocar `.env` ni datos reales.

## Contexto

El item padre #9990804 pedía un middleware global que bloquee escrituras (POST/PUT/PATCH/DELETE)
cuando el colaborador autenticado tiene un `talento_employee_documents.status='pendiente'` de un
template `tipo='firma'` cuyo `modulos_bloqueados` intersecta el módulo de la ruta actual. Fase A
(#9990821) y Fase B (#9990822) ya están mergeadas a `main`:

- `config/talento.php` → `bloqueo_firma_pendiente_enabled` (kill switch, default `false` vía
  `env('TALENTO_BLOQUEO_FIRMA_PENDIENTE_ENABLED', false)`) + catálogo `modulos_operativos`
  (`prospectos=>['/crm']`, `ventas=>['/sellers','/vendedores']`, `comisiones=>['/sellers','/vendedores']`).
- `App\Modules\Addons\Talento\Middleware\BloqueoDocumentoPendienteMiddleware`, registrado en el
  grupo `web` de `app/Http/Kernel.php`.
- Permiso Spatie `talento.bypass_bloqueo_firma` (migración `2026_09_11_220000_...`), asignado solo a
  `super-administrator` + `DESARROLLADOR` vía `PermissionSyncService::syncPermissionToBaseRoles`.
- Columnas `talento_document_templates.tipo` (enum `firma|acuse|estudio`) y `modulos_bloqueados`
  (json), ya migradas y corridas en la BD de dev (`migrate:status` confirma `Ran`).

## Método de verificación

En vez de simular la prueba por HTTP real (que hubiera exigido tocar `.env` con
`TALENTO_BLOQUEO_FIRMA_PENDIENTE_ENABLED=true` + `config:clear` + revertir después, y lidiar con
CSRF/sesión), se instanció el middleware directamente dentro de un mismo proceso `php artisan
tinker` y se activó el flag **solo para ese proceso** con `Config::set('talento.bloqueo_firma_pendiente_enabled', true)`.
Es equivalente a nivel de lógica (el middleware lee `config()` exactamente igual en ambos casos) y
es más aditivo/reversible: cero cambios a `.env`, cero `config:cache`, nada que revertir al
terminar. **Decisión registrada** (regla de oro) vía `circuito:reportar --tipo=decision`.

Se construyeron `Illuminate\Http\Request` con `setRouteResolver()` apuntando a una `Route` con
nombre controlado, para poder probar tanto rutas exentas (por nombre exacto) como rutas no exentas
de distintos módulos, sin depender de que esas rutas existan registradas de verdad.

**Colaborador de prueba:** se descartó el candidato inicial (`colaborador_id=1`, user 4818)
porque tiene un documento **real** pendiente (`id=34`, "Contrato Individual de Trabajo",
`modulos_bloqueados=["*"]"`) que habría contaminado cualquier prueba sobre ese colaborador. Se usó
en su lugar `colaborador_id=4` (`user_id=3992`, rol `client`, cuenta espejo, **0 documentos
pendientes tipo firma preexistentes** — verificado antes de empezar), evitando tocar cualquier dato
real de un colaborador.

## Resultados (8 aserciones + 1 supplementaria, todas OK)

| # | Escenario | Resultado |
|---|-----------|-----------|
| 1 | `modulos_bloqueados=['*']`, ruta no exenta `/crm/...` | **423** (bloquea) ✅ |
| 2a | `modulos_bloqueados=['prospectos','ventas','comisiones']`, ruta `/crm/...` (prospectos) | **423** (sigue bloqueando) ✅ |
| 2b | misma lista acotada, ruta `/flotas/...` (fuera de la lista) | **200** (NO bloquea) ✅ |
| 3 | documento marcado `status='completo'`, misma ruta `/crm/...` | **200** (sin pendientes, NO bloquea) ✅ |
| 4 | documento pendiente pero `template.tipo='acuse'` (no firma), `modulos_bloqueados=['*']` | **200** (NUNCA bloquea; el middleware solo mira `tipo='firma'`) ✅ |
| 5 | las 11 rutas exentas por nombre (`login`, `logout`, `password.email`, `password.update`, `profile.password.change`, `talento.documentos.firma`, `talento.asistencia.{checkin,checkout,ping}`, `talento.portal.asistencia.{checkin,checkout}`), con pendiente `tipo=firma` wildcard reactivado | **200 en las 11** (nunca bloquean) ✅ |
| 5-control | misma config, ruta con nombre **no** exento | **423** (confirma que el 200 de arriba es por la exención, no por falta de pendiente) ✅ |
| 6 | usuario con permiso `talento.bypass_bloqueo_firma` (rol DESARROLLADOR), mismo pendiente bloqueante | **200** (bypass efectivo) ✅ |
| extra | `modulos_bloqueados=['ventas']`: `/sellers/...` y `/vendedores/...` → 423; `/crm/...` → 200 | **OK**, confirma el 3er módulo (ventas/comisiones, prefijo compartido) además de prospectos y el control flotas |

Cobertura de "al menos 3 módulos distintos" del DoD: **prospectos** (`/crm`), **ventas**
(`/sellers`, `/vendedores`) y un módulo fuera del catálogo (**flotas**, `/flotas`) usado como
control negativo — más el caso `comisiones` (mismo prefijo que ventas, cubierto por el mismo test).

## Limpieza

Todos los templates (`TalentoDocumentTemplate`) y documentos (`TalentoEmployeeDocument`) sintéticos
creados para la prueba se borraron con `forceDelete()` al terminar cada script. Verificado
al final: `TalentoDocumentTemplate::withTrashed()->where('name','like','TEST #9990823%')->count()`
= 0, sin documentos huérfanos. `.env` no fue tocado (`grep TALENTO_BLOQUEO_FIRMA_PENDIENTE_ENABLED
.env` → sin resultado, sigue en default `false` vía `config/talento.php`). No se corrió
`config:cache` en ningún momento.

## Conclusión

El middleware cumple el DoD del item padre #9990804 en los 6 escenarios exigidos por el spec de
Fase C, sin regresiones ni comportamiento inesperado. **Sin cambios de código** — Fases A y B ya
implementaban correctamente el mecanismo; esta Fase C es puramente de verificación. El flag
`bloqueo_firma_pendiente_enabled` permanece en `false` (no activado), tal como decidió Irving en la
pregunta `q4` del item padre: solo se aprobó el *mecanismo*, no su activación en dev/prod.
