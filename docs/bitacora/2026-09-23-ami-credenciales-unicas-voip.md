## 2026-09-23 17:05 — Confirmado: AMI ya es una sola conexión compartida; limpieza del duplicado muerto

Irving preguntó por las credenciales AMI del módulo VoIP ("nunca he usado eso, reúnelo todo en
una sola, compartiendo todo, si se puede"). Investigado antes de tocar nada: **ya está
consolidado desde un item anterior (#9990718 §6)** — `AsteriskProvisioningService` (VoIP) delega
en `App\Modules\Core\Voice\AmiClient`, el cliente AMI único del sistema (su propio docblock lo
dice: *"VoIP/María y CobranzaBlaster... deben hablar AMI por aquí, no por sockets propios"*), que
lee las MISMAS credenciales `AMI_HOST/AMI_PORT/AMI_USERNAME/AMI_SECRET/AMI_CONTEXT` que ya usa
`AmiConnectionService` de CobranzaBlaster (ambos vía `config('voip.ami_*')`). No hay dos motores
de conexión con credenciales distintas — hay uno solo, ya compartido.

Lo único que sobraba: 4 líneas muertas en `.env` (`ASTERISK_AMI_HOST/PORT/USER/PASS`, un juego de
credenciales paralelo que **nadie leía** — confirmado por grep completo de `app/`, ni un archivo
PHP las referenciaba) con la contraseña todavía en el placeholder sin rotar
`CAMBIAR_AMI_PASS_VOIP` desde que se crearon. Borradas junto con su comentario de cabecera.
**No se tocó** `ASTERISK_AMI_PERMIT` (variable distinta y sí activa, ACL de `manager.conf`).

Verificado tras el borrado: el sitio sigue respondiendo (`curl` → 302 normal), el grupo del
`.env` se restauró a `www-data` (el Edit tool lo resetea a `meganet` en cada edición — gotcha ya
documentado, se corrigió con `chgrp` de inmediato), y no quedó ninguna referencia viva a las 4
variables borradas (solo un comentario histórico en `GeneradorCredenciales.php` que las cita como
ejemplo pasado, sin depender de que existan).

**Deuda de arquitectura identificada pero NO tocada** (a propósito, ya diferida por el propio
código en el item #185): `AmiConnectionService` (CobranzaBlaster) es una conexión AMI
**persistente**, reusada en lote por el blaster de llamadas — técnicamente distinta de `AmiClient`
(conexión **stateless**, abre/loguea/envía/cierra por cada acción). Ambas leen las mismas
credenciales, pero son dos implementaciones de socket separadas por una razón real (eficiencia del
blast en caliente vs. simplicidad de una llamada puntual). El propio código dice que migrar
`AmiConnectionService` a `AmiClient` "requiere validar contra la troncal real antes de tocarlo" —
fuera de alcance de lo que pidió Irving hoy (que era sobre las CREDENCIALES, no sobre unificar las
dos clases de conexión). `AmiConnectionService` también la usa `Domiciliacion/Commands/
DomiciliacionCobrarCommand.php`, hallazgo nuevo sin relación con VoIP/cobranza telefónica.
