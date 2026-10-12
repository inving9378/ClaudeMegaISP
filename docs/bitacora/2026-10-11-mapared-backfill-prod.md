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

- [ ] **Preguntarle a Irving directamente** si él corrió este backfill — todavía no se le ha
      preguntado. Ver "Actualización" abajo: lo que hay hoy es la suposición de un compañero del
      equipo, no una respuesta de Irving.
- [ ] Correr verificación de intrusión (auth.log, nginx, binlog de MySQL — requieren root). Sigue
      sin correrse. La pista técnica real para descartar intrusión de verdad sigue sin existir.
- [ ] Decidir si se conservan los datos del backfill.

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
