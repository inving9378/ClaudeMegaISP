# UNI-T A2 — Línea base de cálculos (DEV)

Comando: `php artisan uni:linea-base [--semanas=8] [--salida=archivo.json] [--comparar=archivo.json]`.
**Solo lectura**: no usa `simular()`/`calculate()` ni transacciones; cuenta las sentencias de escritura
con `DB::listen` y las reporta (debe ser 0). El detalle (por colaborador/vendedor, con ids y montos)
se guarda únicamente en `storage/app/uni/linea-base/` (fuera de git). Este resumen es agregado.

## Qué captura el JSON
- Por colaborador activo y por cada una de las últimas 8 semanas PayWeek: desglose del pago
  (`LiquidationService::breakdown` → `countBillableUnits`, el mismo conteo que la liquidación) y lo que
  muestra el tablero (`DashboardService::tecnicoPreview`).
- Escalafón vigente (`CompositeScoreService::scoreAll`; usa "hoy", no semanas PayWeek).
- Estado de cuenta de cada vendedor por semana (`CalculateBalanceSellerService::getSalary`).
- Comisiones de embajador agrupadas por beneficiario y estado.
- Conteos de tareas por estado, por fecha (60 días) y de órdenes de Talento.
- Permisos efectivos resumidos: usuarios y permisos por rol.
- Casos representativos (ids, solo en el JSON): con unidades, sin unidades, mayor diferencia pago↔tablero.

## Resultado agregado [DEV, 2026-10-26]
- 17 colaboradores activos × 8 semanas = 136 celdas; 7 con unidades > 0.
- Pago/desglose vs tablero: 0 diferencias en estas 8 semanas. [código] Diferencia latente ya existente:
  `tecnicoPreview` suma solo OTs viejas + proyecto externo y **no** las órdenes de Talento en `tasks`
  que sí cuenta el pago; hoy no se manifiesta porque no hay órdenes de `tasks` validadas dentro de
  esas ventanas (5 órdenes de Talento en `tasks`: 4 Done, 1 ToDo).
- [código] El escalafón calcula con sus propias ventanas (`now()-N semanas`, no PayWeek) y solo OTs
  viejas: otra fuente paralela de unidades.
- Vendedores: 32 con usuario × 8 semanas = 256 estados de cuenta, 0 errores.
- Embajadores: 19 grupos (beneficiario, estado) de comisiones.
- Tareas por estado: Archivado 665, Done 851, InProgress 24, ToDo 126.
- Permisos: 11 roles resumidos.

## Reproducibilidad
Dos corridas seguidas en DEV: `--comparar` reporta "Sin diferencias" y 0 escrituras SQL.
Prueba: `tests/Feature/Unificacion/UniLineaBaseTest.php` (megaisp_test). [por verificar] No pudo correrse
en el worktree del circuito: `migrate:fresh` exige `database/sql/sql_test_unit.sql`, ausente (gitignored).
