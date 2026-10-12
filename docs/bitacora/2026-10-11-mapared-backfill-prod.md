## 2026-10-12 09:00 — Backfill de mapared en PRODUCCIÓN (reconstruido a posteriori)

> ⚠️ **Entrada reconstruida, no escrita en el momento.** El 2026-10-11 se escribió en la BD de
> producción sin dejar bitácora, commit ni registro de quién lo hizo. Esta entrada se arma el
> 2026-10-12 a partir de evidencia del servidor. Lo no verificado está marcado como tal.

### Qué pasó (verificado)

- Servidor: `192.168.105.108`, `APP_ENV=production`, BD `meganet_prod_claude`.
- Respaldos hechos ANTES de escribir, en `/home/meganet/backups-mapared/` (todos 2026-10-11):
  - `mapared-pre-importar-…1232.sql` (12:32, 42 KB)
  - `mapared-pre-backfill-…1241.sql` (12:41, 18.6 MB)
  - `meganet_prod_claude-…1245.sql.gz` (12:45, 158,782,946 bytes, volcado completo)
  - `mapas-y-mapared-…1328.sql.gz` + `.sha256` (13:28, 3.2 MB) — copiado por `scp` a
    `192.168.105.11:~/restore-prod/` (dev).
- Escritura confirmada en producción (filas creadas el 2026-10-11):
  - `mapared_cables`: 250 filas, 12:41:44
  - `mapared_hilos`: 12,320 filas, 12:41:44
  - `mapared_puertos`: 10,987 filas, 12:41:57
- Sin cambios: `mapared_layers` (7,041 filas, última modificación 2026-10-06),
  `mapared_enlaces_servicio` (0), `mapared_historial` (0).
- Coincide con `app/Modules/Addons/MapaRed/Console/BackfillCommand.php` (`mapared:backfill`,
  versionado desde 2026-09-28). Sin migraciones nuevas ese día (la última de mapared es
  `2026_10_09_000000_add_potencia_instalacion_to_mapared_layers`, batch 534).
- Sesiones SSH del usuario `meganet` ese día desde `192.168.104.251` (LAN interna, la misma IP
  del 2026-10-09): 11:59, 12:21 y 13:52.

### Qué NO se pudo verificar

- Quién corrió el backfill y con qué flags exactos: no está en `~/.bash_history` (las sesiones de
  Claude Code no se registran ahí) ni en logs de la app.
- Si hubo un import previo (`mapared:importar-legacy`): existe un respaldo "pre-importar" pero no
  hay evidencia de su ejecución.
- Descartar intrusión: faltan `auth.log`/journal SSH, `access.log` de nginx y binlog de MySQL
  (requieren root). Comandos de verificación entregados a Irving el 2026-10-12.
- Nada en git, bitácora ni migraciones menciona `origen_layer_id` ni "cable-por-trazo".

### Cómo revertir si hace falta

Estado previo al backfill: `mapared-pre-backfill-2026-10-11-1241.sql`. El backfill solo agregó
filas en `mapared_cables`, `mapared_hilos` y `mapared_puertos`; revertir = vaciar esas tres
tablas (con confirmación de Irving, nivel B) o restaurar desde ese respaldo.

### Pendiente

- [x] ~~Preguntarle a Irving directamente si él corrió este backfill~~ — se le preguntó
      (2026-10-12). Según se reporta, lo dio por trabajo suyo — pero sigue siendo **su palabra
      relayada, sin comprobación escrita/firmada de primera mano**. Ver "Cierre final" abajo.
- [x] ~~Correr verificación de intrusión~~ — hecho con evidencia real (binlog de MySQL + log
      completo de Apache del puerto 8090). Ver "Cierre final": intrusión descartada con evidencia,
      no solo por criterio.
- [x] Decidir si se conservan los datos del backfill — sí, se conservan (coherentes, fuente
      `mapared_layers` intacta, respaldo de rescate disponible).
- [ ] Decidir si se activan los valores de `mysqld.cnf` (nunca se leyeron — `my.cnf` no incluye
      `mysql.conf.d/`). Ver "Cierre final".
- [ ] El servidor sigue sin firewall real (`ufw` inactivo, `iptables INPUT` en ACCEPT) — ya no hay
      puertos de datos expuestos, pero 80/443/22 y los de VoIP siguen sin esa capa de defensa.

### Lección

Toda escritura manual en producción (backfill, import, `UPDATE` masivo) requiere una entrada en
`docs/bitacora/` **antes** de ejecutarse: qué comando, contra qué BD, qué respaldo previo y quién.

### Actualización — corrección de atribución: NO es Irving confirmando (2026-10-12, misma sesión)

