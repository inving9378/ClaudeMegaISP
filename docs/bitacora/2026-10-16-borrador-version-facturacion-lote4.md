# Borrador de versión — Facturación, lote 4 (SIN número oficial, sin publicar)

> Borrador para Irving. No hay tag, push ni release. Se suma a los lotes 1–3.

- **Incremento desde:** `61c6c55b` (cierre del lote 3) hasta `9ac0e024`.
- **Contenido:** FAC-10 (#518), FAC-11 (#519), FAC-13 (#521) con desarrollo; FAC-12 (#520), FAC-14
  (#523) y FAC-15 (#524) como documentos.

## Para usuarios
- **Portal Cliente › Facturas:** nueva sección «Prefacturas y facturas en PDF» con el mismo documento
  que ve el personal (Proforma / Factura s/v fiscal), un solo botón por factura.
- **Dashboard:** bloque «Facturación (mes en curso)» con 7 indicadores (para quien ve Finanzas).
- CLABE personal en la Prefactura: lista pero **apagada**.

## Commits
| Item | Commits | Merge |
|---|---|---|
| FAC-10 #518 | `cf5a8c72` `d99947fd` + bitácora | `0e21e569` |
| FAC-11 #519 | `0d49871b` `8300ec26` | `3b66004c` |
| FAC-12 #520 | `ed0c027e` (documento) | `5be4e768` |
| FAC-13 #521 | `90013ea6` `0f49a7aa` | `602f5223` |
| FAC-14 #523 / FAC-15 #524 | `3676458a` `3bd14228` (documentos) | `9ac0e024` |

## Migraciones
Ninguna en este lote.

## Configuración
- `billing.clabe_virtual_proforma` ← `BILLING_CLABE_VIRTUAL_PROFORMA` (default **false**).
- Ruta nueva en `config/route_permission.php`: `/get-stats-facturacion-card-in-dashboard-c` bajo
  `dashboard_view_block_finance`.
- Requiere **`npm run prod`** (cambió `Dashboard.vue`) — lo hace el deploy (`npm_build`).

## Validación hecha (dev)
- FAC-10: controlador real con guard `cliente` (propia 200, ajena/inexistente 404); vista con datos
  sintéticos, contraste ≥4.5 en claro/oscuro.
- FAC-11: cálculo en dev y tarjeta en el navegador (claro/oscuro).
- FAC-13: unitarias + dev en transacción revertida (apagado, encendido, CLABE inválida, inactiva).
- ⚠️ Pruebas con BD pendientes mientras `megaisp_test` siga a medio migrar (#529).

## Checklist en PROD
- [ ] Portal: `/portal/facturas` muestra la sección nueva; el PDF abre solo para facturas propias.
- [ ] Dashboard (como admin): tarjeta «Facturación (mes en curso)».
- [ ] `grep BILLING_CLABE_VIRTUAL_PROFORMA .env` → nada (apagado).
- [ ] Verificar las CLABE de prod con `App\Support\Clabe::valida` antes de encender (la de dev no pasa).

## Rollback
Revertir los merges (no destructivo; sin migraciones).

## Decisiones de Irving
#520 folio amigable · #522 activar CLABE virtual · #523 revisión legal · #524 CFDI 4.0 · #525
menú de Facturas (y si el portal debe listar `invoices`) · #530 permisos de las estadísticas del
Dashboard.
