# Épica CFDI 4.0 — especificación (FAC-15, item #524)

> **Sin código, sin timbrados, sin contratación, sin CSD.** Documento para que Irving y su contador
> decidan; la implementación sería #117 (adaptador PAC) siguiendo esto. Etiquetas:
> **[verificado]** (fuente consultada, ver §12) · **[propuesta]** · **[decisión]** (Irving/contador) ·
> **[pendiente de verificación]**.

## 1. Lo que ya existe en el sistema (revisado en el código, 16-oct-2026)
- `TimbradoServiceInterface` (`emitirFactura(Payment, ClientFiscalData)`, `cancelarFactura(uuid,
  motivo)`, `isDisponible()`, `nombreProveedor()`) y `NullTimbradoService` (lanza
  `PacNoConfiguradoException`).
- Tabla `client_cfdi_invoices` lista para resultados del PAC: estado, proveedor, uuid, serie/folio,
  RFC emisor/receptor, uso CFDI, régimen, montos, rutas XML/PDF/QR, cadena original, sellos,
  certificados, fecha de timbrado, cancelación, respuesta del PAC, error. **No tiene `invoice_id`.**
- Tabla `client_invoice_cfdi` (CFDI cargados a mano, ligados a `client_invoices`): **0 filas en dev**;
  el Portal Cliente ya los descarga.
