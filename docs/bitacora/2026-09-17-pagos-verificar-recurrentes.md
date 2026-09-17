## 2026-09-17 12:xx — Verificación diaria de pagos + ticket automático en la Torre de Control

**Pedido de Irving:** "crea los test necesarios para poder comprobar los pagos y que corran de
forma recurrente cada día y si fallan que se levante un ticket para resolver el error en la
torre de control, que corra antes que acabe el día entre las 11 y las 12 de la noche."

**Decisión de diseño:** en vez de PHPUnit (el proyecto no tiene `.env.testing`/`megaisp_test`
configurado en este entorno — `TestCase.php` corre `migrate:fresh --seed`, que no se puede
disparar sin riesgo en un cron nocturno sobre la BD compartida de dev), se construyó un
**comando Artisan de verificación** (`pagos:verificar-recurrentes`), programable en
`Kernel.php` igual que el resto de los jobs reales del sistema (`invoice:create-proformas`,
`backup_db:process`, etc.) y con el mismo rigor de verificación usado en todo este bloque de
sesión (mutar en memoria, nunca `->save()`, restaurar `Carbon::setTestNow()` siempre en
`finally`).

**4 chequeos:**
1. **`recurrent_pago_tarde`** — invariante de regresión del bug corregido hoy (commit
   `4f8806fb`): un pago 5 días tarde debe dar una fecha de corte POSTERIOR a un pago a tiempo,
   nunca igual ni menor. Corre contra un cliente RECURRENT real, mutando `fecha_corte`/
   `fecha_pago` solo en memoria.
2. **`custom_pago_tarde`** — mismo invariante para el bug hermano en CUSTOM (commit `f7d7f969`).
3. **`daily_now_independiente`** — DAILY no debe depender de `Carbon::now()` (red de regresión
   estructural; hoy DAILY ya es inmune por diseño, pero el chequeo atrapa si algún día deja de
   serlo).
4. **`webhooks_sin_duplicado`** — 100% de solo lectura sobre datos reales: ningún
   `(provider, external_id)` en `payment_webhooks_log` debe tener más de una fila
   `status=processed` (eso sería una transacción del proveedor aplicada dos veces al saldo de
   un cliente).

**Ticket automático en fallo:** usa el mismo mecanismo de dedupe que `AuditorService`
(columna `auditor_fingerprint`, huella estable por chequeo) — si el mismo chequeo sigue
fallando noche tras noche, actualiza el ticket existente (`estado_aprobacion=requiere_irving`)
en vez de crear uno nuevo cada vez. Nivel de riesgo `B` (dinero) — nunca se auto-ejecuta.

**Verificado:**
- `php artisan pagos:verificar-recurrentes --dry-run` en vivo: los 4 chequeos pasan contra
  datos reales (cliente #17 RECURRENT: a tiempo=2026-07-30, tarde=2026-08-06; cliente #19
  CUSTOM: a tiempo=2026-07-28, tarde=2026-08-04; sin cliente DAILY en esta BD, chequeo
  omitido sin marcar falla; 0 webhooks duplicados).
- Mecanismo de creación/dedupe de ticket probado por separado con una `check_key` de prueba
  (invocado 2 veces vía reflection): 1 solo item creado, 3 entradas de log acumuladas (incluye
  una corrida previa que había creado el item y luego truncó por un error de bootstrap del
  output de consola en tinker — confirma dedupe también entre procesos separados, no solo
  dentro de la misma corrida). Item de prueba borrado tras verificar.
- `schedule:list` confirma `pagos:verificar-recurrentes` a las `23:15` diario, dentro de la
  ventana pedida (23:00-24:00).

**Archivos:** `app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php` (nuevo),
`app/Console/Kernel.php` (+7 líneas, registro del schedule).
