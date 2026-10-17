## 2026-10-17 — Runbook de producción: envíos de prefacturas y reglas de la V1.61 (#482, #514, #515, #534)

Bitácora PREVIA (regla del repo para escrituras manuales en producción). Nada de esto se ha
ejecutado. Lo corre Irving en producción (`/var/www/ClaudeMegaISP`, la base del `.env` de prod), por
etapas y solo con su aprobación de cada una. Antes de las escrituras: respaldo con
`backup_db:process` (Etapa 0.1).

### Qué está desplegado en la V1.61 (y qué no está hecho)

| Pieza | En la V1.61 | Estado en producción |
|---|---|---|
| FAC-04 #509 Factura PAGADA reemplaza recibo; revalida antes de enviar | sí | activo (actúa al enviar) |
| FAC-05 #510 PDFs ligeros + evidencia de lo enviado | sí | activo |
| FAC-06 #512 proforma anticipada | sí, sustituida por «Envío prefactura» (FR-05) | apagada (`BILLING_PROFORMA_ANTICIPADA`) |
| FAC-07 #514 `billing:cancelar-colas` | sí | **no ejecutado** |
| FAC-08 #515 compuerta `billing.reactivacion` + `billing:reactivacion-estado` | sí | etapa `cerrado`, pausa puesta |
| FR-06 estado de cuenta mensual | sí | apagado (`BILLING_ESTADO_CUENTA_MENSUAL`); **solo genera, no envía** |
| FR-07 capa de contrato | sí | apagada (`BILLING_CAPA_CONTRATO`) |

Programador: `invoice:create-proformas` 03:00 · `facturacion:estados-cuenta-mensuales` 04:00 (solo
con su interruptor; genera una vez al mes, el día 1) · `billing:send-pending-notifications` cada 15
min (respeta la pausa y la etapa; envía 12 h después de generado) · `billing_service_command:process`
00:03 (cobro; ahí actúa la capa de contrato) · `suspends_services_early_in_the_day:process` 10:00.

**#490 (Cancelado recibe proformas):** en la V1.61 ya no se GENERAN proformas ni cargos para
Cancelados. Pero el envío no revisa el estado del cliente: lo que ya estaba en la cola de un
Cancelado (4 prefacturas en la copia del 15-oct) saldría. Lo resuelve la Etapa 1 (FAC-07). Hueco
que queda: si un cliente se cancela DESPUÉS de generada su prefactura y antes de que salga, saldría.
Candado recomendado antes de la etapa «todos» (cambio pequeño para la V1.62): que el envío cancele la
prefactura de un cliente Cancelado.

### Convenciones
- Todo artisan como `www-data`: `A="sudo -u www-data php artisan"` y luego `$A …`.
- Antes de editar el `.env`: `ls -la .env` (anotar dueño, grupo y permisos) y verificar que queden
  iguales después. Tras cada cambio del `.env`:
  `$A config:clear && $A route:clear && $A queue:restart` — **nunca** `config:cache`.

### Etapa 0 — Preparación (con la pausa puesta)
0.1 Respaldo: `$A backup_db:process` (anotar el archivo).
0.2 Programador vivo:
`sudo crontab -l -u www-data | grep schedule:run` y
`$A schedule:list | grep -E "create-proformas|send-pending|estados-cuenta|procesar-pausas"`.
0.3 Foto de la cola (solo lectura):
`$A tinker --execute="dump(DB::table('billing_notifications')->selectRaw('document_type, status, COUNT(*) n, MIN(created_at) desde, MAX(created_at) hasta')->groupBy('document_type','status')->get()->toArray());"`
0.4 DKIM: el selector está en el encabezado `DKIM-Signature: … s=<selector>; d=…` del correo de
prueba que recibió Irving. Luego: `$A billing:reactivacion-estado --selector=<selector>`.
0.5 Prefactura con «Envío prefactura» (antes del 25-oct; si no, ese día la regla vieja genera las de
noviembre a todos, incluidos bloqueados): `.env` `BILLING_PROFORMA_ANTICIPADA=true`.
Validar: `$A invoice:proformas-simular`; al día siguiente, después de las 03:00:
`$A tinker --execute="dump(DB::table('invoices as i')->join('client_main_information as m','m.client_id','=','i.client_id')->where('i.type','proforma')->whereDate('i.created_at', today())->selectRaw('m.estado, COUNT(*) n')->groupBy('m.estado')->get()->toArray());"`
(debe salir solo `Activo`). Revertir: `false` + limpiar cachés (lo generado se queda, no se envía con la pausa).
0.6 (opcional) Estado de cuenta mensual: `.env` `BILLING_ESTADO_CUENTA_MENSUAL=true`. El 1-nov a las
04:00 genera ~1,235 PDFs (no se envían). Validar antes: `$A facturacion:estados-cuenta-mensuales --simular`.
Revertir: `false`.

