# Borrador de versión — Facturación, lote 1 (SIN número oficial, sin publicar)

> Borrador para Irving. **No** es una versión emitida: no hay tag, push ni release (la instrucción
> vigente no autoriza publicar). Se suma a lo ya preparado para V1.61
> (`docs/bitacora/2026-10-16-release-v161-preparacion.md`), que tampoco está publicado.

- **Incremento desde:** `7795cc56` (inicio del programa «Facturación completa», después de cerrar
  los pendientes de V1.61) hasta el merge `42ad2b5d`.
- **Contenido:** FAC-01 (#506), FAC-02 (#507), FAC-03 (#508) + preparación del aislamiento de correo.

## Para usuarios
- **Aviso de privacidad en la Prefactura/Factura:** nuevo campo «URL del aviso de privacidad» en
  Configuración › Información de la empresa. Si se llena, el PDF muestra «Aviso de privacidad:
  <enlace>» al pie; si está vacío no aparece nada. Solo acepta direcciones http/https.
- Guía para mostrador y textos para pedir la Constancia de Situación Fiscal (documentación; no
  cambia pantallas).

## Commits
| Item | Commits | Merge |
|---|---|---|
| Interceptación de correo de prueba | `3ea55c32` `198cbc9a` `c839fa77` | `e972a27e` |
| FAC-01 #506 | `4a706d7c` | `052ad6d0` |
| FAC-02 #507 | `a3997f0a` | `ac9e462e` |
| FAC-03 #508 | `53b9825c` `cdbbdeb7` `4d775b27` `d1ea463c` | `34054ffd`, `42ad2b5d` |

## Migraciones (aditivas)
1. `2026_10_16_200000_agrega_url_aviso_privacidad_a_empresa` — columna nullable
   `company_information.url_aviso_privacidad` + campo en el formulario (field_modules del módulo
   CompanyInformation, regla `nullable|url:http,https|max:500`). `down()` quita ambos.
2. `2026_10_16_200100_agrega_aviso_privacidad_a_plantilla_prefactura` — agrega
   `${data.aviso_privacidad}` al pie de «Prefactura.» **solo** desde un HTML conocido y si el
   resultado da `6fc0c53c…`; UPDATE condicionado al SHA1 leído; HTML personalizado → no-op +
   `Log::warning`. En prod la migración `…170000` (V1.61) ya deja la plantilla en esta versión y
   esta encuentra `ya_aplicada`.
   - Además: el `down()` de `…170000` ahora reconoce las 3 versiones ajustadas.

## Configuración
- Nueva clave `mail.interceptar` (`MAIL_INTERCEPTAR`, `MAIL_INTERCEPTAR_DESTINO`), **apagada por
  defecto**. En prod no se define (o `false`). **Nunca copiar el `.env` de dev.**
- Sin cambios obligatorios de `.env`.

## Validación hecha (dev)
- Unitarias: interceptación 7/7; provider + actualizador + monto en letras 50/50.
- `deploy:dry-run-migrations --with-data=document_templates,company_information,modules,field_modules` OK.
- PDF con/sin URL y con URL inválida (transacción revertida); formulario claro/oscuro; POST con
  `javascript:` → 422.
- Muestra `FAC-MUESTRA-L1-01` (sintética) aceptada por el SMTP de dev, 1 destinatario.

## Checklist de verificación en PROD (cuando Irving despliegue)
- [ ] `php artisan prefactura:actualizar-plantilla --dry-run` → `Situación: ya_aplicada`.
- [ ] Configuración › Información de la empresa muestra «URL del aviso de privacidad» (vacío).
- [ ] Con el campo vacío, la Prefactura no muestra la línea del aviso.
- [ ] Irving captura la URL real → la línea aparece.
- [ ] `grep MAIL_INTERCEPTAR .env` en prod → **nada** (o `false`).

## Rollback
- Código: revertir los merges listados (no destructivo).
- Plantilla: `php artisan migrate:rollback --path=database/migrations/2026_10_16_200100_agrega_aviso_privacidad_a_plantilla_prefactura.php`
  (quita solo el cambio 6 si la plantilla sigue igual).
- Columna: rollback de `…200000` (borra la URL capturada; anotarla antes).

## Decisiones de Irving
- Capturar la URL real del aviso de privacidad (dev y prod).
- Cuándo enviar la guía (FAC-01) y por dónde pedir la CSF (FAC-02).
- Aplicar la interceptación al `.env` compartido de dev (coordinación en #502).
