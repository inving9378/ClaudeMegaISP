# Reporte: registros huérfanos en `client_custom_services` post-import

**Fecha:** 2026-06-06
**Base de datos:** `megaisp_claude`
**Import realizado por:** Smart Import Service (`flushBulkMergeRows`)

---

## Causa raíz

`flushBulkMergeRows()` elimina la columna `id` de cada fila para evitar conflictos de PK al hacer upsert. Esto funciona correctamente tabla por tabla, pero **rompe la integridad referencial entre tablas** cuando los IDs originales del dump son referenciados por una tabla secundaria.

## Mecanismo

1. **`client_bundle_services`** se importa sin `id` → MySQL asigna auto-increment nuevos
   - IDs disponibles: `1, 2, 4, 6, 7, 9, 10, 11, 13, 15...` (saltos porque duplicados existentes se omiten en el upsert)
   - Rango total: `1–3800`, 2,359 registros

2. **`client_custom_services`** se importa después; cada fila trae `client_bundle_service_id` con el ID **original del dump** (ej: `3`, `12`, `22`, `31`, `96`...), no el ID recién asignado.

3. **Resultado:** 137 registros referencian `client_bundle_service_id` que no existe en `client_bundle_services`. 9 más están en `NULL`.

## Cifras

| Métrica | Valor |
|---------|-------|
| `client_custom_services` totales | **485** |
| Con referencia válida | **339** (70%) |
| Con `client_bundle_service_id` inexistente | **137** (28%) |
| Con `client_bundle_service_id` NULL | **9** (2%) |
| `client_bundle_services` totales | **2,359** (IDs 1–3800 con lagunas) |

Los 137 registros huérfanos se crearon todos en el mismo segundo (`2024-06-04 15:43:19–22`), confirmando que fueron insertados durante esta misma importación.

## Datos concretos de los 137

Son registros con datos reales de clientes reales:
- `client_id`, `service_name`, `price`, `start_date`, `pay_period`, `description`, etc.
- **No** son basura — representan servicios contratados
- Solo la relación con el bundle padre (`client_bundle_services`) está rota

IDs de `client_bundle_service_id` referenciados que no existen (sample):
`3, 12, 22, 31, 96, 101, 103, 106, 140, 144, 164, 179, 182, 185, 189, 198, 199, 202, 205, 207, 208...` hasta `1143, 1144`

## Otras tablas verificadas — sin problemas

| Tabla | Registros | Estado |
|-------|-----------|--------|
| `clients` | 5,619 | ✅ |
| `invoices` | 93,124 | ✅ |
| `bundles` | 31 (sin NULLs) | ✅ |
| `client_internet_services` | 5,415 (0 huérfanos) | ✅ |
| `client_bundle_services` | 2,359 (0 huérfanos) | ✅ |
| `permissions` | 636 | ✅ (solo WARNINGs por duplicados) |
| `users` | 4,759 | ✅ |

## Posibles soluciones

1. **Reimportar todo** con un mecanismo que preserve relaciones FK entre tablas (complejo: el upsert por tabla no conoce las relaciones entre tablas)
2. **Reparación post-import**: por cada custom service huérfano, buscar el bundle service correcto por coincidencia de `client_id` + `service_name` + fechas
3. **Aceptar la pérdida**: los 137 son ~0.2% de los registros; borrarlos si no se consumen desde UI
4. **Hacer `client_bundle_service_id` nullable** si algunos servicios pueden vivir sin bundle padre
