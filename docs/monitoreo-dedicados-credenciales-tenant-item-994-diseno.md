# Item #994 — Credenciales de routers por tenant (Medussa) — DISEÑO

Estado: **solo diseño**. Sin migración, sin código, sin credenciales. La implementación queda
condicionada a que Irving confirme que el monitoreo de dedicados se vende/usa en Medussa
(multi-tenant). Decisión registrada: opción reversible (documento) frente a crear tablas con
secretos, que cae en la frontera dura de credenciales/seguridad.

## Problema
Épica #988: cada cliente dedicado entra por una VLAN en un MikroTik. Hoy los routers son los de
Meganet (un solo operador). Si Medussa lo ofrece a otros ISP (tenants), cada uno necesita su
propio inventario de routers y credenciales, sin que uno vea o use los del otro.

## Diseño propuesto
**Tabla `monitoring_routers`** (nueva, aditiva)
- `id`, `tenant_id` (NOT NULL, índice; NULL = Meganet interno, como `fleet_vehicles.client_id`),
  `name`, `host`, `port`, `use_tls` (bool), `username`,
  `password_enc` (cast `encrypted` de Laravel, llave = `APP_KEY`), `status`, `last_ok_at`,
  `created_by/updated_by` (BaseModel), soft deletes.
- Único `(tenant_id, host, port)`.

**Aislamiento**
- Modelo con el scope global de tenant ya usado en Flotas (`BelongsToClientTenant`): toda consulta
  filtra por `tenant_id`; sin tenant resuelto → no devuelve nada (falla cerrado).
- El monitor (`MikrotikService`) recibe el router por id ya cargado vía el scope; nunca resuelve
  credenciales por host recibido del cliente.

**Secretos**
- La contraseña jamás se devuelve por API (el resource omite `password_enc`; la UI muestra
  «••••» y permite reemplazarla, no leerla).
- Nunca en logs, `activity_log` ni en `.env`. Rotación = reemplazar el valor.
- Recomendado: usuario de RouterOS de solo lectura (`/interface monitor-traffic`, `/interface print`).
- Rotar `APP_KEY` obliga a re-cifrar: comando futuro `monitoring:reencrypt`.

**Permisos** (convención jerárquica #538): `monitoreo_view_dashboard`,
`monitoreo_view_block_routers`; gestión de routers `monitoreo.routers.manage` (solo admin del tenant).

**Convención de tráfico** (sin cambio): en la VLAN rx = SUBIDA del cliente, tx = BAJADA del cliente;
la UI siempre dice «Bajada/Subida del cliente».

## Pendientes de decisión (Irving)
1. ¿Se vende el monitoreo en Medussa? Si no, no construir.
2. ¿Credencial por router o una por tenant con N routers?
3. ¿Conexión directa desde Medussa a la red del tenant (VPN/túnel) o agente local? Define el
   alcance de seguridad real; cualquier opción requiere su aprobación (red/credenciales).

## Plan por fases cuando se apruebe
F1 migración + modelo + scope + pruebas de aislamiento (tenant A ≠ B, como `portal:test-idor`).
F2 CRUD con permisos. F3 conectar el monitor a `monitoring_routers`.