### Etapa 1 — FAC-07: cancelar la cola vieja (día D, en la mañana, con la pausa puesta)
D = día en que arranca el piloto, **no antes de 2026-10-18** (así se va también lo que generó la V1.60
el 17-oct a las 03:00). Cancela, no borra.
1.1 Preparar:
`$A billing:cancelar-colas --preparar --cola=billing_notifications --tipos=prefactura,recibo,ticket --estados=en_espera --hasta=D`
`$A billing:cancelar-colas --preparar --cola=invoice_emails --tipos=email --estados=pending,failed --hasta=D`
1.2 Revisar cada manifiesto y anotar su sha256:
`sudo jq '.total, .criterios, .base_datos' storage/app/billing/manifiestos/<archivo>.json`
1.3 Dry-run: `$A billing:cancelar-colas --manifiesto=billing/manifiestos/<archivo>.json`
1.4 Ejecutar: `$A billing:cancelar-colas --manifiesto=billing/manifiestos/<archivo>.json --ejecutar --sha256=<sha256>`
(anotar el archivo de resultado).
1.5 Validar: repetir 0.3 (en espera solo lo creado desde D) y
`$A tinker --execute="dump(DB::table('billing_notifications as b')->join('client_main_information as m','m.client_id','=','b.client_id')->where('b.status','en_espera')->where('b.document_type','prefactura')->selectRaw('m.estado, COUNT(*) n')->groupBy('m.estado')->get()->toArray());"`
(sin Cancelados). Revertir: `$A billing:cancelar-colas --revertir=billing/manifiestos/<resultado>.json`
y luego con `--ejecutar`.

### Etapa 2 — FAC-08 piloto (día D)
2.1 `.env`: `BILLING_REACTIVACION_ETAPA=piloto`, `BILLING_REACTIVACION_CLIENTES=<ids>`,
`BILLING_REACTIVACION_LIMITE_CORRIDA=10`, `BILLING_REACTIVACION_LIMITE_DIARIO=30`,
`BILLING_REACTIVACION_MAX_ERRORES=3`. Limpiar cachés.
2.2 Validar sin enviar: `$A billing:reactivacion-estado` (pausa PUESTA, etapa piloto, alcanzables = los
del piloto) y `$A billing:send-pending-notifications --dry-run --limit=500 | tail -40`.
2.3 Quitar la pausa:
`$A tinker --execute="DB::table('billing_config')->update(['envio_pausado' => false, 'updated_at' => now()]); dump(DB::table('billing_config')->value('envio_pausado'));"`
2.4 Validar en cada corrida (cada 15 min) y por 2-3 días:
`$A tinker --execute="dump(DB::table('billing_notifications')->whereDate('updated_at', today())->whereIn('status',['enviado','error'])->selectRaw('status, document_type, COUNT(*) n')->groupBy('status','document_type')->get()->toArray()); dump(DB::table('billing_notifications')->where('status','error')->whereDate('updated_at', today())->limit(10)->pluck('error_log','id')->toArray()); dump(DB::table('billing_documento_evidencias')->whereDate('created_at', today())->selectRaw('estado, COUNT(*) n')->groupBy('estado')->get()->toArray());"`
Los clientes piloto confirman bandeja de entrada (no spam), PDF correcto y aviso de privacidad.
2.5 Revertir (inmediato):
`$A tinker --execute="DB::table('billing_config')->update(['envio_pausado' => true, 'updated_at' => now()]);"`
o `BILLING_REACTIVACION_ETAPA=cerrado` + limpiar cachés.

### Etapa 3 — Zona (requisito: DKIM verificado)
`BILLING_REACTIVACION_ETAPA=zona`, `BILLING_REACTIVACION_COLONIAS=<ids>` y/o
`BILLING_REACTIVACION_MUNICIPIOS=<ids>`, límites 50 / 200. Validar y revertir como en la Etapa 2.

