# Folio amigable para documentos de facturación — opciones (FAC-12, item #520)

> Documento de decisión para Irving. **Sin implementación.** El número interno se conserva.

## Situación actual (dev, 16-oct-2026)
- `invoices.number`: `PROF-AAAAMM-` + 12 dígitos pseudoaleatorios (pid + microtime + random), con
  índice **único**; p. ej. `PROF-202610-482290887229`. Difícil de dictar o buscar de memoria.
- `client_invoices.number` (legacy): consecutivo de 13 dígitos (`2610000163678`).
- Referencia de pago **MEG-{id8}-{mod97}** por cliente: es la llave de conciliación (no se toca).
- Volumen: 83,120 `invoices` en 2025 y 18,399 en lo que va de 2026 (~2,400 al mes).
- El PDF muestra el número interno como «Folio».

## Requisitos a cubrir
Unicidad · concurrencia (dos proformas creadas en el mismo segundo) · reinicio por año · búsqueda
desde las pantallas · conciliación (no reemplaza a MEG) · **permanencia**: la misma factura conserva
su folio al pasar de Prefactura a Factura pagada · no confundirse con el **folio fiscal** del CFDI
(serie/folio del PAC + UUID).

## Opciones

### 1. Folio propio consecutivo por año (recomendada)
`MN-2026-001234`: columna nueva `invoices.folio` (única), asignada al crear la proforma con un
contador por año en una tabla `folio_contadores` (`anio`, `ultimo`) bloqueada con `SELECT … FOR
UPDATE` dentro de la misma transacción.
- ✅ Corto, ordenado y fácil de dictar; reinicia cada año; único por índice.
- ✅ Prefijo neutro («MN» = Meganet) que **no cambia** al pagarse (Prefactura y Factura comparten
  folio) y no parece serie fiscal.
- ⚠️ Necesita migración (columna + tabla) y backfill de las existentes (decidir: solo nuevas, o
  numerar las de 2026 por fecha de creación).
- ⚠️ El contador serializa las altas (irrelevante a ~2,400/mes).

### 2. Derivado del id
`MN-2026-` + id con ceros (`MN-2026-0100982`), sin contador.
- ✅ Sin tabla ni bloqueos; único porque el id lo es; sin backfill (se calcula al vuelo).
- ❌ No reinicia por año ni es consecutivo dentro del año (el id es global); números largos.

### 3. Mostrar una parte del número actual
Los últimos 6 dígitos de `PROF-…`.
- ✅ Sin cambios de datos.
- ❌ **No garantiza unicidad** (colisiones posibles); descartada.

## Recomendación
**Opción 1** con prefijo `MN` y backfill solo de 2026, después de la versión que lleve FAC-04/05. El
folio se mostraría en el PDF, la tabla de facturas y la búsqueda; la referencia **MEG** sigue siendo
la de pago y conciliación; el CFDI (Fase 4) llevará su propio folio fiscal y UUID.

## Decisiones de Irving
1. ¿Se adopta un folio amigable? ¿Opción 1, 2 o ninguna?
2. Prefijo (`MN`, `MEG`, `PF`…) — evitar letras que parezcan serie fiscal.
3. Backfill: solo nuevas, 2026 completo o todo el histórico.
4. ¿Se imprime en lugar del número interno o junto a él?
