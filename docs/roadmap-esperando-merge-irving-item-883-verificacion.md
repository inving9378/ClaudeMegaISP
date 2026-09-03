# Item #883 — Torre 24/7 · revisar los `esperando_merge_irving`: separar mergeables legítimos de retenidos con motivo real (RESUELTO — 100% retención legítima, 0 mergeables encolados)

## Contexto

Sub-item de seguimiento de #873. El diagnóstico original (comentarios de #873) ya había descartado
la premisa de que `circuito:destrabar-bandeja` estuviera caído o gateado: corre cada ~5 min y sí
evalúa estos items. La tarea de #883 era otra: de los items en `esperando_merge_irving=true`,
separar los que están retenidos por un **motivo real** (frontera dura, verificación fallida,
conflicto de merge) de los que quedaron ahí por una **clasificación evitable** (p. ej. cap de
auto-merge agotado en un ciclo) — y para estos últimos, encolar el merge con `circuito:merge-run`
(nunca `git push`, nunca tocar prod).

Una vuelta previa (2026-09-03 13:19, mismo slot `wt-2`) ya había hecho el grueso del trabajo:
corrió el dry-run de `circuito:destrabar-bandeja` + clasificó a mano los 62 items vivos de ese
momento con `JarvisService::pendienteReal`/`elegibleAutoMerge`, y encontró que 57 estaban
bloqueados por `nivel_riesgo=C` (motivo legítimo) y 1 (#638) por vivir en un repo aparte sin
integración del circuito. Esa vuelta se cortó a los 600s sin comitear nada y escaló a Irving vía
des-trabe con 3 preguntas estructuradas sobre metodología. Irving las aprobó (2026-09-03 13:38,
`irving:CARLOS`) eligiendo en las tres la opción recomendada:

- **q1** (cómo procesar): generar una tabla-reporte (ID, título, rama, riesgo, motivo) y
  presentarla a Irving para que apruebe merges en lote.
- **q2** (criterio retenido vs. mergeable): retenido = toca frontera dura (dinero/seguridad/
  permisos/prod/negocio) O falla verificación O conflicto de merge; mergeable = aditivo+reversible+
  verificado+fuera de frontera dura.
- **q3** (qué hacer con los retenidos): mover a la bandeja de decisión de Irving con motivo
  documentado, agrupados por tipo de riesgo.

Esta vuelta ejecuta esa decisión: re-corre la clasificación completa (el conteo creció de 58→60
desde el diagnóstico original) y entrega el reporte.

## Clasificación completa (60 items vivos en `esperando_merge_irving=true`, hoy)

Consulta directa a la BD de dev (`RoadmapItem::where('esperando_merge_irving', true)`):

| Motivo de retención | Cantidad | Legítimo (q2) |
|---|---|---|
| `nivel_riesgo = C` (decisión de diseño / frontera dura — botón de Irving) | 59 | ✅ Sí |
| Rama en repo aparte sin integración del circuito (#638) | 1 | ✅ Sí |
| **Clasificación evitable (candidato a `circuito:merge-run`)** | **0** | — |

### Por qué los 59 de nivel C son 100% legítimos, no una clasificación evitable

Verificado directo en el código, no por inferencia: `JarvisService::elegibleAutoMerge()` trae un
guard **incondicional** (línea 988-993, item #756, comentario explícito en el propio archivo):

```php
if ($item->nivel_riesgo === 'C') {
    // ...
    return $no('Nivel de riesgo C: lo mergea Irving (botón/--force), el auto-merge no decide sobre frontera dura.');
}
```

El comentario que antecede al guard (líneas 978-988) documenta que este candado se agregó
*a propósito* tras un bypass real (#753 llegó a encolarse antes de que existiera): "nivel C bloquea
sin importar qué tan limpio esté el diff". No es una cola atascada ni un cap agotado — es el mismo
candado que exige CLAUDE.md ("Nivel C: jamás sin decisión de Irving"). El único camino de estos 59
items es que Irving pulse el botón "Mergear" de la Torre (que sí puede encolarlos vía
`RoadmapCircuitoService::enqueueMerge`, sin gate de nivel — el gate está en el auto-merge, no en el
botón humano) o corra `circuito:merge-run --force` según decida item por item.

Desglose por módulo (para la revisión en lote que pidió q1):

| Módulo | Items (nivel C) |
|---|---|
| Core / Permisos | 11 (#843 #847 #851 #856 #857 #862 #863 #864 #865 #866 #867) |
| DocumentacionCorporativa | 9 (#667 #732 #734 #751 #758 #812 #815 #823 #834) |
| ModuleManager | 5 (#842 #852 #853 #859 #860) |
| Finanzas | 4 (#632 #719 #721 #722) |
| Flotas | 4 (#65 #99 #103 #720) |
| Infra | 4 (#78 #106 #156 #157) |
| Payments | 4 (#117 #142 #272 #285) |
| Auditoria | 2 (#845 #846) |
| Auth | 2 (#153 #179) |
| MegaFamilia | 2 nivel C (#21 #32) + 1 nivel B especial (#638) |
| PortalCliente | 2 (#148 #151) |
| PortalPago | 2 (#162 #163) |
| Roadmap / Circuito CC | 2 (#844 #880) |
| Usuarios | 2 (#105 #274) |
| GestionRed | 1 (#677) |
| Inventario | 1 (#753) |
| Vendedores | 1 (#269) |
| VoIP | 1 (#67) |

Todas comparten el mismo motivo mecánico (`nivel_riesgo=C`); el contenido de cada rama es distinto
(dinero, credenciales, prod, decisiones de producto/diseño — ver título de cada uno arriba), pero
la retención en sí no depende del contenido: es el candado de nivel, funcionando como se diseñó.

### El caso especial — #638

`branch=null` **a propósito**: vive en `/var/www/megafamilia-rn`, un repo git separado (React
Native) sin worktree ni integración del circuito. El botón Mergear/`circuito:integrar` de la Torre
es un no-op inofensivo ahí porque no hay rama que mergear en el monorepo. El trabajo ya está
terminado y verificado (`tsc --noEmit` limpio) en la rama `circuito/item-638-vincular-hijo` de
*ese* repo; el `motivo_bloqueo` del propio item ya documenta la fusión manual pendiente:
`cd /var/www/megafamilia-rn && git merge circuito/item-638-vincular-hijo`. `circuito:merge-run` no
aplica — opera sobre el checkout principal del monorepo Laravel, no sobre repos externos.

## Qué NO se hizo, y por qué

- **No se corrió `circuito:merge-run`**: no hay ningún item con motivo evitable que encolar. Correr
  el comando sobre una cola vacía no habría cambiado nada (ya se confirmó: `Cola de merge vacía`
  es el comportamiento esperado sin encolados).
- **No se encoló manualmente ningún nivel C** vía `enqueueMerge`: eso equivaldría a simular el
  botón de Irving por él, exactamente lo que el candado #756 existe para impedir y lo que las
  reglas de este ejecutor prohíben (nivel C = frontera dura, decisión exclusiva de Irving).
- **No se tocó #638** más allá de leerlo: su fusión vive fuera del monorepo y ya está documentada.

## Conclusión

De los 60 items en `esperando_merge_irving=true`, **el 100% tiene un motivo de retención real**
(59 por `nivel_riesgo=C`, candado incondicional y a propósito; 1 por vivir en un repo externo sin
integración). **0 candidatos a `circuito:merge-run`.** El reporte de esta vuelta (tabla por módulo
arriba) es la entrega que pidió Irving en q1: visibilidad completa para que decida en lote, desde
la Torre (tarjeta "🚧 Por qué no avanza") o el detalle de cada item, cuáles ramas nivel C mergear
él mismo.