### Etapa 4 — Todos (requisito: candado de Cancelado en el envío, V1.62)
`BILLING_REACTIVACION_ETAPA=todos` con el límite diario del proveedor de correo. Validar y revertir
como en la Etapa 2. Después, opcional: DMARC `p=quarantine`.

### Etapa 5 — Capa de contrato (requisito: revisión legal #523)
Antes: `$A facturacion:reglas-simular --detalle`. `.env` `BILLING_CAPA_CONTRATO=true`. Validar al día
siguiente (después del cobro de las 00:03):
`$A tinker --execute="dump(DB::table('client_contrato_cargos')->whereDate('created_at', today())->selectRaw('estado_contrato, COUNT(*) n')->groupBy('estado_contrato')->get()->toArray()); dump(DB::table('client_historial_cambios')->where('origen','sistema')->whereDate('created_at', today())->count());"`
Revertir: `false` + limpiar cachés. **Los cargos ya hechos no se deshacen solos.**

## 2026-10-17 16:20 — Etapa 0 ejecutada en producción (parcial)

Lo corrió Irving en producción (`/var/www/ClaudeMegaISP`); lo reportó Cowork. Claude Code no
ejecutó nada en producción.

**Hecho**
- 0.1 Respaldo `backup_db:process` OK: `megaisp-202610171620.sql.gz` (152.22 MB).
- 0.2 `schedule:list`: aparecen `invoice:create-proformas` 03:00 y `billing:send-pending-notifications`
  cada 15 min. `facturacion:estados-cuenta-mensuales` no aparece (correcto: su interruptor está apagado).
- 0.2 `sudo crontab -l -u www-data` → «no crontab for www-data»: el `schedule:run` no está en el
  crontab de `www-data`. Se está verificando con qué usuario corre.
- 0.5 `BILLING_PROFORMA_ANTICIPADA=true` en el `.env`. Dueño y permisos sin cambio
  (`meganet:www-data`, `rw-r-----`). `config:clear`, `route:clear` y `queue:restart` OK.
- La pausa de envíos sigue puesta y la etapa sigue en `cerrado`: no sale nada.

**Falta**
- 0.3 Foto de la cola.
- 0.4 DKIM.
- Validar 0.5 el 18-oct, después de las 03:00: las proformas creadas ese día deben ser solo de `Activo`.
- 0.6 (opcional) no se hizo.

**Puntos que salen de lo ejecutado**
1. *Usuario del programador.* La cola de la copia del 15-oct tiene prefacturas generadas a las
   03:00, así que el programador sí corre; falta saber con qué usuario. Para ubicarlo (solo lectura):
   `sudo crontab -l | grep schedule:run`, `sudo crontab -l -u meganet | grep schedule:run`,
   `sudo grep -rn "schedule:run" /etc/crontab /etc/cron.d/`. Si no es `www-data`, los archivos que
   cree el programador quedan a nombre de ese usuario. No es nuevo (producción ya corre así), pero
   conviene revisar el 18-oct que la web los pueda leer: `sudo ls -la storage/app/billing/pdf | tail -5`.
2. *Ajuste a la Etapa 1: usar `--hasta=2026-10-18` fijo, no el día del piloto.* `billing:cancelar-colas`
   cancela lo creado antes de `--hasta` (`created_at < --hasta`). Con la anticipada encendida hoy, desde
   el 18-oct a las 03:00 se generan prefacturas válidas (solo `Activo`). Si el piloto arranca después
   del 18-oct y se usa `--hasta=<día del piloto>`, se cancelarían también esas. Esos clientes se
   quedarían sin prefactura del mes, porque la proforma ya existe para ese periodo y no se vuelve a
   generar. Con `--hasta=2026-10-18` se va todo lo de la V1.60 (incluido lo del 17-oct a las 03:00) y
   se conserva lo nuevo. La validación 1.5 queda: en espera, solo lo creado desde el 18-oct.

**Complemento (Cowork, mismo día)**

