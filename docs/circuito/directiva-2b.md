# Directiva 2B — el generador arranca por su propio sustrato

> **Fecha:** 2026-08-18 · **Autor:** Irving (vía Cowork) · **Estado:** implementada
> (`c1aa78fd`, detectores 1–3 y 5 del orden; 4 pendiente por decisión).

## 1. La trampa de diseño, y el nombre corregido

La §5 de `circuito-fase2a.md` llamaba al hallazgo `spec_endpoint_no_implementado`. **Eso presupone
la dirección del error**, y los datos del Paso 0 muestran que en Flotas y Planes es al revés: el
código existe y la *declaración* envejeció.

Son dos cosas distintas: **detectar la discrepancia con certeza** no es **saber qué falta**. El
lookup da lo primero con confianza 1.0 y lo segundo no lo da nunca.

> **Tipo renombrado a `spec_desalineada`** — «declaración y realidad no coinciden», con la primera
> pregunta del item siendo cuál de los dos lados se corrige.

Un detector que emite *"falta construir X"* cuando X existe con otro nombre no produce ruido:
produce **trabajo fabricado**, que es peor.

## 2. Por qué el detector semántico no era el siguiente paso

**117 endpoints declarados sobre 2,117 rutas atribuibles a un módulo = 5.5 %.** Aunque el detector
fuera perfecto, mediría un vigésimo del sistema:

- Permisos: 111/111 correctos → detector impecable que hoy encuentra **cero**.
- Rutas: 5 módulos con discrepancias, sobre 26 declarantes.
- Screens: 9/43 módulos. No hay con qué medir.

`medirContraSpec` semántico produciría dos docenas de items y **volvería a secarse**: el problema
original, retrasado dos semanas. La pregunta no era cómo escribir el detector, era **por qué el
sistema no tiene con qué medirse**.

## 3. El primer producto son huecos de DECLARACIÓN

11 módulos casi vacíos, 17 sin `api_endpoints`, 34 sin `screens`. No es un vacío de datos: es el gap
más grande y mejor documentado del sistema, y nadie lo había contado como tal.

| Tipo | Regla | Confianza | Medido |
|---|---|---|---:|
| `spec_modulo_sin_declarar` | módulo con rutas registradas y 0 `api_endpoints` | 1.0 | **15** |
| `spec_declaracion_incompleta` | declara < 30 % de sus rutas reales | 1.0 | **21** |
| `spec_desalineada` | declarado ≠ registrado, **dirección desconocida** | 1.0 en la discrepancia, **0 en el diagnóstico** | **5** |
| `spec_permiso_inexistente` | permiso declarado ausente de `permissions` | 1.0 | **0** |

**41 gaps**, cada uno trazable a un conteo y no a un juicio.

Y tienen la propiedad que buscábamos toda la fase: **cada módulo que completa su `module.json`
amplía la superficie que el detector puede medir en la vuelta siguiente.** El generador se alimenta a
sí mismo — no por un truco de re-siembra, sino porque su primer trabajo es construir el instrumento
con el que va a medir después.

## 4. Métrica de convergencia

**Superficie declarada**, al digest (§5). Hoy **5.5 %**. Mientras suba, el generador tiene trabajo;
cuando se acerque a su techo, el detector semántico sobre `screens[].steps/actions` ya tendrá contra
qué medir y ahí sí valdrá la pena el juicio del modelo.

Las **133 rutas de controllers legacy fuera de `app/Modules`** quedan fuera del denominador: no
pertenecen a ningún manifiesto y no pueden declararse por esta vía. Es el techo honesto — meterlas
haría la métrica inalcanzable, y una métrica con techo imposible se deja de mirar.

## 5. Orden acordado

1. ✅ `spec_modulo_sin_declarar` + `spec_declaracion_incompleta` — el volumen, 100 % mecánico.
2. ✅ Permisos — **validación de tubería con cero ruido**. Rinde 0 items hoy y eso es lo esperado:
   su valor es de **guardia contra regresiones**, no de generación. Escrito en el código para que
   nadie lea *"detector que no encuentra nada"* como *"detector roto"*.
3. ✅ Rutas, acotado a los declarantes, emitiendo `spec_desalineada` con la ambigüedad explícita.
4. ⏸️ **`screens`: no todavía.** Enriquecerlas es el trabajo que producen los items del punto 1.
   (`circuito.auditor.spec.detectores.sin_screens = false`.)
5. ✅ Superficie declarada al digest.

## 6. Las dos capas del spec (no confundirlas)

- **`module.json` → ESTRUCTURA.** Endpoints, permisos, pantallas. Vive con el código y se versiona
  con él. Es lo que `medirContraSpec()` mide hoy.
- **Item `[SPEC]` → INTENCIÓN.** Criterios de DoD en prosa que Irving escribe desde la Torre sin
  desplegar código. **Pendiente, no descartado:** hoy hay 0 items `[SPEC]`, así que no habría nada
  que leer, pero el hueco es legítimo. El docblock anterior lo daba como el *único* contrato; era el
  contrato equivocado para la estructura, no una mala idea para la intención.

## 7. Decisiones de implementación que no estaban en la directiva

- **Items acotados por tanda** (`cap_por_item` = 25). Pedirle a Mapas «declara 200 endpoints» no es
  una tarea, es un proyecto. La huella de dedup incluye el *tramo* (`api_endpoints#tN`), así que la
  vuelta siguiente pide la tanda siguiente; sin progreso la huella no cambia y no se re-crea —
  falla hacia el lado bueno.
- **Umbral 30 %, no 100 %.** `api_endpoints` describe el contrato público, no cada feed interno de
  datatable. El mejor módulo hoy está en 29 %.
- **Atribución por namespace del controlador**, exacta y mecánica.
