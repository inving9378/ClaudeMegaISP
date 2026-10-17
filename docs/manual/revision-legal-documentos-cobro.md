# Revisión legal de los documentos de cobro — lista para el abogado (FAC-14, item #523)

> Preparada para Irving el 16-oct-2026. **No es una opinión legal ni declara nada válido o
> inválido.** Separa: lo que el sistema hace (hechos), las preguntas para el abogado y las decisiones
> que quedan en Irving. Las normas citadas se marcan **[verificada]** (con fuente consultada ese día)
> o **[pendiente de verificación]**.

## 1. Hechos: qué documentos y canales existen

| Documento | Cuándo | Título y leyendas | Contenido relevante |
|---|---|---|---|
| Prefactura (PDF) | factura sin pagar | «PREFACTURA», «Sin valor fiscal», chip «PENDIENTE DE PAGO» | nombre y domicilio del cliente, conceptos, subtotal, **IVA 16 % desglosado**, total con letra, fecha límite, banco/CLABE de la empresa, referencia MEG, datos fiscales si los dio |
| Factura sin valor fiscal (PDF) | factura pagada | «FACTURA», «Sin valor fiscal», chip «PAGADA» | lo mismo + fecha de pago; **no es CFDI** |
| Recibo y ticket (PDF) | al registrar un pago | «RECIBO DE PAGO», «SIN VALOR FISCAL» | datos del pago y del cliente |
| Correo | «Enviar» (manual) y envío automático (pausado) | «Tu prefactura {periodo} — Meganet», «Tu factura {periodo} (pagada) — Meganet» | PDF adjunto |
| WhatsApp (preparado, apagado) | FAC-09 | plantillas de utilidad propuestas | PDF adjunto por Meta |
| Pie del PDF | siempre | «Documento informativo sin valor fiscal» + aviso de privacidad (si se configura la URL, FAC-03) | |

Datos personales tratados en estos flujos: nombre, domicilio, correo, teléfono, RFC y régimen fiscal
(tarjeta Datos fiscales), Constancia de Situación Fiscal (si se pide, FAC-02), historial de pagos.
Se guarda copia exacta de cada PDF enviado (FAC-05) y el envío por WhatsApp pasaría por Meta.

## 2. Preguntas para el abogado

**A. Uso de «Factura» sin CFDI**
1. ¿Es admisible titular «FACTURA» (con «Sin valor fiscal» debajo) un documento que no es CFDI, o
   conviene otro nombre (comprobante de pago, estado de cuenta, recibo)? *Irving eligió «FACTURA»
   (#505); se pide validación.*
2. ¿Hay riesgo frente al SAT por emitir documentos llamados «factura» sin timbrar? Referencia: CFF
   art. 29 (obligación de expedir CFDI) **[pendiente de verificación del texto vigente]**.

**B. Consumidor y telecomunicaciones**
3. ¿Qué información mínima debe traer el estado de cuenta/recibo de un servicio de internet fijo?
   NOM-184-SCFI-2018 (servicios de telecomunicaciones por contrato de adhesión) — **[verificada]**
   vigente: DOF 08-03-2019, en vigor 04-09-2019; **contenido específico de estados de cuenta
   [pendiente de verificación]**.
4. Derecho del usuario a recibir gratis su factura/estado de cuenta **desglosado** por medios físicos
   o electrónicos (Carta de derechos mínimos de los usuarios de telecomunicaciones, PROFECO/IFT)
   **[verificada]** en el texto publicado por el IFT. ¿Sigue vigente tras la nueva Ley en Materia de
   Telecomunicaciones y Radiodifusión? (LMTR **[verificada]**: DOF 16-07-2025; abroga la LFTR de 2014;
   crea la Comisión Reguladora de Telecomunicaciones que sustituye al IFT) — **vigencia de la Carta
   [pendiente de verificación]**.
5. ¿Los avisos de «fecha límite» y los recordatorios que mencionan suspensión cumplen los plazos y la
   forma de aviso previos a suspender el servicio? **[pendiente de verificación]**.
6. ¿Basta el envío por correo/WhatsApp como entrega del estado de cuenta, o se requiere
   consentimiento expreso del cliente para medios electrónicos? ¿Cómo debe ofrecerse el formato físico?

**C. Datos personales**
7. Ley aplicable: nueva LFPDPPP **[verificada]**: DOF 20-03-2025, en vigor 21-03-2025, abroga la de
   2010; las funciones del INAI pasan a la Secretaría Anticorrupción y Buen Gobierno. Reglamento nuevo
   **[pendiente de verificación]**.
8. ¿El aviso de privacidad vigente cubre: envío de documentos por correo y WhatsApp (Meta como
   encargado o transferencia), la Constancia de Situación Fiscal, la copia de cada PDF enviado y la
   evidencia de envío?
9. ¿Qué plazo de conservación aplica a estos documentos y evidencias, y cuándo deben suprimirse?
   (decisión de retención en FAC-05b) — plazos de CFF art. 30 **[pendiente de verificación]**.
10. ¿Es correcto mostrar en el PDF el domicilio completo y el RFC del cliente si el documento puede
    reenviarse a terceros?

**D. Montos**
11. ¿Es correcto desglosar «IVA trasladado 16 %» en un documento sin valor fiscal?
12. Pagos parciales: el documento muestra «Abonado $X de $Y». ¿Algún requisito para documentar abonos?

## 3. Observaciones (para dar contexto al abogado)
- Todos los PDF dicen «sin valor fiscal»; la factura fiscal se pide a facturacion@meganett.com.mx.
- El envío automático está **pausado** y su reactivación será por etapas (FAC-08).
- La CFDI 4.0 propia (FAC-15) está en especificación: hasta entonces los CFDI se hacen fuera del sistema.

## 4. Decisiones que quedan en Irving
- Contratar la revisión y con quién; prioridad sugerida: A1–A2, C7–C9, B4–B6.
- Según la respuesta: nombre del documento pagado, texto del aviso de privacidad y de los correos,
  plazos de retención.

## 5. Fuentes consultadas (16-oct-2026)
- LFPDPPP 2025 — Cámara de Diputados, texto vigente: https://www.diputados.gob.mx/LeyesBiblio/pdf/LFPDPPP.pdf ;
  análisis EY: https://www.ey.com/es_mx/technical/tax/boletines-fiscales/nueva-ley-federal-proteccion-datos-personal-posesion-particulares
- LMTR 2025 — Cámara de Diputados: https://www.diputados.gob.mx/LeyesBiblio/pdf/LMTR.pdf ;
  nota Pérez-Llorca: https://www.perezllorca.com/es-mx/actualidad/nota-juridica/reconfiguracion-del-sector-de-telecomunicaciones-disolucion-del-ift-y-puntos-clave-de-la-nueva-ley-federal-en-materia-de-telecomunicaciones-y-radiodifusion/
- NOM-184-SCFI-2018 — Secretaría de Economía: https://platiica.economia.gob.mx/normalizacion/wp-content/uploads/sites/2/PDF_Normas_Publicas/NOM-184-SCFI-2018.pdf
- Carta de derechos mínimos (IFT): https://www.ift.org.mx/sites/default/files/carta_de_derechos_seri.pdf
