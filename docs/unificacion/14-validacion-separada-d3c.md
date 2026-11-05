# UNI-T D3c — Prueba punta a punta de la validación separada y comparación con A2

Interruptor: `unificacion.interruptores.validacion_separada` (D3a #1031, D3b #1032). Prueba:
`tests/Feature/Unificacion/ValidacionSeparadaD3cTest.php` (megaisp_test, 3 pruebas, 18 aserciones, todo en transacción).

## Ciclo probado (interruptor ON)
1. Validar una orden en curso → rechazada, sin `validated_at`.
2. Completar → `Done` sin `validated_at`; unidades de la semana sin cambio (0 nuevas).
3. Validar → `validated_at` dentro de la ventana PayWeek de la validación; las unidades suben.
4. Re-validar → rechazada. Devolver: motivo vacío rechazado; con motivo regresa a en curso.
5. Interruptor OFF: `devolver` no habilitado; completar conserva `validated_at` (D3a, `ValidacionSeparadaD3aTest`).

## Efecto por semana frente a A2 (`docs/unificacion/linea-base.md`)
- [código] La unidad se cuenta en la semana PayWeek de `validated_at`, no de la de cierre del técnico:
  una orden completada en una semana y validada en la siguiente suma en la segunda.
- [código] Semanas pasadas: no se reescriben. Filas históricas ya tienen `validated_at`; el interruptor solo
  afecta cierres nuevos. La línea base A2 (136 celdas, 7 con unidades) no cambia por este item.
- [por verificar] Correr `php artisan uni:linea-base --comparar=<json A2>` en DEV tras encender el interruptor
  para confirmar 0 diferencias en semanas ya cerradas; no se ejecutó aquí.

## Criterios de #916
- Completada no suma hasta validarse; al validarse suma en la semana de su validación → prueba punto 2–3.
- Validar una orden en curso se rechaza → punto 1.
- Interruptor apagado = comportamiento actual → punto 5.
- Comparación con A2 → sección anterior (código); la corrida DEV de `--comparar` queda por verificar.
