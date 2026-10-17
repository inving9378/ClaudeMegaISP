# Borrador de versión — Facturación, lote 5 (SIN número oficial, sin publicar)

> Irving decidió **no publicar todavía**. Se suma a V1.61 y a los lotes 1–4.

- **Incremento desde:** `f93ea4bb` (reporte final) hasta `5d6515d8`.
- **Contenido:** #525, #526, #527, #528, #530, #511, #517, #520 (+ pruebas al día, #529).

## Para usuarios
- Finanzas › **Facturas** abre la lista nueva; la anterior queda como **Facturas (histórico)**.
- Cada factura nueva lleva un **folio corto** (MN-2026-000123) en la tabla y en el PDF.
- **Exportar** la lista general pide filtrar si son más de 10,000 facturas.
- En modo oscuro el **Estado** de la factura se ve con su color; «Cancelada» en gris.
- Ya no se puede **marcar como pagada** una factura cancelada.
- Quien tenga el permiso de un bloque del **Dashboard** lo ve (antes solo administradores).

## Migraciones
1. `2026_10_16_230000_agrega_folio_corto_a_facturas` — `invoices.folio` (nullable, único),
   tabla `folio_contadores`, columna «Folio» en la tabla. Dry-run con copia de ~100 mil facturas: 1.1 s.

## Configuración (nuevas, con su valor por defecto)
- `BILLING_FOLIO_CORTO` (true) · `billing.whatsapp_documento.documentos` = `['factura']`.
- `config/route_permission.php`: endpoints del Dashboard por permiso.
- **No** copiar `MAIL_INTERCEPTAR` del `.env` de dev a producción.
- Requiere `npm run prod` (dark mode y aviso de exportación) — lo hace el deploy.

## Checklist en PROD
- [ ] `php artisan migrate` aplica `…230000`.
- [ ] Menú Finanzas: «Facturas» abre la lista nueva; «Facturas (histórico)» la anterior.
- [ ] Una factura creada después del despliegue tiene folio `MN-<año>-000001`…
- [ ] Exportar sin filtro en la lista general → aviso para filtrar.
- [ ] Antes de encender la proforma anticipada: fijar `delay_days` de proforma_invoice en **21**.
- [ ] Limpieza de PDFs (cuando se quiera): `php artisan billing:pdfs-limpiar` (prueba) → revisar →
      `--ejecutar`.

## Rollback
Revertir los merges (no destructivo). `BILLING_FOLIO_CORTO=false` deja de asignar folios.
