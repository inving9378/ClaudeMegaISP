## 2026-10-16 21:40 — Decisiones sí/no de Irving y su ejecución

Irving respondió 12 preguntas de sí o no sobre lo pendiente: **11 sí** y **no publicar** la versión
todavía. Lo ejecutado (dev; nada en producción):

| Item | Decisión | Qué se hizo | Merge |
|---|---|---|---|
| #529 | Sí, reconstruir megaisp_test | `migrate:fresh` falla desde cero en 2 migraciones fuera de orden (registrado **#531**); se destrabó corriendo primero las que crean lo que faltaba, luego el resto y `db:seed` (611 tablas). Corrieron las 2 pruebas pendientes. | `b944f150` |
| #502 | Sí, interceptar correo en dev | 2 líneas en el `.env` de dev (`MAIL_INTERCEPTAR`, destino = correo de Irving), permisos 640 intactos, `config:clear` + `queue:restart` (workers reiniciados). | — (configuración) |
| #527 | Sí | «Marcar como pagada» solo en facturas por cobrar; 422 en canceladas. | `fbc81008` |
| #525 | Sí | «Facturas» → lista nueva; anterior → «Facturas (histórico)». La nueva ya estaba como «Facturas Proforma» (hallazgo corregido). | `b944f150` |
| #526 | Sí | Exportar pide filtrar si son > 10,000 facturas (422 + aviso). | `b944f150` |
| #528 | Sí | «Estado» con color en oscuro; Enviada/Cancelada con estilo; cancelada no clicable para pagar. | `f8f6d0b9` |
| #530 | Sí | Endpoints del Dashboard mapeados a su permiso (middleware real: 200/403). | `382d64a7` |
| #513 | Sí, N = 21 | `delay_days` = 21 en dev (antes 1); interruptor apagado. | — (dato) |
| #520 | Sí | Folio `MN-AAAA-NNNNNN` para facturas nuevas: PDF, archivo, columna «Folio». Migración `…230000` aplicada en dev. | `5d6515d8` |
| #511 | Sí | `billing:pdfs-limpiar` (prueba por defecto) + clasificador compartido con el inventario. | `a649fc95` |
| #517 | Sí, solo pagada | WhatsApp: `documentos = ['factura']`. | `e79910c9` |

**Pruebas:** 117 pruebas propias (con BD en megaisp_test reconstruida + unitarias) OK. Al
reconstruirse, las pruebas de envío de FAC-04/05 fallaban porque FAC-08 deja la etapa cerrada: se
actualizaron y hay una prueba nueva de «etapa cerrada = nada sale».

**⚠️ Incidente (registrado en #531):** el seed de megaisp_test deja la cuenta SMTP real del sistema
asignada a los correos del Hub; en dos corridas, antes de corregir las pruebas, salieron 2 correos de
prueba por el SMTP real hacia direcciones **ficticias `@example.test`** (dominio reservado, no
entregable) con contenido sintético. Corregido en las pruebas (commit `028fa57e`: quitan las
asignaciones dentro de la transacción y verifican que no quede ninguna).

**Hallazgos nuevos:** #531 (migraciones fuera de orden + SMTP real en el seed de pruebas).

### Validación en navegador (después del build del principal)
- Menú Finanzas: «Facturas → /finanzas/invoices» y «Facturas (histórico) → /finanzas/facturas».
- Tabla: columna «Folio» al inicio (vacía en las facturas existentes: sin backfill, por decisión).
- Modo oscuro, «Estado»: Emitida 7.53:1, Pagada 9.01:1 (antes texto blanco con línea). Pagada no clicable.
- Exportar sin filtro: «No se exportó — Son 98,246 facturas. Filtra la lista…».
- Capturas: `/reportes/facturacion/r2_facturas_light.png` y `r2_facturas_dark.png`.
- Hallazgo: en modo **claro** el «Pagada» preexistente tiene 2.06:1 y «Emitida» 4.47:1 → **#532**.
- Usuario temporal 5032 desactivado otra vez y sin rol; su sesión borrada.
