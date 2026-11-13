# Meta Ads (ads_read): qué app usa hoy LM y dónde se capturan token y cuenta

Revisado el 12-nov en DEV, solo lectura y sin imprimir ningún secreto de Meta. **PROD no se pudo leer desde dev.**

## 1. ¿Qué app de Meta usa hoy la captura de leads?
- **La captura de leads no usa el Hub de integraciones.** El webhook `/webhooks/marketing/meta-ads` (`MetaAdsWebhookController`) y `MetaGraphApiService` leen tres valores de `marketing_settings`:
  - `meta_verify_token`
  - `meta_app_secret`
  - `meta_page_access_token`
- **En DEV esas tres claves no existen.** La captura de leads de Meta nunca se configuró aquí.
- **La fila del Hub `meta-business`** («Meta Business (Facebook + Instagram)», id 7) está **inactiva**. En su configuración `app_id` y `app_secret` están en `null`. Solo la usan la conexión OAuth (`MetaOAuthController`) y la renovación de tokens de Publicación.
- **Conclusión:** en DEV no hay ninguna app de Meta registrada para Marketing, así que no se puede dar un nombre ni un ID. En PROD hay que verlo con una sesión allá:
  - Hub `/integraciones` → tarjeta «Meta Business» → App ID;
  - o los `marketing_settings` `meta_*`.

  El App ID que aparezca ahí se compara contra el de la app de Sofía en Meta Business.
- La fila del Hub «WhatsApp oficial (Meta)» (`whatsapp-oficial-meganet`) es otra integración. Marketing no la usa.

## 2. ¿Dónde se captura el token y la cuenta `act_…`?
- **Token:** Hub `/integraciones` → pestaña «Integraciones» → tarjeta **Meta Business (Facebook + Instagram)** → Editar. El formulario trae tres campos: la llave/token (tipo contraseña), **App ID** y **App Secret**.
- **ID de cuenta publicitaria `act_…`: hoy no hay ningún campo en ninguna pantalla.** El plan (Fase 5) lo guarda por marca en `marketing_empresas.meta_ad_account_id`. Se capturará en Marketing → «Empresas y equipo» → editar la marca. El Hub no se toca: es de todo LM y queda fuera de lo autorizado.
