## 2026-11-10 — Carga de la base de producción en dev (bitácora PREVIA)

**Quién lo pide:** Irving (lo pasa la sesión de david): «cargar la bd completa de prod a dev» con
MikroTik y OLT en solo lectura, para monitorear dedicados en Estadísticas y ver las ONT reales.
**Decisiones de david:** reemplazar la base de dev (no usar una base aparte). El circuito queda
frenado y **no se reanuda** al terminar: lo decide Irving.
**Quién lo corre:** Claude Code, sesión de david, desde `/var/www/megaisp`, con el usuario MySQL de la
app.
**Base:** `megaisp` en DEV (192.168.105.11). Producción no se toca.

### Origen
- Respaldo automático de producción del 10-nov a las 02:01 (`meganet_prod_claude`, MySQL 8.4.6,
  162 MB comprimido). Está en `/var/backups/mysql/prod-copia/` (700, fuera de la carpeta web). Se
  revisó que esté íntegro (`gzip -t`), que termine en «Dump completed» y que sea de esa base: trae
  709 tablas y un disparador sobre `payments`, sin vistas ni rutinas.

### Antes de escribir
- **Respaldo completo de dev** en `/var/backups/mysql/preservados/pre-carga-prod-<fecha>-megaisp-completo.sql.gz`
  (mysqldump `--single-transaction --routines --triggers --events`, `gzip -t`, 600). Restaurar:
  `zcat <respaldo> | mysql megaisp` sobre la base vacía.
- Circuito frenado desde las 07:30 (`circuito:pausar`, centinela `storage/app/circuito/PAUSA`).
- Candado de solo lectura de la red: rama `feat/red-solo-lectura-dev`. Tiene que estar en `main`
  antes de la carga, porque con la base de producción la configuración de SmartOLT de producción
  llega a dev.
- Modo mantenimiento durante la carga: la web de dev y los procesos de cola se detienen.

### Qué se conserva de dev (no se toca)
33 tablas: `roadmap_*`, `circuito_*`, `settings` (y de producción entran solo sus claves que no
son del circuito), `api_integration*` (llaves del Hub de dev), `releases`/`release_*`/`deployment_logs`
(dev es quien publica versiones), `talento_app_releases`, `marketing_settings` (configuración de
WhatsApp de dev) y la cola (`jobs`, `failed_jobs`). La cola de producción **no** se carga: dev
ejecutaría sus trabajos pendientes contra clientes reales.

### Qué entra de producción
Todo lo demás: clientes, servicios, facturación, personal, permisos, routers, OLTs y mapas. Deshace
la purga a 100 clientes del 9-nov (`2026-11-09-purga-bd-dev-100-clientes.md`): vuelven los datos
reales de todos los clientes a dev.

### Después de cargar
- Se aplican los 9 cambios de estructura que dev tiene en `main` y producción todavía no. Dos de ellos
  son de tablas conservadas (`circuito_ia_modelos`, `roadmap_items.solo_humano`) y se marcan como
  aplicados.
- Se recrean las cuentas de prueba con su misma contraseña cifrada, roles, permisos y ficha:
  `prueba_admin`, `prueba_mostrador`, `revision_play` (la que usa Google para revisar la app, con sus
  3 órdenes de ejemplo) y las `demo_*`. Sus órdenes y préstamo de prueba anteriores no se recrean.
- Se borra la caché de permisos, se reinician los procesos de cola y se quita el mantenimiento. El
  circuito sigue frenado.

