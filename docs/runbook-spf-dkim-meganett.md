# Runbook — SPF / DKIM / DMARC para `meganett.com.mx` (item Roadmap #78)

**Estado:** documentación preparada por el Circuito CC. **La aplicación real de los
registros DNS es un paso MANUAL fuera de este repositorio** (requiere acceso al panel
DNS del dominio, típicamente cPanel o el panel del registrador). Ningún agente de código
puede ejecutar este paso — no hay credenciales de DNS en el entorno de dev ni debe
haberlas.

## Por qué importa

Sin SPF/DKIM, Gmail/Outlook/Hotmail marcan como SPAM (o rechazan) el correo saliente de
`meganett.com.mx`. Afecta: recibos de pago, notificaciones de facturación, correo de
Marketing, recuperación de contraseña del Portal Cliente.

## Configuración actual detectada (`config/mail.php` + `.env` de este entorno)

- `MAIL_MAILER=smtp`
- `MAIL_HOST=mail.meganett.com.mx` (correo saliente vía el propio hosting del dominio,
  típicamente cPanel/Exim)
- `MAIL_FROM_ADDRESS=no-reply@meganett.com.mx`

Esto sugiere que el proveedor SMTP es el **hosting del propio dominio** (cPanel), no un
servicio externo tipo SendGrid/Mailgun/SES. Si eso es correcto, cPanel casi siempre trae
una herramienta de un clic ("Email Deliverability") que genera el SPF y DKIM correctos
automáticamente — es la vía más segura (evita error humano en el TXT).

⚠️ **Confirmar antes de tocar DNS:** ¿todo el correo saliente de MegaISP sale por este
mismo host, o hay otro servicio (ej. WhatsApp/Marketing usa Evolution API, no SMTP, así
que no aplica)? Si en el futuro se agrega un proveedor SMTP transaccional adicional
(SendGrid, SES, etc.), el SPF debe incluir también su rango, o sus correos se marcarán
como spoofing.

## Paso 1 — SPF (registro TXT en la raíz del dominio)

En el DNS de `meganett.com.mx`, agregar registro **TXT** en `@` (raíz):

```
v=spf1 a mx include:mail.meganett.com.mx ~all
```

- `~all` (softfail) para empezar — NO usar `-all` (hardfail) hasta confirmar que no hay
  otros emisores legítimos; `-all` prematuro puede **bloquear correo real**.
- Si cPanel expone su propio SPF autogenerado (Email Deliverability), preferir ese valor
  tal cual lo da la herramienta — ya contempla los servidores de envío reales del hosting.

## Paso 2 — DKIM

1. En cPanel → **Email Deliverability** (o **Authentication** en WHM), generar el par
   DKIM para `meganett.com.mx` si no existe ya.
2. cPanel entrega el registro TXT exacto, algo como:
   ```
   Host:  default._domainkey.meganett.com.mx
   Valor: v=DKIM1; k=rsa; p=<clave pública larga>
   ```
3. Agregar ese TXT tal cual lo da cPanel (no editar el `p=`).
4. Verificar con la misma herramienta de cPanel ("Manage" → muestra ✅ si el DNS ya
   propagó) o `dig txt default._domainkey.meganett.com.mx`.

## Paso 3 — DMARC (recomendado, opcional para el mínimo viable)

Registro **TXT** en `_dmarc.meganett.com.mx`:

```
v=DMARC1; p=none; rua=mailto:no-reply@meganett.com.mx; fo=1
```

- Empezar con `p=none` (solo monitoreo, no rechaza nada) durante 1-2 semanas.
- Revisar los reportes agregados (`rua`) para confirmar que SPF/DKIM alinean en el 100%
  del correo real antes de subir a `p=quarantine` y luego `p=reject`.

## Verificación post-cambio

- `dig txt meganett.com.mx` → debe incluir la línea `v=spf1 ...`
- `dig txt default._domainkey.meganett.com.mx` → debe incluir la línea `v=DKIM1 ...`
- `dig txt _dmarc.meganett.com.mx` → debe incluir la línea `v=DMARC1 ...`
- Enviar un correo de prueba real (ej. recuperación de contraseña del Portal Cliente) a
  una cuenta de Gmail y revisar "Mostrar original" → `SPF: PASS`, `DKIM: PASS`,
  `DMARC: PASS`.
- Propagación DNS: hasta 24-48h según el TTL del registrador.

## Qué NO hace este runbook

- No modifica ningún registro DNS real (eso queda para quien tenga acceso al panel).
- No cambia nada en `.env` ni en `config/mail.php` de este repo.
- No toca producción ni ningún sistema vivo.

## Siguiente paso operativo (fuera del Circuito)

Irving (o quien tenga acceso al DNS/cPanel de `meganett.com.mx`) aplica los pasos 1-3
manualmente y corre la verificación. Tiempo estimado: 30-60 min + espera de propagación.