*Programador en el crontab de root.* `* * * * * cd /var/www/ClaudeMegaISP && php artisan schedule:run`;
no hay crontab de `www-data` ni de `meganet`. Todo lo programado corre como root y puede dejar archivos
con dueño root que `www-data` (la web y los workers) no pueda escribir:
- Logs diarios. Ningún canal de `config/logging.php` fija `permission`. Si root crea primero el
  archivo del día (`laravel-AAAA-MM-DD.log` u otro canal), queda `root:root 0644` y `www-data` ya no
  puede escribir en él. Los errores de la web se pierden, y un fallo al escribir el log puede
  convertirse en un 500.
- Carpetas nuevas en `storage/app`. El disco `local` no define `permissions`, así que Laravel las crea
  `0700`. Una carpeta creada por root queda inaccesible para la web; es lo mismo que pasó en dev con la
  carpeta de estados de cuenta (404). Los archivos (PDF de prefactura, evidencias de FAC-05, estados de
  cuenta mensuales, todos en `billing/pdf`) quedan `0644`: la web los lee, pero no los puede
  sobrescribir. Si `billing/pdf` misma fuera de root, el estado de cuenta final que genera la web al
  cancelar fallaría y quedaría anotado en su columna `error`.
- Caché en archivos y vistas compiladas. Si `CACHE_DRIVER=file`, root crea subcarpetas de
  `storage/framework/cache` en las que `www-data` no puede escribir; eso incluye la caché de permisos.
  Los PDF se arman con Blade, así que root también deja vistas compiladas en `storage/framework/views`
  que la web no puede recompilar después de un deploy.

Revisión de solo lectura (hoy se puede):
`sudo find storage bootstrap/cache -user root | wc -l` y
`sudo find storage bootstrap/cache -user root -printf '%u:%g %m %p\n' | head -40`.

Recomendación: mover el programador al crontab de `www-data`. **No hoy**: en un día hábil tranquilo,
con el piloto ya estable y antes de la etapa «zona». Evitar la madrugada (00:00–05:00), el día 1 y del
25 al 28. Pasos:
1. `$A schedule:list` completo: identificar tareas que de verdad necesiten root. Por ejemplo,
   `backup_db:process` escribe en `/var/backups/mysql` (debe quedar `www-data:www-data 750`), y las de
   VoIP llaman a `asterisk`, que necesitaría sudoers.
2. `sudo find storage bootstrap/cache -user root -exec chown www-data:www-data {} +`.
3. En la misma ventana, quitar la línea del crontab de root y agregarla al de `www-data`
   (`sudo crontab -u www-data -e`).
   **Nunca las dos a la vez**: correría cada tarea dos veces, con cobros, proformas y envíos duplicados.
4. Revisar al minuto siguiente: `sudo -u www-data php artisan schedule:list` y el log del día.
   Revertir: regresar la línea a root.

*Foto de la cola (0.3) en producción.*

| Tipo | Estado | Filas | Creadas |
|---|---|---|---|
| ticket | en espera | 8,411 | 2026-06-05 → 2026-10-17 15:29 |
| recibo | en espera | 8,411 | 2026-06-05 → 2026-10-17 15:29 |
| prefactura | en espera | 7,309 | 2026-06-13 → 2026-10-17 03:00 |
| prefactura | cancelado | 6 | — |

Total en espera: 24,131.

La Etapa 1 lo cubre:
- El manifiesto 1.1 de `billing_notifications` (`--tipos=prefactura,recibo,ticket --estados=en_espera`)
  toma los 24,131 y lo que se agregue durante el 17-oct.
- Las 6 ya canceladas no se tocan.
- `invoice_emails` va en su propio manifiesto.

¿Se cancelan los recibos y tickets de pagos del propio día D con `--hasta=D`? **No.** `--hasta` es
estricto (`created_at < D 00:00`), así que lo creado el mismo día D se conserva: recibos, tickets y las
prefacturas de las 03:00. Con `--hasta=D` se cancela todo lo de los días anteriores a D. Si D es
posterior al 18-oct, eso incluye recibos, tickets y prefacturas válidas de la V1.61 del 18-oct a D−1.
Para evitarlo: `--hasta=2026-10-18` fijo (punto 2 de arriba). Solo se cancela lo de la V1.60 y los
recibos y tickets del 17-oct.

Queda por decidir antes de las etapas 3 y 4 qué hacer con lo que se junte desde el 18-oct fuera del
piloto: enviarlo o cancelarlo con un segundo manifiesto (`--desde=2026-10-18 --hasta=<fecha>`). El
envío no tiene una antigüedad máxima.