### Lo que se pierde (avisado)
- Lo que sí se escribió en dev y no está en las tablas conservadas.
- Lo de ramas de Irving sin juntar con `main` que ya se había aplicado a la base: permisos y columnas
  de editar/eliminar órdenes (#1225). Se recupera corriendo `php artisan migrate` desde su rama.
- Las sesiones abiertas en dev y en la app de Talento: hay que volver a entrar. El personal entra con
  su contraseña de producción.

### Ensayo
Procedimiento completo probado primero en `megaisp_dryrun` (copia de las tablas conservadas +
respaldo de producción), con caché en memoria para no ensuciar la de dev.
- 1.er intento, detenido solo en el paso de carga sin tocar dev: el disparador
  `update_column_is_first_payment` (marca el primer pago de cada cliente) no se puede crear con el
  usuario de la app (error 1419: sin SUPER y con binlog). Dev nunca lo ha tenido: se carga sin él.
  Si se quiere, lo crea alguien con root de MySQL. Además `mysqldump` pide `--no-tablespaces`.
- 2.º intento, completo en 9 min: 716 tablas (igual que dev), 5,887 clientes, 5,018 cuentas,
  `roadmap_items` 1,116 y 407 ajustes del circuito intactos, 8 cambios de estructura aplicados y 0
  pendientes, las 9 cuentas de prueba recreadas con la misma contraseña cifrada (9/9) y sin
  permisos ni roles faltantes, y 3 órdenes de ejemplo para `revision_play`. En la cola queda solo un
  trabajo de regenerar el manual, que dispara una de las migraciones.
- Lo que trae de red: 3 routers (ninguno con usuario de solo lectura capturado: dev leerá con el
  usuario de la API, protegido por el candado), 2 clientes dedicados con monitoreo activo y 3 OLTs con
  driver SmartOLT. La llave de SmartOLT de producción **no** está en la base (0 filas en
  `olt_smartolt_config`) sino en su `.env`: sin ella dev no consulta SmartOLT.

## 2026-11-10 08:45 — Carga ejecutada (resultado)

- Candado de la red y esta bitácora integrados a `main` antes de cargar (0a2a2751e, fdcf6e6d0), con
  autorización de david.
- **1.er intento (08:31):** respaldo hecho y dev en mantenimiento; se detuvo en el paso 4 sin borrar
  nada. La lista de 683 tablas por borrar se cortó por `group_concat_max_len` (1024). En el ensayo
  no apareció porque ahí no había nada que borrar. Corregido con `SET SESSION group_concat_max_len`.
- **2.º intento (08:33–08:42):** respaldo de dev en
  `/var/backups/mysql/preservados/pre-carga-prod-20261110-0833-megaisp-completo.sql.gz` (el de las
  08:31 es del mismo estado). 683 tablas cargadas de producción y 32 conservadas, 8 cambios de
  estructura aplicados y 0 pendientes; 716 tablas, 5,887 clientes, `roadmap_items` 1,116 y 407
  ajustes del circuito intactos.
- **Cuentas de prueba:** el paso falló por la caché de permisos de dev (aún con los ids de antes:
  FK de `model_has_permissions`). La transacción se deshizo sin dejar nada. Tras
  `permission:cache-reset` se recrearon las 9 con la misma contraseña (9/9). `revision_play` entra a la
  app por `https://dev.meganett.com.mx` y ve sus 3 órdenes de hoy (sesión de prueba cerrada).
- Fin del mantenimiento a las 08:42; web de dev responde. El circuito **sigue frenado**.
- `.env` de dev: `RED_ROUTERS_REALES_EN_DEV=true` (grupo www-data intacto) + `queue:restart`.
- Base de ensayo `megaisp_dryrun` borrada (tenía datos reales); export de cuentas borrado.

### Monitoreo de dedicados — por qué no funciona en producción
- En la copia de producción, el colector corre cada minuto desde el 5-nov 18:15. En 6,212 corridas
  leyó 0 routers y 0 clientes: guarda `router_no_verificado` y el ping como `omitido`. La causa es
  siempre la misma: el router 2 contesta con una identidad distinta de su título y nadie la confirmó
  (`mikrotiks.identidad_esperada` vacía en los 3 routers). El colector no lee un router sin
  identidad confirmada.
- Arreglo en producción, sin código: en un dedicado, Servicios → Internet → «Datos de red» →
  «Probar lectura», y confirmar la identidad.
- En dev se confirmó con `IdentidadRouter::guardar` (solo base). `metricas:colectar --dry-run` desde
  dev, contra el router real y con el candado puesto: router ok, 1 dedicado en línea (1/1 IPs, 0 %
  de pérdida), nada guardado. El otro dedicado no tiene nada que medir (sin router o sin datos de red).

### Falta
- Decidir si en dev corren solos el colector (cada minuto) y la lectura de la OLT. Hoy no corre
  ninguna tarea automática de dev; las del circuito están frenadas.
- SmartOLT en dev: su llave vive en el `.env` de producción (decisión pendiente).
- Irving: sus ramas #1225/#1227/#1228 necesitan `php artisan migrate` desde su worktree (permisos y
  columnas de editar/eliminar órdenes), y cada worktree `permission:cache-reset` (caché por checkout).
- Borrar el respaldo de producción de `/var/backups/mysql/prod-copia/` cuando ya no haga falta.
