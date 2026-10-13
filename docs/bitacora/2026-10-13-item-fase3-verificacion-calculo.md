## 2026-10-13 11:40 — Fase 3/5: verificación de cálculo con datos sintéticos, bug de límite de semana y hueco de UI encontrados y corregidos

### Bug encontrado ANTES de construir datos de prueba — límite de semana mal calculado

Al revisar `medirUnidades()` (la pieza nueva de la Fase 2) para saber dónde ubicar los datos
sintéticos, se confirmó que `$week['period_start']`/`['period_end']` son strings de **fecha
sola** ('2026-10-03'/'2026-10-10', para las columnas `week_start`/`week_end`) — **no** los
instantes precisos del corte de `PayWeek` (`start_instant`/`end_instant`, que corta exacto a las
18:00 del sábado). El código de la Fase 2 usaba `whereBetween('created_at', [period_start,
period_end])`, que MySQL interpreta con el límite superior a medianoche: cualquier OT completada
o pago capturado entre las 00:00 y las 18:00 del sábado de cierre se habría quedado FUERA de la
cuenta sin que nadie lo notara (el síntoma habría sido "le faltó un pago/una OT" sin explicación
visible). **Corregido** a `whereDate()` (día calendario completo), el mismo criterio que ya usa
`CalculateBalanceSellerService` para 'ventas' — consistente entre las tres métricas, y encontrado
antes de construir ningún dato de prueba sobre el límite equivocado (no afectó a los 13
colaboradores reales evaluados en la Fase 2 porque ninguno tenía actividad real en el rango —
pero si la hubiera tenido, el bug habría estado ahí sin mostrar error).

### Hueco de UI encontrado — "Regla de desempeño semanal" solo existía para Vendedor

Al verificar el renderizado real (la parte central de esta fase), se confirmó que la tarjeta
"Regla de desempeño semanal" **nunca se conectó para Técnico ni Mostrador** — solo
`TalentoVentasController::misVentas()` (Vendedor) la incluía en su respuesta; ni
`PortalTecnicoController::miCalidad()` ni `::miProgresoMostrador()` devolvían
`regla_desempeno`, y los templates de esas dos pestañas en `public/talento-portal/app.js` no
tenían el bloque correspondiente. Con las 8 reglas de la Fase 2 ya viviendo en el motor, un
técnico o un mostrador real habrían tenido una evaluación real calculándose cada domingo **sin
ninguna forma de verla** en su propio "Mi progreso". Corregido: se reusa
`DashboardService::reglaDesempeno()` tal cual (ya era agnóstico de rol, solo necesitaba que
alguien lo llamara) + se agrega la misma tarjeta ya usada en Vendedor, con su propio label por
métrica ("Completados" / "Pagos capturados" en vez de "Prospectos/Ventas"). De paso se relabeló
la tarjeta nativa de la ficha admin (`TalentoFichaCalidad.vue`, siempre técnico-only) que
arrastraba el mismo texto genérico de vendedor desde que se construyó (antes de que existiera la
Fase 2, nunca tuvo otro dato que mostrar).

### Verificación con datos sintéticos — los 3 desenlaces, 2 métricas nuevas + la ya existente

7 cuentas de prueba desechables (`prueba_fase3_*`), una por combinación rol×desenlace:

| Cuenta | Rol | Datos sintéticos | Desenlace esperado | Desenlace real |
|---|---|---|---|---|
| tec_penalty | TECNICO_PLANTA | 0 OTs completadas | Penalización pendiente | ✅ Penalización pendiente |
| tec_meets | TECNICO_PLANTA | 40 OTs completadas (piso 36, techo 45) | Cumple cuota | ✅ Cumple la cuota |
| tec_bonus | TECNICO_PLANTA | 50 OTs completadas | Bono | ✅ Bono ganado ($300) |
| mostrador_penalty | Mostrador | 0 pagos capturados | Penalización pendiente | ✅ Penalización pendiente |
| mostrador_meets | Mostrador | 18 pagos (piso 15, techo 25) | Cumple cuota | ✅ Cumple la cuota |
| mostrador_bonus | Mostrador | 28 pagos | Bono | ✅ Bono ganado ($250) |
| vendedor | Vendedor | 0 prospectos/ventas | Penalización pendiente | ✅ Penalización pendiente (ruta ya existente, sin tocar — confirmado que sigue viva) |

Las OTs y pagos sintéticos se insertaron por `DB::table()->insert()` directo (sin pasar por
Eloquent/observers) para no disparar ningún efecto colateral real (ningún ledger de
compensación, ningún abono de saldo a un cliente real) — los pagos de prueba se adjuntaron a un
cliente real existente (id 4305) solo como referencia de visualización, sin tocar su saldo;
confirmado después que sus 52 pagos reales siguen intactos.

Se confirmó el renderizado real con Playwright en **dos superficies distintas**: el Portal de
Colaborador ("Mi progreso" de cada rol) y la ficha admin nativa (`/talento/colaborador/{id}` →
Información, para el caso de bono de técnico) — ambas muestran la tarjeta, el badge de color
correcto por desenlace, y el monto de bono/penalización cuando aplica.

### Limpieza

Las 7 cuentas + sus `talento_colaboradores`/`seller`, las OTs y pagos sintéticos (identificados
por su `receipt` con prefijo único `FASE3-TEST-`), y las evaluaciones generadas por esa corrida
de prueba — todo borrado, confirmado en cero. El cliente real 4305 confirmado sin cambios.

### Estado

Fase 3 cerrada. El cálculo de salario/bono/penalización por rol está verificado end-to-end con
los 3 desenlaces posibles, para las 3 métricas (técnico/mostrador/vendedor), en las dos pantallas
donde el colaborador o un admin pueden verlo. Dos defectos reales encontrados y corregidos en el
camino (límite de semana, hueco de UI en técnico/mostrador) — ninguno existía antes de la Fase 2
(son consecuencia directa de construirla), así que no hay nada "ya roto desde antes" que reportar
aquí. Sigue la Fase 4 (aislamiento de datos por rol) y la Fase 5 (Mostrador "admin-lite").
