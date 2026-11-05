# Reglas de facturación pendientes de validar — borrador (#534, item #1062)

> Solo documento. Nada de esto se enciende hasta que Irving lo valide con su contador y abogado.
> Interruptores hoy **apagados**: `BILLING_PROFORMA_ANTICIPADA`, `BILLING_ESTADO_CUENTA_MENSUAL`,
> `BILLING_CAPA_CONTRATO`.

## 1. Cargos durante la suspensión (cliente Bloqueado)
- Hecho: el cliente Bloqueado recibe un estado de cuenta mensual (no prefactura); no se le genera cargo
  nuevo por la regla FR-06.
- Preguntas: abogado → pregunta 13 de `revision-legal-documentos-cobro.md`; contador → pregunta 6 de
  `cfdi-4-especificacion.md` §11b.
- Recomendación: mantener apagado hasta tener ambas respuestas.

## 2. Autorrenovación y contrato cumplido
- Hecho: vigente se factura aunque no pague; cumplido sin autorrenovación se factura hasta «Facturas
  máximas sin pago» (3) y luego pasa a Inactivo; con autorrenovación inicia otro plazo igual.
- Preguntas: abogado 14–15; contador 7.
- Recomendación: no encender `BILLING_CAPA_CONTRATO` antes de la revisión legal (#523).

## 3. Inactivos y cancelación masiva
- Hecho: `facturacion:inactivos-a-cancelado` (dry-run por defecto) pasaría 2,149 inactivos a Cancelado
  (simulación del 17-oct sobre copia de prod).
- Preguntas: abogado 16–17.
- Recomendación: no aplicar hasta resolver la retención de datos (pregunta 9).

## 4. Decisiones que quedan en Irving
Contratar al contador y al abogado, y fijar el orden: primero 13–15 y contador 1–3, después el resto.
