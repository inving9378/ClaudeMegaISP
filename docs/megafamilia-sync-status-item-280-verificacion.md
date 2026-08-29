# Item #280 — Consumir `/api/megafamilia/sync-status` en megafamilia-rn — verificación

**Fecha:** 2026-08-29 · **Worker:** wt-1 · **Sub-item cerrado:** #716 (contenía el plan detallado ejecutado aquí)

## Contexto

El backend (`GET /api/megafamilia/sync-status`, `auth:sanctum`) ya estaba listo desde el item #26
(commit `dc2df59f`, integrado a main). Devuelve `{version, servicio_updated_at, facturas_updated_at,
tickets_updated_at, checked_at}`, donde `version` es un md5 de los `updated_at` de
clients/invoices/tickets del usuario autenticado.

El código del lado cliente vive en `/var/www/megafamilia-rn` (React Native, repo **fuera** de
`megaisp`, checkout único compartido **sin worktree/rama/integrar del circuito** — el aislamiento
#334 y la infra de `circuito:rama`/`circuito:integrar` solo operan sobre el repo `megaisp`). Por eso
este item pasó por 3 sesiones previas (wt-6, wt-2, wt-1) que verificaron la implementación pero
reportaron bloqueo al intentar comitear ahí, y quedó escalado/re-triado varias veces (ver
`comentarios_claude`/`log` del item #280 en la Hoja de Ruta para el historial completo).

## Qué se hizo en esta sesión

Se confirmó que el bloqueo de git-write en `megafamilia-rn` reportado por sesiones anteriores **no
aplicó en esta**: el aislamiento #334 restringe escritura sobre `/var/www/megaisp`, no sobre
`megafamilia-rn`. Se ejecutó el plan (a) del sub-item #716:

1. `useSyncStatus.ts` (nuevo, ya estaba `git add`-eado por una sesión previa) y `storage.ts`
   (modificado, ya estaba `git add`-eado) — sin cambios, se comitearon tal cual estaban en el índice.
2. `ClienteNavigator.tsx` mezclaba en el working tree **dos features sin relación**: el wiring de
   este item (import + llamada a `useSyncStatus()`) y WIP ajeno de otra feature (registro de la
   pantalla `FlotasPlan`). Se armó un patch acotado (`git apply --cached`) que llevó al índice
   **solo** el import y la llamada al hook, dejando el hunk de `FlotasPlan` intacto y sin stagear en
   el working tree (no se tocó ni se perdió ese WIP ajeno).
3. Verificación antes de comitear: `npx tsc --noEmit` en `megafamilia-rn` → **limpio, sin errores**.
   Se confirmó además que `useAuthStore.role`, `useClienteStore.loadServicio/loadFacturas/loadTickets`
   y el soporte de `signal` (AbortController) en el cliente axios (`api.ts`) existen y calzan con lo
   que usa el hook; y que `apiBaseUrl` ya incluye `/api/megafamilia`, así que `api.get('/sync-status')`
   resuelve a la ruta real del backend, igual que el resto de las llamadas del store.
4. Commit en `megafamilia-rn` (rama `main` de ese repo, no hay infra de rama propia para RN):
   `44e58542681e9e65c14952eb91ca25ac340a3e15`.

**Resto del working tree de `megafamilia-rn` (ruido de checkout: ~120 archivos con diffs de 0
líneas/permisos/line-endings, más `VincularHijoScreen.tsx` y `ClienteFlotasPlanScreen.tsx`
untracked de otra feature) — NO tocado**, tal como indicaba el plan del sub-item #716.

## Implementación (resumen funcional)

- `useSyncStatus()` hace polling cada 30s a `/sync-status` con `AbortController`, se
  pausa/reanuda con `AppState` (background/foreground), y solo si `version` difiere del guardado en
  `AsyncStorage` refresca `loadServicio()+loadFacturas()+loadTickets()` del store del cliente. Solo
  corre si `isAuthed && role === 'cliente'`.
- `storage.ts` gana `loadSyncVersion()`/`saveSyncVersion()` sobre `AsyncStorage`
  (`@megafamilia:syncVersion`).
- `ClienteNavigator.tsx` invoca `useSyncStatus()` una vez en el navigator raíz del cliente.

## Verificación

- `npx tsc --noEmit -p tsconfig.json` en `megafamilia-rn` → exit 0, sin errores.
- Revisión manual de los 3 archivos comiteados (diff `--cached` antes de comitear) — contenido
  idéntico al descrito y verificado por las sesiones wt-6/wt-2 previas.
- Sin pruebas end-to-end en dispositivo/emulador (fuera de alcance de este worktree — no hay
  toolchain de build de Android/iOS en el ejecutor on-box).

## Pendiente (fuera de alcance de este item)

- Build/instalar un APK nuevo de `megafamilia-rn` con este cambio y probarlo contra un usuario
  cliente real (queda para cuando se genere el próximo release de la app).
