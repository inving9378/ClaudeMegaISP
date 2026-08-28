# Item #224 — "Flotas: el OCR de documentos es un placeholder rotulado 'Fase 7'" (RESUELTO — premisa incorrecta)

## Lo que decía el item

> La UI muestra "Detección automática con IA (Fase 7)" sin implementación detrás.
> _(Detectado en el inventario de módulos del 2026-08-08.)_

El item traía una pregunta pendiente (`requiere_irving`): "¿Construimos ya el OCR de documentos
de Flotas conectándolo al módulo IA existente, o se queda como placeholder hasta una fase
posterior?"

## Lo que hay realmente en el código (dev, 2026-08-28)

El OCR **ya está construido, íntegro y en `main`**, del mismo día que el auditor detectó el
placeholder — commits `37c6afdd` + `80ca8e39` (2026-08-08 17:38, item **#580** "Flotas Fase 7"),
más el doc `a2c264b0` (2026-08-08 17:40). El escaneo del auditor corrió sobre un snapshot previo
a esa integración; el item #224 nació ya obsoleto.

Verificado en este worktree:

- **No queda ningún texto "Detección automática con IA (Fase 7)" en la UI** (`grep` en
  `resources/js/components/module/flotas/*.vue` sin resultados). Los únicos matches de "Fase 7"
  que quedan son comentarios de código (`#580, Fase 7`) que documentan el origen del feature, no
  texto visible al usuario.
- **Backend real:** `FleetDocumentOcrService` (`app/Modules/Addons/Flotas/Services/Ocr/`) lee el
  documento (imagen/PDF) y devuelve campos estructurados con confianza. Usa el **módulo IA
  compartido** (`IAAdaptadorFactory` + `ia_proveedores`) — **sin cliente HTTP ni API key
  propios**, tal como pide la convención "SERVICIOS COMPARTIDOS ÚNICOS" de `CLAUDE.md`. Nunca
  inventa (ilegible → `value=null`+`confidence=baja`), nunca lanza excepción hacia arriba (la
  subida del documento no se bloquea si la IA falla).
  Ruta: `POST /api/documentos/ocr` (`FleetDocumentController::ocr`, gate
  `fleet.documents.manage`, valida tenant del vehículo, guarda auditoría en
  `fleet_document_ocr_runs`).
- **Frontend:** `useFleetDocumentOcr.js` (composable compartido) + wiring en
  `FleetTabDocumentos.vue` — al adjuntar el archivo dispara la lectura, prellenar SOLO campos
  vacíos (nunca pisa lo que la persona ya escribió), badge de confianza, botón de "revisado"
  manual. La persona confirma siempre al guardar (la IA propone, no decide).
- **Migraciones aplicadas en dev:** `2026_08_08_160000_create_fleet_document_ocr_runs_table` y
  `2026_08_08_160100_add_ocr_columns_to_fleet_documents` (`migrate:status` → `Ran`, batch 540).

Esto responde la pregunta pendiente del item: la opción elegida (por el propio item #580, antes
de que #224 existiera) fue **construir el OCR real conectado al módulo IA existente** — la misma
opción que el propio brief de decisión de #224 recomendaba como plan B si Irving quería avanzar
ya. No hay nada que decidir ni que construir: ya está hecho, probado y en producción de código
(dev).

## Cambio en esta vuelta

Sin cambio de código funcional (nada que arreglar). Se actualizó la nota obsoleta en `CLAUDE.md`
("Notas / deuda Fase 2" de Flotas Fase 1) que todavía decía "placeholder … sin implementar", para
que no vuelva a generar un hallazgo fantasma en una futura auditoría del inventario de módulos.

## Dónde verlo en la UI

`/flotas/{id}` → pestaña **Documentos** → botón "Agregar documento" → adjuntar un PDF/imagen: se
dispara la lectura por IA y prellena los campos con badge de confianza.
