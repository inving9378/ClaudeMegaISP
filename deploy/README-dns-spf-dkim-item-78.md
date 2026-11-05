# Runbook — SPF / DKIM / DMARC para meganett.com.mx (item #78)

Acción manual de Irving en el DNS del dominio (registrador / cPanel). El circuito no tiene acceso a DNS.

1. **SPF** — TXT en la raíz (`@`): `v=spf1 +a +mx include:<PROVEEDOR_CORREO> ~all`
   (usar el include del proveedor que realmente envía: cPanel/hosting, SMTP externo, etc.).
2. **DKIM** — cPanel → Email Deliverability → generar/activar DKIM; copiar el TXT `default._domainkey` al DNS.
3. **DMARC** (recomendado) — TXT `_dmarc`: `v=DMARC1; p=none; rua=mailto:<BUZON_REPORTES>` ; endurecer a `quarantine` tras revisar reportes.
4. **Verificar** — `dig +short TXT meganett.com.mx`, `dig +short TXT default._domainkey.meganett.com.mx`, `dig +short TXT _dmarc.meganett.com.mx`; enviar correo de prueba a Gmail y revisar "Mostrar original" (SPF/DKIM/DMARC = PASS).
5. Ya que `MAIL_FROM_ADDRESS` esté definido en el `.env` de PROD con un remitente `@meganett.com.mx` (ver nota de Flotas 3.3).

Rollback: eliminar los TXT agregados.
