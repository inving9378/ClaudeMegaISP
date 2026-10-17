# Borrador de versión — Facturación, lote 2 (SIN número oficial, sin publicar)

> Borrador para Irving. No hay tag, push ni release. Se suma al lote 1 y a lo preparado para V1.61.

- **Incremento desde:** `42ad2b5d` (cierre del lote 1) hasta `dbeff1da`.
- **Contenido:** FAC-04 (#509), FAC-05 (#510), FAC-06 (#512) — unidad de notificaciones de cobro
  (padre #482). **Nada se activa:** la pausa `envio_pausado` sigue puesta y el interruptor de la
  proforma anticipada va apagado.

## Para usuarios (cuando se reactiven los envíos, FAC-08)
- Al pagarse una factura, el cliente recibe su **Factura (PAGADA)** en lugar del recibo, y ya no le
  llega la prefactura de algo que pagó. Los abonos parciales siguen con su recibo.
- Los pagos por SPEI/conciliación ahora sí generan su ticket y recibo.
- Los PDFs de recibo/prefactura pasan de ~1.1–1.3 MB a ~57 KB.

## Commits
| Item | Commits | Merge |
|---|---|---|
| FAC-04 #509 | `b9d3a5c5` `7c50b96a` `a40f4946` | `3917580d` |
| FAC-05 #510 | `9316cec9` `82520cff` `cb770c56` `0957425d` | `d63c6f31` |
| FAC-06 #512 | `831051f5` `fd4bb50f` `758f0d56` | `dbeff1da` |
| bitácoras | `b3ffd0c6` `c3419947` `2183d726` | `03a418ea` `85560e0f` |

## Migraciones (aditivas)
1. `2026_10_16_210000_crea_billing_documento_evidencias` — tabla nueva (evidencia de PDFs
   enviados). `down()` la borra (perdería las evidencias: respaldar `storage/app/billing/pdf/enviados`
   y la tabla antes).

## Configuración
- `billing.proforma_anticipada` ← `BILLING_PROFORMA_ANTICIPADA` (default **false**). No definirlo en
  prod hasta la decisión de #513.
- Nuevos listeners de correo (`MessageSending` interceptación — apagada; `MessageSent` evidencias).
- Requiere `php artisan queue:restart` en prod al desplegar (cambian `StandardMail`, `BillingMail` y
  observers que usan los workers).

## Validación hecha (dev)
- FAC-04: 12 pruebas con BD (rollback) + datos reales de dev en rollback.
- FAC-05: 6 pruebas con BD + inventario en dev; recibo 1.28 MB → 57 KB con acentos correctos.
- FAC-06: 8 unitarias + 5 con BD; simulación en dev.
- ⚠️ Desde el incidente #529 (`megaisp_test` a medio migrar) las pruebas con BD no se pueden
  re-correr hasta reconstruirla.
- No probado: «Enviar» manual real con evidencia (sería correo a cliente); dos barridos de envío
  en paralelo reales.

## Checklist en PROD (al desplegar, sin activar nada)
- [ ] `php artisan migrate` aplica `…210000` (tabla de evidencias).
- [ ] `php artisan queue:restart`.
- [ ] `php artisan billing:send-pending-notifications --dry-run --limit=20` → cada fila muestra su
      decisión (`enviar`/`cancelar`) **sin enviar** (con la pausa puesta, el modo normal no hace nada).
- [ ] Consulta de solo lectura: prefacturas en espera de facturas ya pagadas (ver
      `docs/bitacora/2026-10-16-item-509.md`).
- [ ] `php artisan billing:pdfs-inventario --dias=90` y `du -sh storage/app/billing/pdf`.
- [ ] `php artisan invoice:proformas-simular --dias=21`.
- [ ] `grep BILLING_PROFORMA_ANTICIPADA .env` → nada (apagado).

## Rollback
- Revertir los merges listados (no destructivo). La tabla de evidencias puede quedarse (vacía o
  con evidencias); si se revierte su migración, respaldar antes.

## Decisiones de Irving
- #511 (FAC-05b) política de retención/borrado de PDFs.
- #513 (FAC-06b) N, naturales/hábiles y activación.
- #529 reconstruir `megaisp_test`.
- Reactivación de envíos: FAC-08 (#515).
