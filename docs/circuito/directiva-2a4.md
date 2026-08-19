# Directiva 2A.4 — `env()`, candado de coherencia, frenos asimétricos, precedencia

> **Fecha:** 2026-08-18 · **Autor:** Irving · **Estado:** implementada
> (`4fd7ec84`, `9b4c677f`, `2c4aedeb`, `c1e3dbca`, `8b1bf65e`, `2bc4ff0d`).

## 1. Los `env()` suben por delante de 2A.4

El circuito depende de `env()` en runtime y la convención del proyecto mandaba `config:cache` como
paso de cierre. **Cada sesión que siguiera la convención al pie de la letra dejaba al circuito sin
API key.** La regla escrita y el código en producción se contradecían en el punto exacto del que
depende que el circuito pueda llamar a Claude.

→ Mover a `config/` las ~23 críticas y devolver `config:cache` al checklist de cierre.

**Pregunta abierta en la directiva:** con `config:cache` puesto, ¿el circuito falla ruidoso o
callado? *(Respondida: **callado**. Ver [`directiva-2a-cierre.md`](directiva-2a-cierre.md) §1.)*

## 2. La deriva ya va en cuatro — ponle un candado

El candado atómico del reclamo repitiendo los `not like` a mano es la **cuarta** aparición del mismo
predicado: scope de Eloquent, SQL crudo del reclamo, `preg_match` en PHP y la Vue.

> Falta el candado que impide que se vuelva a separar: **un test que afirme que el scope y el
> reclamo atómico seleccionan el mismo conjunto** sobre una muestra de items.

**Por qué ésta es la cara:** el reclamo atómico decide **qué toca un worker**. Una deriva ahí no es
un tablero mudo, es una terminal trabajando sobre algo que no debía.

*(Al implementar aparecieron **cinco** copias, no cuatro: `SupervisorService::listosParaTerminal`
tenía una que además no honraba `origen_bloqueo='humano'`.)*

## 3. 2A.4 — los frenos humanos NO caducan

Regla **asimétrica**:

- **Freno del clasificador** → caduca solo. Es un consejo automático; si nadie lo confirmó, vence.
- **Freno humano** → **nunca caduca.** Es una decisión de Irving y el sistema no la revoca por
  antigüedad. Pero **se resurfacea**: cada N días el digest lista *"frenos que pusiste tú y llevan X
  días en pie"*, con el item, la fecha y lo que decía el rótulo.

> Los 33 no son items bloqueados por error — son decisiones tuyas que olvidaste haber tomado. Un
> caducado automático te las revocaría a la mala; un recordatorio te las devuelve para que decidas
> de nuevo con la cabeza fresca.

**Escalado:** freno humano con muchas aprobaciones mudas acumuladas sube al principio del digest. Es
la señal más limpia de "aquí hay un desacuerdo entre lo que decidiste y lo que quieres".

*(Corrección posterior de Irving: el ejemplo original era #186, que **no** es uno de los 33 — sus 32
mudas venían del guard de `excluir_pool_automatico`. El caso que sí ilustra la regla es **#65: 48
aprobaciones contra su propio `[BLOCKED-NEGOCIO]`, 38 días**.)*

## 4. 2A.5 — define la precedencia antes de ampliar el guard

Al ampliar el guard #456 a `aprobado_irving`, las dos direcciones quedan activas a la vez. Define
explícitamente **qué gana cuando ambos campos cambian en el mismo save**, y déjalo escrito en el
código, no implícito en el orden de los hooks. Es el único punto donde este arreglo puede volverse un
bucle o un pisotón silencioso.

*(Resuelto: gana `estado_aprobacion`. Los días con `+` en el reporte de frenos son cotas inferiores;
sellar hoy y reportar "0 días" habría sido la mentira que la fase vino a matar.)*
