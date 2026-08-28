# OpenPay producción — checklist de activación (item Roadmap #142)

Guía para que **Irving** ejecute a mano, en PROD, el paso a producción de OpenPay. El
circuito de dev **no toca** producción, dinero real ni credenciales — este documento es
la preparación (nivel A, aditivo/reversible); la ejecución sigue siendo 100% manual.

## Fix de código aplicado en esta vuelta (dev)

`OpenpayService::__construct()` (`app/Modules/Addons/PortalCliente/Services/OpenpayService.php`)
tenía un guard que **bloqueaba producción sin importar el `.env`**: lanzaba
`RuntimeException` si `OPENPAY_SANDBOX` no era `true`, y además llamaba
`Openpay::setSandboxMode(true)` **hardcodeado** — el SDK nunca iba a pegarle al endpoint
live aunque alguien pusiera `OPENPAY_SANDBOX=false`. Esto afectaba tanto al cobro de
tarjeta del Portal Cliente como a Domiciliación (`DomiciliacionCobrarCommand` reusa este
mismo servicio). Se corrigió para que `config('openpay.sandbox')` (env `OPENPAY_SANDBOX`)
sea el **único** interruptor real: `setSandboxMode((bool) config('openpay.sandbox'))`.
Verificado en tinker: con `sandbox=false` simulado, el SDK resuelve
`https://api.openpay.mx/v1` (antes: excepción). Sin este fix, los pasos de abajo NO
hubieran funcionado nunca, sin importar qué se pusiera en el `.env` de prod.

## Los 5 pasos de configuración (ya documentados en CLAUDE.md, repetidos aquí como checklist)

1. Completar certificación de sitio en `dashboard.openpay.mx` (hoy ~35%).
2. Sustituir llaves sandbox por las de producción **solo en `.env` de PROD**
   (`OPENPAY_ID`, `OPENPAY_PUBLIC_KEY`, `OPENPAY_PRIVATE_KEY`) — nunca en dev, nunca en
   un archivo versionado.
3. `OPENPAY_SANDBOX=false` en el `.env` de PROD.
4. Publicar `portal.meganet.mx` con nginx + certbot (requisito para que OpenPay pueda
   llamar al webhook).
5. Configurar el webhook en el dashboard de OpenPay:
   `https://portal.meganet.mx/portal/openpay/webhook`.

Tras editar `.env`: warm-up estándar — `config:clear && route:clear && queue:restart`
(**nunca `config:cache` a ciegas**; seguir el candado `config:auditar-env` documentado en
CLAUDE.md).

## ⚠️ Dos fuentes de credenciales que NO se sincronizan (ver `docs/openpay-dos-fuentes-credenciales-item227.md`)

Flipear `OPENPAY_SANDBOX=false` en `.env` **solo** activa producción para
`PortalCliente\OpenpayService` (cobro de tarjeta + domiciliación recurrente). El servicio
`Payments\OpenPayService` (CLABE virtual / SPEI) lee sus credenciales y su propio flag
`sandbox` de la tabla `payment_providers` (BD, cifrado, **por registro**) — es
independiente. Si el objetivo es "todo OpenPay en producción", hay que revisar también
el/los `PaymentProvider` usados para CLABE, no asumir que el `.env` los cubre.

## Kill switch aparte para Domiciliación (cobro recurrente)

`DomiciliacionCobrarCommand` tiene su propio freno, independiente de `OPENPAY_SANDBOX`:
`DOMICILIACION_COBRO_LIVE_ENABLED` (default seguro `false`). Poner `OPENPAY_SANDBOX=false`
NO activa por sí solo los cobros recurrentes reales — hace falta además este segundo flag
en `true`, decisión explícita y separada de Irving (documentado en el checklist
pre-deploy de CLAUDE.md, punto 7).

## Criterios de aceptación antes de dar por certificado el módulo (respuesta q4 del item #142)

Con llaves de producción reales y `OPENPAY_SANDBOX=false`, antes de abrir a todo el
padrón, validar en PROD:

- (a) Cobro exitoso real de monto bajo.
- (b) Cobro fallido manejado con mensaje claro al cliente (`humanizeError()` ya cubre los
  códigos 3001-3011).
- (c) Webhook de confirmación recibido y conciliado (`OpenpayWebhookController`).
- (d) Reembolso probado.
- (e) Logs sin PII de tarjeta (el PAN nunca toca el backend — solo token de `openpay.js`;
  verificar que ningún log imprima el token completo ni datos del `customer` de forma
  innecesaria).

## Recomendación de secuencia (respuesta q3 del item #142, ya aprobada)

`OPENPAY_SANDBOX` como flag único, en runtime, sin `config:cache` intermedio: Irving lo
activa en una ventana acordada, monitorea los primeros cobros reales y puede revertir
(`OPENPAY_SANDBOX=true` + warm-up) en segundos si algo falla.
