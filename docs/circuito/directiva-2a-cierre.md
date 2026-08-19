# Directiva — cierre de 2A y vuelta al objetivo original

> **Fecha:** 2026-08-18 · **Autor:** Irving · **Estado:** implementada
> (`86548414`, `74688e3c`, `bd22a8b8`, `51f2af43`, `46898daa`).

## 1. #807 es el hallazgo más importante de toda la fase

> *"El sistema no se cae: se degrada a algo que parece una decisión legítima."*

Las cinco instancias anteriores eran de la misma familia: alguien escribe, otro lee otra cosa. Ésta
es de una familia peor. **No hay un lector equivocado que corregir** — el lector es un humano viendo
una historia coherente. Todo escala a `requiere_irving`, el autopilot deja de calificar, y el tablero
cuenta que el circuito está siendo prudente. Nada contradice esa historia.

**La señal pedida, sin tocar la falla-segura:**

> Distinguir `escala:juicio` de `escala:sin_modelo`.

Es mejor que un canario, porque **cada llamada real es su propia sonda**. No hay que mantener un
chequeo aparte que puede quedarse obsoleto — que es, otra vez, la misma enfermedad.

Lo mismo para `proponerPreguntas()`/`proponerOpciones()`: array vacío por fallo y array vacío por no
haber nada tienen que ser distinguibles. **Indistinguible-de-benigno es la firma de esta clase
entera.**

## 2. #808 — la regla existe pero no está agendada

2A.4 dejó el caducado del clasificador escrito, probado, fail-closed… y sin línea de cron no corre.
**Una regla implementada y no agendada es un no-op invisible.**

- Mientras #808 no aterrice, que el digest lo diga en primera línea. **Que la ausencia sea ruidosa.**
- Regla general: **todo proceso programado registra su última ejecución, y el digest delata al que
  lleve más de 48 h sin latir.**

*(Al implementarlo apareció la versión irónica del mismo bug: un `--dry` a mano sellaba latido falso
y enmascaraba justo lo que el vigilante existe para delatar. Resuelto con
`exige_opciones`/`excluye_opciones`.)*

## 3. Stashes

`stash{1}` → aplicar re-apuntado a `releases/torre-control/`: con seis terminales corriendo, `main`
seguía anunciando el paralelo como futuro. **La misma enfermedad de la fase, en la capa de copy.**
`stash{0}` → verificado obsoleto (0 líneas de diff contra su rama, 186 contra main) y soltado.

## 4. El fallback del rótulo

Si a los 7 días sigue en 0, retirar el `LIKE` sobre `title`. **Un fallback que nadie usa es sólo una
segunda definición esperando a derivar** — la sexta instancia, servida.

*(Implementado como racha medida, no como fecha anotada: una fecha en un reporte es justo lo que
nadie vuelve a mirar.)*

## 5. Lo que sigue

Con 2A cerrado la fontanería está sana, y **nada de eso era lo que se pidió**. Lo que se pidió fue
que el sistema analice, cree items y se autocomplete en ciclo. La fontanería era **condición previa,
no un desvío**: un generador nuevo alimentando una tubería muda y derivada habría producido más items
de los que nadie podía ver ni despachar. Ahora hay dónde ponerlos.

→ **Fase 2B**, con su Paso 0 obligatorio: medir los `module.json` antes de escribir el detector.
Ver [`directiva-2b.md`](directiva-2b.md).