- Datos fiscales del cliente en `client_fiscal_data` (RFC, razón social, régimen, C.P., uso CFDI,
  correo fiscal, constancia). Tarjeta «Datos fiscales» en la ficha (#480).
- Documento vigente con rama CFDI preparada e inactiva (`Invoice::documentoVigente()` → `cfdi` solo
  si hay CFDI ligado; Enviar responde 409 «llega con la Fase 4»).

## 2. Proveedor (PAC)
- **[decisión]** Facturama (candidato del prompt) u otro (Finkok, SW Sapien, Formas Digitales,
  ya listados en #117). Criterios: API REST con sandbox, costo por timbre, cancelación por API,
  descarga de XML/acuse, soporte de complemento de pagos y factura global, SLA.
- **[pendiente de verificación]** capacidades y precios vigentes de Facturama (no consultados aquí).
- **[propuesta]** un adaptador por PAC detrás de `TimbradoServiceInterface`; el PAC activo se elige
  por configuración y sus credenciales viven en el Integration Hub (`api_integrations`), nunca en Git.

## 3. CSD y secretos
- **[propuesta]** El CSD (.cer, .key y contraseña) de Meganet se carga **en el PAC** si este lo
  administra (modelo multiemisor); si hay que guardarlo localmente: cifrado en reposo, permisos
  restringidos, fuera de `public/` y de Git, con acceso solo del servicio de timbrado y bitácora.
- **[decisión]** quién custodia el CSD y su contraseña; procedimiento de renovación antes de vencer
  (**[pendiente de verificación]** vigencia del CSD).
- Nada de secretos en bitácoras ni en este documento (convención del repositorio).

## 4. Cuándo se timbra y con qué método
- **[propuesta]** Timbrar **al pagarse** la factura (el mismo evento que hoy produce la «Factura
  sin valor fiscal», FAC-04), solo para clientes con datos fiscales completos que lo pidan
  (`client_fiscal_data.facturacion_fiscal`).
- **[para el contador]** Método de pago: PUE (pago en una exhibición) cuando se timbra ya pagada;
  PPD + complemento de pago (REP) si se decide timbrar antes del pago o con pagos parciales.
  Forma de pago según el método real del pago (catálogo SAT) — mapeo `method_of_payments` → c_FormaPago.
- **[para el contador]** Clave de producto/servicio y unidad para internet y otros servicios; tasa de IVA
  por servicio (hoy el sistema calcula 16 %).

## 5. Datos del receptor
- **[verificado]** obligatorios: RFC, nombre (en MAYÚSCULAS tal como la Constancia), C.P. del domicilio
  fiscal, régimen fiscal y uso del CFDI compatible con el régimen (infografía SAT CFDI 4.0, art. 29-A CFF).
- **[propuesta]** validar antes de timbrar con las mismas reglas del checklist de FAC-02 y bloquear el
  timbrado si falta algo (sin «corregir» datos a mano).

## 6. Factura global (público en general)
- **[verificado]** en operaciones con público en general: RFC `XAXX010101000`, régimen receptor 616 (sin
  obligaciones fiscales) y uso `S01` (guía de llenado del SAT).
- **[decisión]** periodicidad de la global (diaria, semanal, mensual…) y qué pagos entran (los de
  clientes sin datos fiscales o que no pidieron factura).
- **[propuesta]** marcar cada pago incluido para no timbrarlo dos veces (individual y global).

## 7. Cancelaciones, relaciones y notas de crédito
- **[pendiente de verificación]** motivos de cancelación vigentes: 01 (con relación), 02 (sin relación),
  03 (no se realizó la operación), 04 (operación nominativa en una global); plazos y aceptación del
  receptor.
- **[propuesta]** la cancelación de una factura en MegaISP no cancela el CFDI: se solicita al PAC con
  motivo explícito, se guarda el acuse y el estado (`cancelacion_solicitada` → `cancelada`).
- **[para el contador]** sustitución (relación 04), notas de crédito (CFDI de egreso, relación 01) para
  descuentos/bonificaciones y su efecto en saldos.

## 8. Convivencia con CONTPAQi y fuente de verdad
- **[decisión]** durante la transición, ¿quién timbra qué? **[propuesta]** regla por cliente: un
  cliente se timbra en MegaISP **o** en CONTPAQi, nunca en ambos; marca en `client_fiscal_data`.
- **[propuesta]** prevención de doble timbrado: antes de timbrar, buscar CFDI vigente ligado al pago o
  factura (`client_cfdi_invoices` y `client_invoice_cfdi`); importar los XML de CONTPAQi al flujo de
  carga manual que ya existe para que el sistema los vea.
- Fuente de verdad del estado fiscal: el SAT/PAC; MegaISP guarda copia y estado.

## 9. Fallos, reintentos y conciliación
- **[propuesta]** llave de idempotencia por pago (o factura) enviada al PAC; ante timeout, **consultar
  primero** si ya se timbró antes de reintentar (nunca reintento ciego: doble timbre).
- Estados: `pendiente` → `timbrado` | `error` (con respuesta del PAC); reintentos con espera creciente y
  tope; alerta a Facturación al tope.
- Conciliación diaria: CFDI emitidos según el PAC vs. registros locales; diferencias a una bandeja.
- Evidencia: XML/PDF del CFDI en el disco privado de facturación (como FAC-05).

## 10. Cambios de datos previstos (para cuando se implemente)
- `client_cfdi_invoices.invoice_id` (y/o `payment_id`, ya existe) para ligarlo con `invoices`; que
  `Invoice::hasCfdi()` use ese vínculo (hoy mira `client_invoice_cfdi` vía `client_invoice_id`).
- Clave de producto/unidad por plan o servicio.

## 11. Fases propuestas
1. Elegir PAC y cargar CSD en sandbox (Irving). 2. Adaptador + timbrado de una factura de prueba en
sandbox. 3. Cancelación y consulta. 4. Complemento de pago/PUE-PPD según el contador. 5. Global.
6. Piloto en producción con pocos clientes (como FAC-08). 7. Envío «Tu CFDI {periodo} — Meganet» (la
rama ya está prevista en el documento vigente).

## 11b. Preguntas exactas para el contador (borrador, item #1062)
1. ¿Se timbra al pagarse (PUE) o al emitir la prefactura (PPD + complemento de pago)? ¿Qué pasa con
   pagos parciales y abonos?
2. Clave de producto/servicio y unidad SAT para internet residencial, instalación, equipos y cargos
   por reconexión.
3. Mapeo de nuestros métodos de pago (efectivo, transferencia, OXXO, tarjeta, pago a técnico) a
   c_FormaPago.
4. Factura global: periodicidad y qué pagos incluye; ¿se puede timbrar individual un pago ya incluido?
5. Cancelación: motivo por escenario y manejo de notas de crédito/bonificaciones.
6. Cargos del periodo de suspensión (#534): ¿se timbran? Regla 10 del sistema: no se timbra un
   periodo no pagado. ¿Es correcta?
7. Autorrenovación y facturas emitidas sin pago (hasta 3): tratamiento fiscal de lo facturado y no
   cobrado (incobrables) y su cancelación.
8. Clientes de CONTPAQi vs. MegaISP: fecha de corte y cómo evitar el doble timbrado.

## 12. Fuentes
- SAT — infografía «Factura electrónica CFDI 4.0: como cliente, ¿qué datos necesito proporcionar?»:
  https://www.sat.gob.mx/minisitio/Factura/documentos/Infografia_FacturaElectronica_Datos_Receptor.pdf
- SAT — Anexo 20 y guías de llenado (público en general 616/S01):
  http://omawww.sat.gob.mx/tramitesyservicios/Paginas/anexo_20.htm
- Relación: #117 (implementación del PAC), #483 (Fase 4 de la Prefactura).
