# Circuito CC #9990394 — Ratio útil/ruido del barrido (#908) + cierre de #910

Parte 2/2 de la medición honesta pedida por #910 (Torre 24/7 · Pieza 5d). Parte 1 (#9990393,
inventario + snapshots de ocupación) seguía `en_progreso` bajo otra terminal (`wt-2`, reclamada
en el mismo instante que este sub-item) al momento de ejecutar esta parte — es una carrera de
despacho paralelo del pool, no una dependencia bloqueante real: el inventario y la clasificación
que pide el spec de #910 son 100% derivables por lectura directa de `roadmap_items`, así que se
recalculan aquí de forma independiente (solo-lectura, sin tocar #9990393) para no quedar
esperando sin necesidad.

## Inventario — cómo se identifican los items del barrido (#908)

Dos vías de detección, ambas exhaustivas sobre las 1149 filas de `roadmap_items` en dev,
coinciden exactamente:

1. `title LIKE '[BARRIDO]%'` (prefijo literal que pone `BarridoService::crearItemDeHallazgo()`).
2. `log[].por === 'barrido'` (evento `item_creado` que deja `RoadmapIntakeService` al crear con
   origen `'barrido'`).

**Resultado: 12 items en total**, generados en una sola ventana de ~1h15min el 2026-09-04
(06:45:04 → 08:00:06, hora local -06:00), repartidos en 12 módulos distintos (uno por item —
GestionRed, Tickets, Talento, Flotas, Marketing, Payments, MegaFamilia, VoIP, WhatsAppAgent,
PortalCliente, PortalPago, Usuarios): todos hallazgos `barrido_todo_fixme` (comentarios
TODO/FIXME/deprecated encontrados durante la exploración), clase `mecanico` → nivel A ejecutable.
Cero hallazgos `barrido_error_sintaxis` y cero de clase `producto` (ninguno fue a la bandeja de
Irving) en esta corrida.

## Clasificación — útil vs. ruido vs. abierto

| Categoría | Definición | N | % |
|---|---|---|---|
| **ÚTIL** | `estado_aprobacion=completado` AND `merge_commit` no nulo | **12** | **100%** |
| RUIDO | cerrado/archivado/duplicado/sin_acción SIN merge_commit | 0 | 0% |
| ABIERTO | aún en curso | 0 | 0% |

Los 12 items — `#9990229, #9990230, #9990233, #9990234, #9990236, #9990237, #9990239, #9990240,
#9990242, #9990243, #9990248, #9990252` — están **todos** `completado` con `merge_commit` real
(verificado id por id contra la tabla), y quedaron archivados horas después de crearse (mismo
día, ventana de 15-20 min entre creación y archivado cada uno). **Cero ruido, cero abiertos.**

**Hallazgo honesto (spec de #910 exige decirlo explícito en ambos sentidos, no solo cuando es
malo):** la proporción de ruido NO es alta — es 0%. El diseño del barrido, en esta única muestra
de 12 items, generó únicamente hallazgos mecánicos reales (comentarios TODO/FIXME vigentes en el
código) que se resolvieron y mergearon sin excepción. No hay señal de que esté llenando slots sin
generar valor.

**Límite de la muestra:** n=12, todos de una sola sesión de barrido (una terminal, una ventana de
75 minutos, un solo tipo de hallazgo — TODO/FIXME). No hay corridas posteriores registradas
todavía (el candado single-flight + la rotación de cobertura por módulo hacen que el barrido solo
dispare cuando el pool está seco; no ha vuelto a activarse desde entonces según este inventario).
Un ratio de 100% con una sola muestra no permite proyectar qué pasaría con volumen mucho mayor o
con otro tipo de hallazgo (`barrido_error_sintaxis`, `producto`) que hoy tiene 0 casos reales.

## Ocupación — snapshot puntual (sin serie histórica "antes" persistida)

Igual que documentó la parte 1 para su propio alcance: no existe un snapshot histórico
"antes"/"después" de ocupación específicamente correlacionado a las corridas de barrido — el
sistema no persiste esa serie. Se tomó una lectura puntual DESPUÉS (vía
`JarvisService::diagnostico()['terminales']`, 2026-09-06 ~12:2x): **2/6 terminales ocupadas, 4
libres, cola ejecutable = 0**. Es consistente con el diseño: el barrido (`BarridoService::debeBarrer()`)
solo dispara precisamente cuando la cola está seca y sobran terminales — por construcción, la
ocupación en el momento de barrer tiende a ser baja, así que un "antes/después" puntual no aporta
señal adicional sobre el valor del mecanismo; el ratio útil/ruido de arriba es la métrica que
responde la pregunta real del item ("¿el barrido llena slots sin generar valor?").

## Conclusión — recomendación explícita

El barrido (#908) generó, en su única corrida medida, 12 items mecánicos y los 12 terminaron en
commit útil mergeado a main. **0% de ruido.** No hay hallazgo preocupante que reportar sobre el
diseño del mecanismo en esta muestra. Recomendación: mantener el barrido activo tal como está;
re-evaluar con más muestra (corridas futuras, y en particular si algún día aparece un hallazgo
`producto` o `barrido_error_sintaxis`) antes de afirmar que el 100% es representativo a largo
plazo.

## Fuentes

- Item padre: #910 (Pieza 5d). Hermano: #9990393 (parte 1, inventario+ocupación — en progreso en
  paralelo bajo `wt-2` al momento de esta medición; sin datos propios registrados todavía, por lo
  que esta parte recalculó el inventario de forma independiente y equivalente).
- Piezas medidas: #907 (5a, disparador), #908 (5b, barrido+FIFO, vía #985/#986/#987), #909 (5c,
  verificación de vuelta, vía #988/#989/#990 — completada 2026-09-05 16:22).
- Metodología de referencia (mismo patrón, otra medición): `docs/circuito-verificacion-aflojo-item-911.md`.
- Consultas: `php artisan tinker` sobre `RoadmapItem` (title LIKE, log[].por, agregados por
  estado_aprobacion/merge_commit), `JarvisService::diagnostico()['terminales']`.
