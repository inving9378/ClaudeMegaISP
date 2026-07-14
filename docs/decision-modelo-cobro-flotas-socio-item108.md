# Decisión — Modelo de cobro Flotas con socio (item #108)

Fecha de decisión: 2026-07-14 (Irving, vía formulario multi-pregunta de la Torre).
Item origen: roadmap #108 ("[BLOCKED-NEGOCIO] Definir modelo de cobro Flotas con socio").
Item dependiente (bloqueado hasta ahora): roadmap #99 (gancho `markBilled() → InvoiceService`,
Fase 6.2).

## Respuestas de Irving (los 4 ejes mínimos del brief)

1. **Unidad de cobro / modelo:** **Híbrido** — fee fijo mínimo mensual + variable por
   consumo/unidades sobre un umbral.
2. **Quién factura al cliente final:** **El socio** factura al cliente final y paga una
   contraprestación a Meganet (Meganet NO factura directo al cliente por este concepto).
3. **Cartera vencida / riesgo de impago:** **El socio asume el riesgo** — Meganet solo cobra
   su contraprestación sobre lo efectivamente cobrado real (no hay exposición de Meganet a
   cartera vencida de Flotas).
4. **Nivel mínimo viable para Fase 6.2 (AHORA):** **Manual** — registrar los cobros de Flotas
   etiquetados en el sistema y hacer la liquidación al socio en Excel/por fuera del sistema.
   Explícitamente **NO** se construye todavía el flujo automático (ni la tabla de contratos con
   cálculo automático, ni el módulo completo de partners).

## Qué implica esto para #99 (`markBilled() → InvoiceService`)

El diseño original de #99 (item polimórfico en `invoice_items`, dentro de la factura ISP del
cliente) **ya no aplica tal cual**: dado que es el socio quien factura al cliente final (eje 2)
y Meganet no asume cartera vencida (eje 3), el cargo de Flotas **no debe viajar como
`invoice_item` de la factura del cliente ISP** — sería facturar por partida doble (el socio ya
factura al cliente por separado).

Lo que sí hace falta, y es explícitamente lo que Irving aprobó para arrancar (eje 4): un
**registro interno de cobros de Flotas etiquetado** (qué vehículo/plan generó cuánto, cuándo)
que sirva de insumo para que Irving liquide manualmente al socio fuera del sistema — sin tocar
`invoice_items` ni el flujo de facturación al cliente.

**#99 debe re-alcanzarse** (no ejecutarse con su prompt original) antes de tomarse: el prompt
técnico correcto ya no es "gancho `markBilled()` → `invoice_items` polimórfico", sino algo del
tipo "registro/tag de cobro de Flotas visible para exportar y liquidar manualmente al socio".
Cualquier ejecutor que tome #99 debe leer primero este documento.

## Estado

Item #108 se cierra `completado` — la decisión de negocio ya está tomada y documentada aquí.
El trabajo técnico derivado (re-alcance de #99) es un item aparte y no se ejecuta desde #108.
