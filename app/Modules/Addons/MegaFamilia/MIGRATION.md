# Módulo addon-megafamilia

Plataforma de control parental para clientes de MegaISP. Incluye administración
web (admin/soporte) y app móvil (padres + hijos vía Sanctum).

## Capas

- **migrations/** — 17 tablas: `parental_plans`, `parental_accounts`,
  `parental_licenses`, `parental_profiles`, `parental_devices`,
  `parental_rules`, `parental_app_blocks`, `parental_web_blocks`,
  `parental_schedules`, `parental_requests`, `parental_tasks`,
  `parental_rewards`, `parental_locations`, `parental_geofences`,
  `parental_alerts`, `parental_events` + un add_permission_*.
- **Models/** — 16 modelos Eloquent extendiendo `App\Models\BaseModel`.
- **Controllers/** — 19 controladores (10 sólo admin, 5 admin+soporte,
  3 cliente, 1 API mobile).
- **views/** — 19 blade shells que extienden `core-layout::master` y
  montan un componente Vue.
- **seeders/** — `ParentalPlansSeeder` (3 planes seed) + entry en
  `module_registry`.
- **routes.php** — UI web + endpoints `/api/megafamilia/*` con
  `auth:sanctum`.

## Adaptaciones respecto al spec

- El spec asignaba `megafamilia_admin` a "ADMINISTRADOR" pero el role en
  DB es `Administrador`. Se asigna a: `Administrador`,
  `super-administrator`, `Super Administrador`, `DESARROLLADOR`.
- El spec asignaba `megafamilia_support` a "SOPORTE" pero no existe ese
  role; se asignó a `TECNICO` (el más cercano). Renombrar después si
  hay un role específico de soporte.
- `parental_accounts.client_id` FK a `users(id)` siguiendo el spec literal;
  el cliente ISP (`clients`) puede asociarse después si se decide unificar.

## Pendiente / scaffolds

- Vue: `MegaFamiliaDashboard.vue` está completo; los otros 17 son
  scaffolds funcionales (tablas/cards/forms cableados al endpoint pero
  con poco contenido específico). Iterar caso por caso.
- API mobile: endpoints listos. `App\Models\User` ya usa
  `Laravel\Sanctum\HasApiTokens` (verificado: `createToken()` emite
  tokens `1|…` y la revocación funciona), así que `/api/megafamilia/
  auth/login` opera sin cambios adicionales.
- Repositorios y Services están vacíos — añadir cuando una consulta
  amerite extraer del controller.
