# Fase 0 — Estado del backend (lo escribe `agente-backend`)

Frontend y QA: leer esto antes de probar contra `megaisp_test`. Las entradas van de la más vieja a la más nueva.

## 2026-11-12 16:25 — Migraciones aplicadas en `megaisp_test` y rutas nuevas respondiendo

**Migraciones aplicadas en `megaisp_test`** (con `flock /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php artisan migrate --path=app/Modules/Addons/Marketing/migrations --force`):

| Migración | Qué hace |
|---|---|
| `2026_09_14_210000_create_marketing_conversation_permissions` | ya existía, estaba pendiente en `megaisp_test` (4 permisos `marketing.conversation.*`) |
| `2026_11_12_100000_create_marketing_empresas_table` | tabla `marketing_empresas` + fila 1 «Meganet» (`#0066CC` / `#FF6600`) |
| `2026_11_12_100100_create_marketing_empresa_usuario_table` | tabla `marketing_empresa_usuario` (unique `company_id,user_id`, índice `user_id,activo`, FKs) |
| `2026_11_12_100200_create_marketing_empresas_gestionar_permission` | permiso `marketing.empresas.gestionar` → `super-administrator` y `DESARROLLADOR` (aditivo) |
| `2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet` | asignación inicial por rol. En `megaisp_test` asignó **0** (la base solo tiene 1 usuario sin roles) |

Ensayo limpio en `megaisp_dryrun` (base desechable creada para esto, con esquema mínimo copiado de `megaisp_test`: users, roles, permisos, migrations + 7 usuarios ficticios): migrate → `rollback --step=4` (las 2 tablas desaparecen) → re-migrate → segundo migrate «Nothing to migrate». Selector verificado: super-administrator → admin, DESARROLLADOR+Administrador → admin, Administrador → marketing; excluidos espejo `client`, inactivo, `is_system` y Vendedor.

**Rutas**: las 131 registradas (7 públicas sin capa, 18 V, 88 M, 8 MP, 2 de contexto, 8 de «Empresas y equipo»), todas las autenticadas con `marketing.empresa` después de `auth`[/`check_route_permission`]. Verificado con `route:list --json`.

Rutas nuevas, todas probadas por HTTP en `megaisp_test`:
- `GET /api/marketing/contexto`, `POST /api/marketing/contexto/empresa-activa`
- `GET /marketing/empresas` (vista `addon-marketing::empresas`, tag `<marketing-empresas-equipo :auth-user-id="…">`)
- `GET|POST /api/marketing/empresas`, `PUT /api/marketing/empresas/{id}`
- `GET|POST /api/marketing/empresas/{id}/usuarios`, `PUT /api/marketing/empresas/{id}/usuarios/{userId}`, `GET /api/marketing/empresas/{id}/usuarios-disponibles?buscar=`

**Usuarios `mkt_prev_*`**: backend **no** los sembró. Según §2.10 del contrato los siembra frontend (con `read -s` de la contraseña, que backend no puede teclear). Las tablas que necesita el script ya existen.

**Notas para QA y frontend**
- Respuestas 404 de las rutas nuevas (empresa ajena, inexistente, `{userId}` sin fila): `{"ok":false,"error":"no_encontrado","mensaje":"…"}` (el contrato solo fija el 404; el cuerpo es un extra).
- En las rutas con `{id}` de «Empresas y equipo», el 404 por empresa ajena sale **antes** de validar el cuerpo: un admin de B que manda un cuerpo inválido a `{id}` de A recibe 404, no 422.
- El contexto (`EmpresaActiva`) se limpia en el `terminate()` del middleware: después de un `$this->getJson(...)` en una prueba ya **no** queda puesto.
- La vista `sin-empresa` extiende `core-layout::master`; ese layout truena en el worktree mientras no exista `public/js/app.js` (lo genera `npm run dev`). En mis pruebas la renderizo con un layout mínimo.
- `config_compartida`, `sin_permiso_rol` y `sin_empresa` devuelven exactamente los textos de §2.5.

**Desviaciones / hallazgos para el supervisor** (no corregidos: están fuera de la lista del paquete backend)
1. Varios controladores existentes fijan `company_id = 1` a mano (`MarketingLeadController` 8, `MarketingLeadFormController` 8, `MarketingMultivariantCampaignController` 7, `MarketingGeneratedContentController` 6, `PublishingController` 5, `PilotCampaignService` 1). Con la empresa B activa el scope suma `company_id = B`. Resultado: B ve listas vacías y 404 en **sus propios** registros. No hay fuga de A hacia B, pero B no puede trabajar sus datos. Por ejemplo, ventas de B crea un lead (queda con `company_id = B` por el trait) y luego no lo ve. Arreglarlo es tocar esos controladores (quitar el `1` fijo y dejar que el scope filtre). Queda para el supervisor o Fase 1.
2. Los controladores existentes exigen además permisos Spatie propios (`marketing.leads.view|create|update|…`, `marketing.forms.*`, `view-marketing-campaigns`, …). Esta fase **no** los crea. Lectura en dev (`megaisp`, solo SELECT): `marketing.leads.*` y `marketing.forms.*` **no existen**; `view-marketing-campaigns` y `manage-marketing-niches` sí. En `megaisp_test` no existe ninguno. Un usuario con rol de empresa `ventas` necesita también esos permisos Spatie para llegar a Leads. El problema ya existía y va en la misma línea que D5.

## 2026-11-12 16:32 — Paquete backend terminado (sin commit)

- Selector de la migración 100300 contra dev, solo lectura (SELECT, sin escribir): **12 admin + 1 marketing**, como dice el contrato.
- Pruebas propias, todas en verde y corridas **por archivo** con `flock /tmp/megaisp-suite.lock`, en `tests/Feature/Marketing/Fase0/Backend/`:
  `EmpresaActivaYScopeTest` (14), `CapaEmpresaMiddlewareTest` (15), `ContextoEmpresaBackendTest` (7), `EmpresasApiBackendTest` (14),
  `EquipoApiBackendTest` (15), `MigracionAsignacionBackendTest` (4), `ExistsAcotadoBackendTest` (4). Total: 73 pruebas.
- El WAF global (`SqlInjectionProtection`) responde **403** antes de llegar a la validación a cuerpos como `' OR 1=1 --` o `x'; DROP TABLE…`. QA: en las pruebas de inyección, aceptar 403 (WAF) o 422/200 sin resultados.
- `EvolutionWebhookTest::test_no_token_when_stored_token_empty_passes` falla (401). Falla igual en `megaisp-base-marketing`: **preexistente**.
- `grep withoutGlobalScope` en el módulo y en `app/Models/Marketing`: sin coincidencias.
