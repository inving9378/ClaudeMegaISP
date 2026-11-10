# UNI-T K1 · Decisiones económicas del proyecto — alternativas y ejemplos

Item #904 (paraguas #862). Insumo para la decisión de Irving en K2 (#905), que a su vez
bloquea a D8b (#906), I5 (#907), L3 y M2.
Etiquetas: [código] leído en el código · [DEV] observado en la BD de DEV (no producción) · [por verificar].
Solo conteos, nombres técnicos y ejemplos **sintéticos** (cifras redondas, sin personas).
Este documento **no cambia ninguna regla económica**: propone opciones y recomienda.
Fuentes: `linea-base.md` (A2), `11-inventario-incentivos-comerciales.md` (I4), `07-inventario-metas-uni-t-m1.md` (M1).

**Parámetros de referencia para los ejemplos** (de la regla de técnicos en DEV):
sueldo base semanal 2,000 · cuota 36 unidades/día × 6 días = **216/semana** · valor por unidad = 2,000 ÷ 216 = **9.26** ·
instalación nueva = 9 puntos · soporte/reparación (garantía) = 3 puntos. [DEV]

---

## 1. Periodo semanal y límites

**Estado actual [código]**
- Técnicos (Talento): `PayWeek`, sábado 18:00 → sábado 18:00 (corte inclusivo a las 18:00), filtrado por `validated_at` con fecha-hora.
- Vendedores: domingo → sábado desde 2024-06-02, por fecha calendario.
- Regla semanal de Talento (`talento_weekly_rules`): `whereDate`, ventana de 8 días en el borde; día calendario, no corte 18:00.
- Proyecto externo: por `report_date` (fecha), cae en la semana que contiene ese día.

**Ejemplo numérico (sintético).** Una instalación de 9 puntos se valida el sábado a las 17:59 y otra el mismo sábado a las 18:01.
Con `PayWeek` la primera cuenta en la semana que cierra ese sábado y la segunda en la siguiente. Con fecha calendario (vendedores,
regla semanal) las dos cuentan en la misma semana. Una técnica con 216 unidades justas en `PayWeek` queda en 207 si la de las 18:01
se la lleva la otra semana: no alcanza la cuota y pierde 9 × 9.26 = 83.33 de «valor por unidad» que esa semana no cobra.

| Opción | Pros | Contras |
|---|---|---|
| A. Mantener tres ventanas (estado actual) | Cero cambios; lo histórico no se mueve | Una misma persona (técnico-vendedor) tiene dos semanas distintas; cifras que no cuadran entre pantallas |
| B. Unificar todo en `PayWeek` (sáb 18:00) | Un solo calendario para pago, tablero, escalafón y reglas semanales | Vendedores pasarían de dom–sáb a corte sábado 18:00; hay que migrar reportes ya emitidos |
| C. Unificar el cálculo en `PayWeek` y dejar a Vendedores como vista dom–sáb solo informativa hasta decidir | Pago de Talento consistente sin tocar lo histórico de Vendedores | Dos calendarios visibles un tiempo |

**Recomendación: C** (reversible, detrás de interruptor; pasa a B cuando Irving lo confirme).

## 2. Compatibilidad vendedor / Talento / embajador

**Estado actual [código][DEV].** Tres beneficiarios y destinos distintos (inventario I4): vendedor → `payment_by_rule`
(51 pagos de sueldo fijo, 21 de venta adicional); colaborador → `talento_ledger_entries` (3 bonos de regla semanal);
embajador → comisión aplicada como descuento en factura (46 filas: 16 `applied`, 30 `approved`; 0 con factura aplicada).
`payment_by_rule.colaborador_id` está vacío en las 72 filas. Un caso de cliente que es vendedor y embajador a la vez.

**Ejemplo (sintético).** Persona X es colaboradora y vendedora. En la semana: sueldo fijo de vendedor 1,500 (por `FixedSalaryPayment`)
y bono semanal de regla `ventas` 300 (ledger). Hoy se cobran **ambos**: 1,800. Si se declara incompatibles, cobraría el mayor (1,500) o
el que decida la regla de precedencia.

| Opción | Pros | Contras |
|---|---|---|
| A. Compatibles siempre (hoy), solo se muestran juntos | No quita ingreso a nadie | Posible doble reconocimiento de la misma venta |
| B. Matriz configurable por concepto (compatible / excluyente / tope) | Irving decide caso por caso; se pueden combinar reglas | Más configuración que mantener |
| C. Excluyentes por persona (solo un sistema paga) | Simple de auditar | Quita ingreso a quien hoy lo tiene en ambos |

**Recomendación: B**, con el valor por defecto = A (todo compatible), así no cambia nada hasta activar. Embajador se trata aparte (es descuento en factura, no dinero).

## 3. Sueldo configurable frente a importes en código

**Estado actual [código].** `FixedSalaryPayment.php:34-39`: si no cumple mínimos paga `ventas × 300 + prospectos × 15` y el atajo
`ventas × 20 + prospectos ≥ 120` está fijo en el código; los importes 300 y 15 también. Las reglas de Talento (`talento_compensation_rules`) sí guardan el sueldo base en BD.

**Ejemplo (sueldo 1,500; mínimos 2 ventas y 8 prospectos, regla no fija).**
- 5 ventas y 20 prospectos: 5×20+20 = 120 ≥ 120 → paga 1,500.
- 3 ventas y 10 prospectos: 3×20+10 = 70 < 120 → paga 3×300 + 10×15 = 1,050.
- Si Irving quiere 350 por venta, hoy hay que editar código; los mismos 3 y 10 pagarían 1,200.

| Opción | Pros | Contras |
|---|---|---|
| A. Dejar en código | Estable | Cambiar un importe exige desarrollo y versión |
| B. Mover 300, 15 y 120 a columnas de la regla (default = valores actuales) | Irving los edita sin desarrollo; histórico igual | Migración + prueba de que el default no cambia el cálculo |
| C. Fórmula configurable (`formula_config`, hoy sin motor) | Máxima flexibilidad | Construir un motor completo; riesgo alto de error en dinero |

**Recomendación: B.**

## 4. Reconocimiento con varios participantes

**Estado actual [código].** Órdenes (`tasks`/OT): **cada asignado recibe la unidad completa** (`countBillableUnits`, `whereHas users`).
Proyecto externo: reparto **1/N** de los puntos del reporte (`ProjectActivityService`).

**Ejemplo (sintético).** Instalación de 9 puntos con 2 técnicos asignados:
- Hoy: 9 + 9 = **18 puntos** entre los dos (el trabajo vale el doble).
- 1/N: 4.5 + 4.5 = 9.
- Responsable 100 % + ayudante 0 %: 9 y 0 (el ayudante no suma a su cuota).
- Responsable 70 % / participante 30 %: 6.3 y 2.7.
Con valor por unidad 9.26: hoy la orden cuesta 166.67; con 1/N, 83.33.

Conviene separar cuatro conceptos: **responsable** (a quién se le imputa la garantía), **participación** (cuánto del pago),
**producción individual** (cuenta para cuota/escalafón) y **resultado de equipo** (bono colectivo).

| Opción | Pros | Contras |
|---|---|---|
| A. Unidad completa a cada uno (hoy) | Cero cambios; nadie baja | Se paga de más; incentiva asignar gente de sobra |
| B. Reparto 1/N en todo (igual que proyecto externo) | Coherente con lo que ya hace planta externa; costo del trabajo constante | Un ayudante que sí aportó baja su cuota |
| C. Porcentajes por rol (responsable / participante) configurables | Refleja el trabajo real; garantía sigue al responsable | Requiere capturar el rol por orden |

**Recomendación: C con default = A**; si Irving prefiere simplicidad, B.

## 5. Garantías y trabajos de corrección

**Estado actual [código][DEV].** El tipo «Soporte/reparación (garantía)» vale 3 puntos, es cobrable (`is_billable`) y **no exige validación**
(`requires_validation = 0`). La instalación nueva abre una ventana de responsabilidad de 6 meses (`WarrantyWindowService`).
No hay distinción en el pago entre una reparación a un cliente ajeno y la corrección de una instalación propia.

**Ejemplo (sintético).** 12 reparaciones en la semana = 36 puntos = 36 × 9.26 = 333.33 de valor, equivalente a 4 instalaciones.
Si de esas 12, 5 corrigen instalaciones hechas por la misma persona dentro de su ventana de 6 meses, hoy cobra 5 × 3 × 9.26 = 138.89
por arreglar su propio trabajo.

| Opción | Pros | Contras |
|---|---|---|
| A. Igual que hoy (cobra 3 puntos siempre) | Sin cambios | Se cobra por corregir el propio error |
| B. Cero puntos si es corrección de la propia instalación dentro de la ventana | Alinea incentivo a la calidad | Necesita enlazar la reparación con la instalación original |
| C. Cero puntos + descuento por reincidencia (usa `clawback_*`, hoy sin motor) | Máximo incentivo de calidad | Toca dinero ya pagado; riesgo de disputa |

**Recomendación: B** (sin descuento retroactivo), y exigir validación a las reparaciones que cuentan.

## 6. Metas, calidad, permanencia y descomisión

**Estado actual [código][DEV].** Cuota de pago y mínimo de regla semanal son dos campos distintos que hoy coinciden solo para el rol técnico (216);
vendedores tienen otro par de cifras (M1, contradicciones 1–2). Campos `clawback_*`, `monthly_bonus` (en Talento), `formula_config`, `kpi_key`, `conditions`
están en la tabla sin motor que los lea. Calidad de señal: 1 regla activa de 7. Exámenes y certificaciones en 0 uso.

**Ejemplo (sintético).** Venta con comisión de 600 que el cliente cancela a los 20 días. Hoy la comisión ya pagada se queda
(no hay descomisión). Con una ventana de permanencia de 30 días y descomisión total, se descontaría 600 en la semana siguiente; con 50 %, 300.

| Opción | Pros | Contras |
|---|---|---|
| A. Una sola meta por concepto (la cuota de la regla de compensación), y la regla semanal la lee | Elimina doble cifra | Cambia el origen del mínimo semanal |
| B. Mantener ambas pero con validación de coherencia (aviso si divergen) | No cambia pagos | La duplicidad sigue |
| C. A + permanencia y descomisión configurables (desactivadas por defecto) | Cierra el hueco de ventas que se caen | Toca dinero pagado; exige política escrita |

**Recomendación: A ahora; C después, con política de permanencia definida por Irving.**

### Política de descomisión cerrada por Irving (#1216 → #1220, 2026-11-09)

- **Solo comisiones PENDIENTES** se descomisionan. **Nunca clawback** de comisiones ya pagadas.
- **Ventana de permanencia móvil**: 30 días desde la activación (`descomision.ventana_dias`); si el cliente cancela o no concreta el primer pago dentro de ella, se revierte el 100 % de la comisión aún pendiente.
- **Desbloqueo** de un vendedor bloqueado: solo Irving, mediante permiso Spatie, con motivo obligatorio y entrada en bitácora.
- **Apagado por defecto**: `config/descomision.php` → `enabled=false` (`DESCOMISION_ENABLED`). El status «Bloqueado» se resuelve por nombre en `seller_status` (`status_bloqueado`), compatible con `sellers.status_id_antes_de_inactivar` (#1193).
- Esta fase es solo política + config; el motor (servicio/job) es un item posterior.

## 7. Planta externa — evaluación 1–10, bono normal y excelencia

**Lo pedido.** Evaluación 1–10; bono normal desde 9; excelencia en 10; componente individual y colectivo.
No trasladar el esquema a todos los puestos.

**Lo implementado [código][DEV].** No existe evaluación 1–10. Hoy: puntos por actividad con reparto 1/N (`ProjectActivityService`) y un bono de proyecto
por **porcentaje de avance** (`ProjectBonusService` + `TalentoProject::computeBonus` con `bonus_scale` por umbral de `threshold_pct`),
repartido **proporcional al sueldo base** de quienes aportaron. `talento_projects` tiene 0 filas en DEV: nada que contrastar con datos reales.

**Falta [por construir, si Irving lo aprueba]:** captura de la evaluación 1–10 (quién evalúa, cuándo), umbrales 9 y 10 configurables,
separación del monto individual (por puntos propios) y colectivo (por resultado del equipo), y un tope.

**Ejemplo (sintético).** Proyecto con bono total 10,000, 4 personas. Mitad individual (5,000, por puntos) y mitad colectivo (5,000 ÷ 4 = 1,250 c/u).
Evaluación 8 → sin bono; 9 → bono normal (100 %); 10 → excelencia (p. ej. 120 %, 12,000 en total). Hoy el reparto es solo proporcional al sueldo, sin evaluación.

| Opción | Pros | Contras |
|---|---|---|
| A. Dejar el bono por avance % (hoy) | Ya funciona | No premia calidad |
| B. Añadir evaluación 1–10 como multiplicador del bono existente, solo para proyectos de planta externa | Reusa el motor; no se expande a otros puestos | Requiere captura y definir evaluador |
| C. Esquema 1–10 para todos los puestos | Uniforme | Fuera de lo pedido; mucho trabajo |

**Recomendación: B**, con interruptor apagado por defecto.

---

## Reproducibilidad
Los ejemplos usan solo los parámetros de referencia de arriba. Los datos de DEV citados salen de `php artisan uni:linea-base`
(A2), de las consultas del documento 11 (I4) y del inventario 07 (M1); se pueden repetir con esas consultas de solo lectura.

## Preguntas para K2
Cargadas como `preguntas` estructuradas en #905 (una por tema, con opciones, pros/contras y recomendación).

## Decisiones de Irving (K2 · #905)
Registradas en `config/talento_incentivos.php` con el comportamiento actual como valor por defecto; no cambian ningún cálculo
hasta que D8b (#906), I5 (#907), L3 y M2 las lean detrás de su interruptor.

| § | Tema | Opción elegida |
|---|------|----------------|
| 1 | Periodo semanal | 3 — Talento en PayWeek; Vendedores dom–sáb solo informativo |
| 2 | Compatibilidad | 2 — matriz configurable (compatible/excluyente/tope), default todo compatible |
| 3 | Importes de vendedor | 2 — columnas de la regla con los valores actuales como default |
| 4 | Varios participantes | 3 — porcentajes por rol, default = hoy |
| 5 | Garantías | 2 — cero puntos al corregir la propia instalación en 6 meses, sin clawback |
| 6 | Metas duplicadas | 1 — una sola meta (cuota de la regla); permanencia/descomisión después |
| 7 | Planta externa | 2 — evaluación 1–10 como multiplicador, interruptor apagado |
