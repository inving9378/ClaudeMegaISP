## 2026-10-15 11:40 — Restaurar el dump de producción sobre la BD de DEV (bitácora PREVIA)

**Qué:** cargar `/var/backups/mysql/megaisp-202610150604.sql.gz` (dump de `meganet_prod_claude`,
terminado 2026-10-15 06:05:21) **encima de la BD de desarrollo `megaisp`**, para revisar en dev a
los clientes con problemas de facturación. Decisión explícita del usuario de la sesión (se le
ofreció cargarlo en una BD aparte y eligió `megaisp`).

**Contra qué BD:** `megaisp` en DEV (192.168.105.11). **Producción no se toca** (dev no llega a .108).

**Quién:** Claude Code, sesión en DEV (`/var/www/megaisp`), a petición del usuario de la sesión.

**Respaldo previo:** `php artisan backup_db:process` justo antes de borrar → nuevo
`/var/backups/mysql/megaisp-202610151227.sql.gz` (además del de las 02:00 y el binlog).

**Cómo:**
1. Borrar todas las tablas y vistas de `megaisp` (FK off), para que no queden tablas solo-de-dev
   mezcladas con datos de prod.
2. `zcat dump | <filtro> | mysql megaisp`, con el filtro:
   - **sin los datos de `jobs`** (el dump trae 477 jobs pendientes de prod: MikroTik/WhatsApp; los
     workers de cola de dev siguen vivos y los ejecutarían). La tabla se crea vacía.
   - sin las cláusulas `DEFINER=` de triggers/vistas (el usuario de la app no puede crear objetos a
     nombre de `meganet@localhost`).
3. Neutralizar salidas a terceros con datos reales: integraciones de WhatsApp/Evolution del Hub y de
   `marketing_settings`, troncal VoIP (`voip_configuracion`) e IPs de OLTs. MikroTik no hace falta:
   fuera de `APP_ENV=production` el código siempre conecta al router de pruebas
   (`RouterConnection::connection`). En dev no corre `schedule:run` (sin cobros/suspensiones
   automáticas). El freno del circuito (`storage/app/circuito/PAUSA`) ya está puesto desde 2026-09-17.
4. Limpiar caché de la app y de permisos.

**Qué se pierde en dev:** todo el contenido actual de `megaisp` (Hoja de Ruta: 1,880 items,
112 releases, estado de la Torre, usuarios/datos de dev). Queda en el respaldo previo.

**Revertir:** borrar tablas de `megaisp` y cargar el respaldo previo
(`zcat /var/backups/mysql/megaisp-202610151227.sql.gz | mysql megaisp`), luego
`cache:clear` + `permission:cache-reset`.

## 2026-10-15 12:45 — Resultado: dump de prod (06:05) cargado en `megaisp` de DEV

- Respaldo previo de dev: `/var/backups/mysql/megaisp-202610151227.sql.gz` (149 MB, `gzip -t` OK).
- Borradas las 615 tablas de dev; cargado el dump en dos tramos (12:31–12:38 y 12:39–12:41).
  El primero se detuvo en el único trigger de prod (`update_column_is_first_payment` sobre
  `payments`): crearlo exige SUPER con binlog activo y `megaisp_user` no lo tiene. **Se omitió ese
  trigger** (dev nunca lo tuvo) y se reanudó desde `payments_details`. Si se quiere, lo crea root
  con el bloque de las líneas 14707–14713 del dump.
- Verificado: 610/610 tablas del dump (ni faltan ni sobran), 5,851 `clients`, 138,997 `payments`
  (último 2026-10-14 19:57), 4,972 `users`, último release `V1.59-14.10.2026`, `jobs` = 0 (los 477
  de prod no se cargaron), `migrate:status --pending` = ninguna, `/login` HTTP 200.
- Neutralizado (solo en dev): `api_integrations` evolution (id 4) y smtp (ids 8, 9) → `active=0`;
  `marketing_settings.evolution_api_url` (prod apuntaba a `http://127.0.0.1:8080`, que en dev es el
  Evolution con el número real de ventas) → URL inválida; `olts` 2/3/4 `ip` (10.50.10.2,
  10.50.11.2, 10.50.12.2) → `192.0.2.1`.
- `cache:clear` OK (directorio de caché vacío).