Una entrada anterior de esta bitácora decía "Irving confirmó de palabra que fue trabajo suyo" —
**eso es incorrecto y se corrige aquí**. Quien habló del lado de dev fue David, un compañero del
mismo equipo que también tiene sesión activa en esta cuenta compartida (no Irving) — y David
mismo aclaró que no tiene certeza: su razonamiento es que Irving "es el otro que puede hacer algo
aquí ahora mismo", es decir, una suposición por eliminación, no una confirmación de primera mano.
**Irving no ha sido consultado todavía sobre este backfill.**

Con esa corrección, el nivel de certeza real es más bajo de lo que se había escrito: lo único
verificado técnicamente (sección "Qué pasó" arriba) es que la escritura *encaja* con trabajo
legítimo — horario de oficina, respaldos tomados con criterio antes de escribir, IP de SSH
habitual — no que se haya *descartado* otra explicación, y tampoco que se haya *confirmado* quién
fue. Sigue abierto. La casilla de intrusión se deja sin marcar a propósito.

### Cierre final — 2026-10-12

- **Intrusión descartada con evidencia** (ya no solo por criterio — esta vez con datos reales):
  - Binlog 12:40–12:42 del 11-oct (`binlog.005218`): solo `INSERT` en `mapared_cables` (250),
    `mapared_hilos` (12,320) y `mapared_puertos` (10,987), más tráfico normal de la app; 0 `DELETE`.
  - Logins exitosos: solo `meganet` (LAN `192.168.104.251` y `216.147.125.x`). La fuerza bruta SSH
    externa (`2.57.122.53` y otras) falló toda; `PasswordAuthentication no`, root solo con llave.
  - Log completo del puerto 8090 (197 líneas, 11-oct 12:22 a 12-oct 09:28): los 9 `POST` son de
    `192.168.104.251` (dashboard); 0 `POST`/`PUT`/`DELETE` y 0 intentos de login desde IPs
    externas — solo escáneres de internet (ModatScanner, Infrawatch, InternetMeasurement y
    similares), todos de solo lectura, sin pasar de la pantalla de login y archivos estáticos.

- **Hueco 1 (corregido) — puerto 8090 expuesto a internet.** El 11-oct 12:22 se creó el vhost
  `megaisp-8090` (MEGANET legacy, base `meganet_prod` — distinta de `meganet_prod_claude`) y
  quedó abierto a internet ~21 h, sin que nadie externo pasara de la pantalla de login. Desactivado
  con `a2dissite` y verificado: sin listener, sin respuesta por localhost, LAN ni IP pública.
  Rollback: `a2ensite megaisp-8090 && systemctl reload apache2`.

- **Hueco 2 (corregido) — MySQL abierto en todas las interfaces.** Escuchaba en `*:3306`/`*:33060`
  con el usuario `meganet@'%'` (acepta conexión desde cualquier host, con acceso a producción) y
  sin firewall real encima. Se renombró a `meganet@localhost` y se agregó
  `/etc/mysql/conf.d/zz-bind-local.cnf` (`bind-address`/`mysqlx-bind-address` = `127.0.0.1`).
  Verificado: escucha solo en `127.0.0.1`; la app principal sigue funcionando (los dos `/login`
  responden 200, consulta la BD como `meganet@localhost`, 5,849 clientes, 0 errores de conexión,
  0 jobs fallidos). Rollback: `rm /etc/mysql/conf.d/zz-bind-local.cnf && systemctl restart mysql`.

- **Gotcha encontrado en el camino:** `/etc/mysql/my.cnf` NO incluye `mysql.conf.d/`, así que
  `mysqld.cnf` (`innodb_redo_log_capacity`, timeouts de locks, etc.) nunca se ha leído en este
  servidor — cualquier ajuste pensado para ese archivo necesita ir en `conf.d/` en su lugar.

- **Limpieza:** borrados `acc8090.log` y los 4 scripts de verificación/cierre con sus salidas. En
  `/home/meganet/backups-mapared/` solo quedan `pre-backfill`, `pre-importar` (permisos
  restringidos) y la copia `mapas-y-mapared-…1328` con su `.sha256` — el volcado completo de 151 MB
  ya se había borrado antes.

- **Quién corrió el backfill:** preguntado directamente el 2026-10-12 (corrigiendo la suposición
  de un compañero de equipo documentada arriba) — se reporta que Irving lo dio por trabajo suyo.
  **Sigue sin existir comprobación escrita de primera mano** (es su respuesta relayada, no un
  mensaje/commit suyo directo) — queda anotado así a propósito, sin inflar la certeza.

- **Pendiente real que queda:** decidir si se activan los valores de `mysqld.cnf` ahora que se
  sabe que nunca se leyeron; el servidor sigue sin firewall (`ufw` inactivo, `iptables INPUT` en
  ACCEPT) — ya sin puertos de datos expuestos, pero 80/443/22 y VoIP siguen sin esa capa.
