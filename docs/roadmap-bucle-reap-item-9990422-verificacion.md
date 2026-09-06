# Item #9990422 — bucle reap sobre sub-item ya descompuesto (FASE 1 retomada: detección causa=limite_cuenta en vuelta.sh)

## Contexto

`#9990422` ("FASE 1 (retomada): implementar detección causa=limite_cuenta en vuelta.sh",
sub-item de `#9990415`) traía la spec EXACTA de un cambio acotado de ~5 líneas en
`deploy/circuito/vuelta.sh`. El propio log del item muestra que una vuelta previa (misma
terminal `wt-6`) ya hizo lo correcto:

1. Corrió `circuito:cabida 9990422` → **NO CABE** (`historico_excede_umbral`: mediana histórica
   del módulo Roadmap/Circuito CC nivel B ~482s > umbral 480s — el umbral está al filo, no es
   por tamaño real del cambio, que es de una sola rama `if` en un solo archivo).
2. Descompuso el trabajo, SIN crear rama ni tocar código, en **`#9990426`** ("Implementar
   detección causa=limite_cuenta en vuelta.sh (spec exacta de #9990422)"), con la spec intacta +
   una nota para quien lo tome: si `circuito:cabida` vuelve a dar NO CABE por el mismo motivo,
   consultar a Thomas en vez de decomponer de nuevo (posible falso positivo sistémico del
   módulo).
3. Dejó la nota de decisión en `comentarios_claude` a las `17:04:11`.

Pero el proceso **murió antes de intentar el cierre** del padre: el log registra, en el mismo
minuto (`17:04:11`), el evento `claim_liberado_al_morir_la_vuelta` (`soltar-claim`) — el claim se
liberó y el item volvió a `aprobado_revisor` sin nadie detrás. El pool lo repartió de nuevo (otra
vez a `wt-6`) sin que hubiera trabajo propio que hacer: exactamente la misma familia de bug ya
documentada en `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/
`#9990012`/`#917`/`#910`/`#936`.

## Verificación

Consultado `#9990426` directo en BD: sigue **intacto**, `pendiente_revision`, sin reclamar
(`worker_sid=null`, `claimed_at=null`), con la spec exacta preservada carácter por carácter (solo
difiere en unos pocos acentos ASCII-safe del texto, sin cambio de contenido). La descomposición
original seguía siendo la correcta — nadie más la tocó.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía
tinker). El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS", ~líneas
333-360) lo reenrutó a `aprobado_irving` + `excluir_pool_automatico=true`, liberando
`worker_sid`/`claimed_at` (evento `paraguas_abierto` en el log: "le quedan 1 sub-item(s)
abierto(s)"), sacándolo del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:558-590` aprox.) lo complete solo cuando `#9990426` cierre.

## Resultado

Sin cambio de código de negocio. El trabajo técnico real (insertar la rama `limite_cuenta` en
`deploy/circuito/vuelta.sh`) sigue en `#9990426`, `pendiente_revision`, listo para que el revisor
lo tríe.
