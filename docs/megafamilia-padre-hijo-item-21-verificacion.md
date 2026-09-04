# Verificación del item #21 — Infra de relación Padre-Hijo en backend (RESUELTO — premisa incorrecta)

El item pedía "construir infra de relación Padre-Hijo en backend (modelo, asociación, permisos)"
para desbloquear MegaFamilia, la Vista Hijo APK, el Panel Padre APK y el Control Parental.

**Investigado en esta vuelta:** esa infra **ya existe, completa**, desde antes de que el item se
creara. El módulo `addon-megafamilia` (migraciones `2026_05_16_*`, `created_at` del item #21 es
`2026-05-31` — **posterior**) ya trae la relación Padre-Hijo modelada de punta a punta:

## Modelo y asociación (ya construidos)

- **`parental_accounts`** (el "padre" — cuenta principal) — `user_id` → `users.id` (el dueño de
  la cuenta MegaFamilia), `client_isp_id` → `clients.id` (tenant ISP), `plan_id`, `status`.
  Modelo `ParentalAccount` (`app/Modules/Addons/MegaFamilia/Models/ParentalAccount.php`).
- **`parental_profiles`** (el "hijo" — perfil infantil dentro de la cuenta) — `account_id` →
  `parental_accounts.id` (FK, cascade), `name`, `age`, `school_level`, `profile_type`
  (`nino`/`preadolescente`/`adolescente`), `pin_hash` (auth del hijo en su dispositivo).
  Modelo `ParentalProfile`, relación `account()`/`ParentalAccount::profiles()`.
- **`parental_devices`** (asociación dispositivo↔hijo↔cuenta) — `profile_id` + `account_id`,
  pareado por `link_token` de un solo uso con expiración (`link_token_expires_at`), flujo
  completo en `ApiController` (`requestLink`/`linkDevice`, líneas ~987-1068): genera token →
  el hijo lo consume desde su app → dispositivo queda ligado a `profile_id`/`account_id`.
- Toda la superficie hijo (reglas, bloqueos de apps/web, horarios, tareas, recompensas,
  solicitudes, geocercas, alertas) cuelga de `profile_id`, ya construida (`ParentalRule`,
  `ParentalAppBlock`, `ParentalWebBlock`, `ParentalSchedule`, `ParentalTask`,
  `ParentalTaskAssignment`, `ParentalReward`, `ParentalRequest`, `ParentalGeofence`,
  `ParentalAlert`, `ParentalEvent` — 16 modelos en total).
- Aislamiento multi-tenant: `BelongsToClientTenant` fail-closed sobre `client_isp_id` en
  `ParentalAccount` y `ParentalProfile` (`DerivesClientIspId` desde `account_id`).

## Permisos (ya construidos)

- Migración `2026_05_16_000017_add_permission_megafamilia.php` crea `megafamilia_admin`,
  `megafamilia_support`, `megafamilia_view` y los asigna a
  `DESARROLLADOR`/`Administrador`/`super-administrator`/`Super Administrador` (admin completo) y
  `TECNICO` (soporte/vista) — panel administrativo del módulo.
- El acceso del padre a SU PROPIA cuenta MegaFamilia (Portal Cliente) está scopeado por
  `parental_accounts.user_id` (ver sección "Portal Cliente" de `CLAUDE.md`), no por permisos
  Spatie — es autoservicio del cliente, no un rol de staff.
- El acceso del hijo a su perfil es por posesión del dispositivo pareado (`link_token`) + PIN
  (`parental_profiles.pin_hash`), no por rol — coherente con que un menor no tiene cuenta de
  usuario en `users`.

## Panel Padre APK / Vista Hijo APK — ya consumen esta infra

- Login del padre verificado end-to-end en el item #104 (`docs/megafamilia-apk-login-item-104-verificacion.md`):
  `POST /api/megafamilia/auth/login` contra `client_main_information` → auto-crea `users` →
  token Sanctum → `parental_accounts.user_id`.
- `megafamilia-rn` (la app React Native, ~95% migrada del Flutter original, ver
  `docs/megafamilia-apk-auditoria-2026-07-14.md`) ya tiene pantallas de Vista Hijo
  (`HijoDashboard.tsx`, `LogrosScreen.tsx` — ver item #639) que leen sobre este mismo modelo
  `parental_profiles`/`parental_devices`.

## Por qué no se construye nada nuevo

`grep -rniE "family_relationship|plan_famil|padre_hijo|cuenta_familiar" app/ resources/ routes/
config/ database/migrations` → **cero resultados**: no hay ningún consumidor esperando un modelo
distinto (p. ej. una tabla `family_relationships` polimórfica, que sí se llegó a proponer en un
brief de des-trabe anterior del propio item, escrito solo a partir del título sin revisar que el
código ya existía). Construir un segundo modelo de relación Padre-Hijo en paralelo al que ya usan
16 tablas, 19 controllers y la app móvil violaría ESTABILIDAD/MINIMALISMO (duplicaría infra viva)
sin resolver nada que hoy esté bloqueado — MegaFamilia, el Panel Padre y la Vista Hijo ya operan
sobre esta relación.

**`circuito:cabida` marcó el item como "NO CABE" (`ya_timeouteo_antes`)** por un timeout previo
sin commits (la vuelta anterior se la pasó deliberando sobre el brief de des-trabe, no
investigando el código real). No se generó un sub-item de seguimiento porque no queda trabajo
pendiente que dividir — la conclusión no depende del tamaño de la tarea, y encadenar sub-items
con la misma premisa ya resuelta solo perpetuaría el ciclo (patrón visto y cortado en la cadena
`#702→#708→#715→#717`, ver `docs/mikrotik-migracion-item-717-verificacion.md`).

**Sin cambio de código.** Solo esta nota de verificación.
