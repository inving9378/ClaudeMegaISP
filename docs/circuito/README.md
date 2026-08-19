# `docs/circuito/` — las directivas, versionadas

> **Por qué existe esta carpeta.** `circuito-fase2a.md` se escribió en una sesión de Cowork y se
> entregó como archivo suelto; cuando la sesión de CC fue a buscarlo para implementar la Fase 2B, no
> estaba en el repo. Una especificación que vive sólo en un chat es la próxima regla en un solo
> lugar, y toda la Fase 2A se fue en cerrar exactamente esa clase de agujero.
>
> **Regla:** toda directiva que cambie el comportamiento del circuito se commitea aquí, con fecha, al
> mismo tiempo que el código que la implementa.

| Documento | Qué fija |
|---|---|
| [`directiva-2a4.md`](directiva-2a4.md) | `env()`→`config`, candado de coherencia, frenos asimétricos, precedencia del #456 |
| [`directiva-2a-cierre.md`](directiva-2a-cierre.md) | `escala:sin_modelo`, liveness de procesos programados, stashes, Paso 0 de 2B |
| [`directiva-2b.md`](directiva-2b.md) | El generador arranca produciendo su propio sustrato |
| [`../fase2b-paso0-inventario-modulejson.md`](../fase2b-paso0-inventario-modulejson.md) | La medición que fundamenta la 2B |

## Lo que estos documentos tienen en común

Casi todo lo de la Fase 2A fue **la misma enfermedad en capas distintas**: una regla que vive en un
solo lugar (o en ninguno) se cae sin que nadie se entere. El inventario de instancias:

1. El predicado de despacho, en cinco dialectos → definición única + candado (2A.5 §2).
2. El freno del clasificador confundido con el humano → columna `origen_bloqueo` (2A.3).
3. Las escrituras crudas esquivando la traza de banderas → test estático (#780).
4. El candado atómico del reclamo quedándose atrás del guard → seam compartido (2A.5).
5. **Escalar por juicio y escalar por no haber modelo, indistinguibles** (#807). La peor: aquí no hay
   un lector equivocado que corregir — el lector es un humano viendo una historia coherente.
6. Una regla implementada y **no agendada** (#808). Y su versión irónica: el vigilante sellando un
   latido falso en `--dry`, enmascarando justo lo que existe para delatar.
7. Un detector que presupone la dirección del error → «declaración y realidad no coinciden» (2B).

**El patrón:** el modo de fallo peligroso no es el ruidoso. Es el que produce una historia
plausible. Por eso el criterio de cierre de cada una no es "está arreglado" sino **"¿qué truena si
alguien lo vuelve a separar?"** — un test, un exit code o una línea del digest. Nunca un párrafo.
