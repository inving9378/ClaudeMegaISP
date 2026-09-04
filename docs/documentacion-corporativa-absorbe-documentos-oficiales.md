# Decisión — DocumentosOficiales queda absorbido por DocumentacionCorporativa (2026-08-28)

**Decisión de Irving, 2026-08-28.** El addon planeado `DocumentosOficiales` (antiguo
item #795, renumerado fuera de la Hoja de Ruta actual — el máximo id vigente era 661)
**NO se construye como addon aparte**. Su dominio queda absorbido por el item **#664**
(DocumentaciónCorporativa — Fase 2: repositorio documental, bandeja de captura y
plantillas), que depende de #662 (Fase 0: cimiento del addon).

## Por qué

`DocumentosOficiales` iba a ser un repositorio versionado de actas, poderes, cédulas y
contratos marco (tablas `docof_*`, permisos `documentos_oficiales.*`). Ese dominio es
un **subconjunto estricto** de lo que ya cubre la Fase 2 de DocumentacionCorporativa:
carga de documentos con versionado inmutable + hash SHA-256, bandeja de pendientes,
registros estructurados de actas/poderes/contratos, con alertas de vencimiento.
Construir los dos habría duplicado el servicio de versionado — el punto único de
verdad que no debe existir dos veces.

## Qué sigue vigente del análisis del item #840

`docs/analisis-modulo-plantillas-item-840.md` sigue siendo la referencia correcta para
la otra mitad del dominio: la **generación** de documentos (contratos por
cliente/CRM) permanece en `document_templates` + `DocumentTypeTemplate` +
`DocumentTemplateService` + dompdf (lo que el análisis llama "1.a"). Esa conclusión
—NO unificar generación de plantillas con repositorio de archivos— se respeta tal
cual: el item #664 **consume** `document_templates` para sus plantillas nuevas
(organigrama corporativo, estructura accionaria, relación de activos y pasivos,
carátula de expediente), no lo reemplaza ni lo duplica.

Lo que **no** sigue vigente de aquel análisis es la premisa de que `DocumentosOficiales`
(entonces "1.b") seguiría su curso como addon separado (conclusión §4.2 de ese
documento) — esa parte queda **reemplazada** por esta decisión: su dominio (repositorio
versionado de actas/poderes/contratos) se levanta dentro de DocumentacionCorporativa
con prefijo `dc_`, no `docof_`.

## Ramas huérfanas — pendientes de que Irving decida borrarlas

Dos ramas con **cero commits** quedaron del plan original y siguen en el repo:

- `circuito/item-795-documentos-oficiales`
- `circuito/item-795-documentos-oficiales-parte-a-reposito`

Verificado (item #668, Paso 0): `git log --oneline main..<rama>` devuelve vacío en
ambas — no hay nada que perder si se borran. El circuito **no las borra**; Irving
decide cuándo. No hay código bajo `app/Modules/Addons/DocumentosOficiales/` ni tablas
`docof_*` en ningún entorno.

## Para la próxima sesión que toque este dominio

- No volver a proponer un addon `DocumentosOficiales`. El trabajo vive en el item #664
  (y sus fases hermanas #662-#667 de DocumentacionCorporativa).
- Si se retoma la generación de documentos, seguir usando `document_templates` +
  `DocumentTemplateService` + dompdf — no crear un motor paralelo.
- Prefijo de tablas del addon nuevo: `dc_`, nunca `docof_`. Permisos:
  `documentacion_corporativa.*` (patrón del module.json de #662), nunca
  `documentos_oficiales.*`.
