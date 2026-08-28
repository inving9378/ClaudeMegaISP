# Item #66 — Flotas Fase 7 "IA agrupada" (OCR, predicción, asistente conversacional, análisis comparativo)

## Qué pedía el item

> "Capa de IA que se construye DESPUES de Fases 1-6 estables y con minimo 6 meses de datos
> reales. Agrupa: (1) OCR documentos al subir PDF, (2) predicción proximos servicios por
> historial+km, (3) asistente conversacional para queries naturales sobre la flota, (4) analisis
> comparativo de gastos. Stack: Claude API para OCR y chat, logica estadistica para prediccion.
> Tiempo: 3-4 semanas."

El item ya había sido triado dos veces y escalado a Irving (des-trabe Opus, 2026-08-26) por venir
vacío y mezclar 3-4 decisiones de producto distintas. El brief recomendó **Opción A**: partir en
items independientes y **priorizar solo uno para arrancar** (el más mecánico, OCR), dejando
predicción/asistente en backlog hasta tener proveedor, presupuesto y caso de uso definidos.

## Qué se encontró al investigar (antes de picar código)

1. **El subsistema OCR YA está resuelto**, y no por este item: `FleetDocumentOcrService`
   (`app/Modules/Addons/Flotas/Services/Ocr/`), migraciones
   `2026_08_08_160000_create_fleet_document_ocr_runs_table.php` +
   `2026_08_08_160100_add_ocr_columns_to_fleet_documents.php`, modelo `FleetDocumentOcrRun`. Lee
   el documento vía el módulo IA compartido (`IAAdaptadorFactory` — regla "SERVICIOS COMPARTIDOS
   ÚNICOS" de CLAUDE.md) y prellena la pestaña Documentos con confirmación humana. Cerrado por
   item #580/#224, detalle en `docs/flotas-ocr-item-224-verificacion.md`. Exactamente la pieza
   que el brief des-trabe recomendaba priorizar — ya está hecha, de forma independiente.

2. **La precondición que el propio item exige para las otras 3 piezas NO se cumple hoy:**
   "DESPUES de Fases 1-6 estables y con minimo 6 meses de datos reales". Revisando
   `CLAUDE.md` § MÓDULO FLOTAS, solo existen documentadas **Fases 1 (Vehículos/Mantenimientos/
   Documentos/Proveedores), 2 (Tracking GPS) y 3 (Geocercas)** — no hay Fase 4, 5 ni 6. Además
   este ambiente es DEV: no hay 6 meses de uso real de producción acumulando `fuel_log`,
   `maintenances` ni historial de km con el que entrenar/alimentar una predicción o un análisis
   comparativo con datos reales.
   - Predicción de próximos servicios (historial+km): sin datos suficientes, cualquier
     implementación hoy sería sobre datos sintéticos/seed — no cumple el propósito del item.
   - Asistente conversacional: requiere decisión de producto (para quién, qué motor, costo por
     query) que el brief des-trabe ya identificó como pendiente de Irving.
   - Análisis comparativo de gastos: ya existe una versión simple no-IA en `FleetDashboard.vue`
     ("gastos del año por categoría"); una versión con IA requeriría decidir alcance primero.

## Resolución

Siguiendo la Opción A ya recomendada (des-trabe Opus, aprobada por Irving al reaprobar el item el
2026-08-28), se descompone en 3 sub-items de backlog — uno por subsistema restante — cada uno con
su precondición explícita y las decisiones de producto que le faltan, para que no se ejecuten
hasta que el brief de Fases 4-6 + datos reales exista:

- **#686** — Flotas: Predicción de próximos servicios (historial + km)
- **#687** — Flotas: Asistente conversacional sobre la flota
- **#688** — Flotas: Análisis comparativo de gastos

El item #66 se cierra como paraguas resuelto (mismo patrón que #75/#123/#639): no requería código
nuevo hoy porque (a) su pieza ejecutable ya existía (OCR, #580/#224) y (b) el resto está bloqueado
por una precondición temporal/de negocio explícita en el propio item, no por ambigüedad de
implementación — no hay nada que decidir por criterio propio que la construya antes de tiempo.

## Sin cambio de código funcional

Este cierre no modifica ningún archivo de `app/`, `resources/` ni `routes/` — solo documenta la
verificación y deja registrados los 3 sub-items en la Hoja de Ruta.
