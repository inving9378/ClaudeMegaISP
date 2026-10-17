# Indicadores de facturación (Dashboard › Facturación)

> FAC-11 (item #519). Bloque «Facturación (mes en curso)» del Dashboard, junto a «Finanzas». Lo ve
> quien tiene el permiso de lectura existente **`dashboard_view_block_finance`**. Solo agregados
> (sin datos de clientes), solo lectura, recalculado cada 10 minutos. Código:
> `App\Services\Finance\Billing\IndicadoresFacturacionService`.

**Periodo:** del día 1 del mes en curso (00:00) hasta el final de hoy, en hora de México.

| Indicador | Fórmula | Denominador | Fuente | Notas |
|---|---|---|---|---|
| Proformas generadas | conteo | — | `invoices` tipo `proforma`, `created_at` en el periodo | |
| % proformas entregadas | — | — | — | **Sin datos:** no existe acuse de entrega del destinatario. |
| % proformas aceptadas por el servidor de correo | proformas del periodo con evidencia `aceptado` ÷ proformas del periodo | proformas generadas en el periodo | `billing_documento_evidencias` (canal correo, documento prefactura, sin interceptadas) | Aceptado = el servidor de correo lo recibió; **no** es entrega. «Sin datos» si no hay evidencias en el periodo (antes de desplegar FAC-05). |
| Días promedio proforma → pago | promedio de `payment_date − created_at` (días) | proformas pagadas con `payment_date` en el periodo | `invoices` | Excluye las retroactivas (`payment_method = retroactive`) y los pagos anteriores a la proforma (diferencia negativa). Muestra cuántas entraron. |
| % pagos identificados por referencia MEG (WhatsApp) | pagos del periodo aplicados por una identificación MEG ÷ pagos de clientes del periodo | pagos de clientes (`payments`, ambos tipos de Client) con `created_at` en el periodo | `whatsapp_identification_sessions` (`method = meg`, no simulación, `applied_payment_id`) | **Parcial:** solo la conciliación por WhatsApp registra con qué se identificó el pago; SPEI y mostrador no. |
| Pagadas sin CFDI de clientes con datos fiscales | conteo y monto | — | `invoices` pagadas con `payment_date` en el periodo, cliente con RFC en `client_fiscal_data`, sin CFDI vigente ligado | Hoy ninguna factura de `invoices` tiene CFDI ligado (Fase 4); el conteo muestra cuántas lo pedirían. |
| Errores de envío | notificaciones con error + evidencias fallidas | — | `billing_notifications` (`status = error`, `updated_at` en el periodo) y `billing_documento_evidencias` (`fallido`) | Los errores al **generar** un PDF solo quedan en el log de Laravel (sin dato estructurado). |

**Muestras de prueba:** las evidencias marcadas `interceptado` (correos de prueba de dev) no cuentan.

**Rendimiento:** `invoices` no tiene índices por fecha/estado; con ~100 mil filas el cálculo tarda
~0.5 s sin caché. Agregar índices sería un cambio aparte (impacto a medir en prod).
