# Deriva #216 Fase 3a (item #807) — consumidores + desde-cuándo de las diferencias "falta_en=migraciones"

**Item:** #807, sub-item de #740 (paraguas Fase 3). **Alcance:** solo lectura — audita, no migra ni borra nada.

## Resultado

`storage/app/circuito/diff-esquema-216-fase3a.json` (artefacto de runtime, gitignored como el resto de
`storage/app/circuito/*` — igual que su insumo `diff-esquema-216.json`) con **54 objetos**
`{tabla, elemento, tipo, falta_en, consumidores[], desde_cuando, notas}`.

## Drift de alcance encontrado (documentado, no bloqueante)

El item se redactó sobre un corte del diff (#739, 49 hallazgos totales, 34 en el subconjunto
"falta_en=migraciones") que **ya no coincide** con el archivo vigente: entre ese corte y hoy,
`megaisp_dryrun` se reconstruyó (#797/#798) y el comando generador sumó comparación de índices
(#800/Fase 2b). El archivo actual tiene **131** registros con `lado_faltante=megaisp_dryrun`, de los
cuales solo **54** corresponden a las tablas/columnas nombradas explícitamente en la descripción del
item (10 tablas huérfanas completas + 44 columnas/índices en las 13 tablas restantes). Decisión
(regla de oro, sin frontera dura): trabajar exactamente el subconjunto nombrado por el item, no los
131 — los otros 77 registros (familias `auditoria_*`, `circuito_*`, `ipv6_*`, `torre_*`,
`vigilante_*`, más varias `dc_*` de otro alcance) son diferencias **nuevas**, aparecidas después de
que #740 se descompusiera en 3a/3b/3c, y quedan fuera de este sub-item — candidatas a un futuro
barrido, no de este.

`talento_ot_type_evidence_requirements` (nombrada en la descripción original) **ya no aparece** en el
diff vigente: su migración quedó resuelta entre ambos cortes. Se documenta como resuelta, sin fila en
el JSON.

## Resumen de los 54

| Categoría | Cantidad |
|---|---|
| Con consumidores reales | 37 |
| Sin consumidores | 17 |
| **Falso positivo** (migración real existe, `megaisp_dryrun` desactualizado) | 33 |
| Huérfanas genuinas, candidatas a limpieza (sin migración en ningún lado) | 17 |
| Caso especial: consumidores reales pero SIN migración en ningún lado | 1 |

### Hallazgo principal: la mayoría (33/54) son falsos positivos por snapshot desactualizado

`megaisp_dryrun` es una base de referencia construida corriendo las migraciones; no se auto-actualiza
cuando nuevas migraciones llegan a `main`. Para 33 de los 54 registros SÍ existe una migración real y
reciente (rango 2026-06-02 a 2026-08-29, la mayoría del módulo `Roadmap` de días recientes y de
`DocumentacionCorporativa` del 2026-08-28/29) con consumidores activos en el código — el diff solo
refleja que el snapshot de `megaisp_dryrun` es anterior a esas migraciones. **No requieren ninguna
migración nueva**; se resuelven solos si/cuando se re-ejecute el rebuild de `megaisp_dryrun`
(#797/#798).

Ejemplos: las 3 tablas huérfanas de `dc_entregas`/`dc_solicitudes` (migración del 2026-08-29 14:33,
commit `4615c1f5`, circuito#760) y las 4 tablas de `dc_activos_digitales`/`dc_documento_versiones`/
`dc_documentos`/`dc_inventario_accesos` (migraciones del 2026-08-28, Fase 3/#662 de
DocumentacionCorporativa) — el propio item las había listado como huérfanas, pero para hoy ya tienen
migración; y las 29 columnas/índices de `roadmap_items` (tabla del propio circuito, en desarrollo
diario) más las 4 columnas de `invoices` (migración del 2026-08-28 23:06, commit `bdcb8f7e`,
circuito#748).

### Huérfanas genuinas (17) — sin migración en ningún lado, candidatas a limpieza

- **6 tablas** sin ningún rastro de código ni migración: `client_cfdi_invoices`, `client_invoice_cfdi`,
  `invoice_serviceables` (0 consumidores, 0 migraciones — ni en `database/migrations_old/` ni en
  ningún módulo).
- **3 columnas `deleted_at`** en `fleet_assignments`/`fleet_fuel_log`/`fleet_photos`: la migración base
  de Flotas (`2026_06_01_180000_create_fleet_tables.php`) les agrega `softDeletes()` a las tablas
  hermanas pero NO a estas 3; los modelos no usan el trait `SoftDeletes`. Columnas huérfanas.
- **5 columnas** de `fleet_subscriptions` (`base_price`, `included_units`, `overage_amount`,
  `overage_unit_price`, `overage_units`): el modelo real usa `price_per_vehicle`/`monthly_price`;
  probable diseño de precios con excedente por unidad, abandonado.
- **2 columnas** `users.password_legacy`/`password_migrated_at`: sin consumidores ni migración,
  probable remanente de un intento anterior de la migración base64→bcrypt, reemplazado por
  `PasswordService` (que no las usa).
- **4 columnas** `roadmap_items.preguntas_natural`/`preguntas_natural_at`/`resumen_natural`/
  `resumen_natural_at`: caso llamativo por ser la propia tabla del circuito — sin `$fillable`, sin
  migración, sin ninguna referencia. Probable resto de una función de lenguaje natural abandonada.

Por la decisión ya tomada en la pregunta q2 del item (opción elegida por Irving): estas 17 solo se
**documentan**, no se tocan ni se migran en este sub-item.

### Caso especial (1): `marketing_niches.preferred_voice_model`

Único registro con consumidores **reales y activos**
(`VoiceComparatorController.php` + `VoiceComparatorView.vue`, el comparador de voces TTS) pero **sin
ninguna migración** en el repo — a diferencia de su columna hermana `preferred_voice_id`, que sí la
tiene. Probable `ALTER TABLE` manual durante el desarrollo del comparador. Es la candidata prioritaria
para que Fase 3b/Irving decida escribir la migración de reconciliación (escribir esa migración está
fuera de alcance de este item).

## Metodología

Por cada uno de los 54: `grep -rn` del nombre de tabla/columna/índice en `app/ resources/ routes/
config/` (incluyendo migraciones de módulos addon, no solo `database/migrations/`) para consumidores;
y `git log -1 --date=iso` sobre el archivo de migración encontrado (o `database/migrations_old/` si
no había ninguno vigente) para el "desde cuándo".

## Sin cambio de código

Este item es de auditoría de solo lectura. No se escribió ninguna migración de reconciliación (fuera
de alcance explícito). El artefacto JSON completo queda en
`storage/app/circuito/diff-esquema-216-fase3a.json` para que Fase 3b/3c y el propio #740 lo consuman.
