# Item #623 — Seguimiento de la pregunta sin resolver de #277 (RESUELTO — ya respondida en main)

## Contexto

El item #277 («Clientes: cerrar 2 marcador(es) TODO/FIXME en `ClientController.php`») se cerró
con 1 pregunta que requería decisión de Irving y quedó sin `opcion_elegida` marcada en el registro
del roadmap:

- `app/Modules/Core/Clientes/Controllers/ClientController.php`
  - L111 `[TODO] Quitar despues de la primera importacion`
  - L514 `[TODO] pedido por irving quitar despues`

El item #623 nació como seguimiento automático de esa pregunta huérfana (ver `log` del item,
evento `item_creado`, "Auto-generado al cerrar el padre con preguntas sin resolver").

## Verificación

`grep -n "TODO\|FIXME" app/Modules/Core/Clientes/Controllers/ClientController.php` no devuelve
ningún resultado hoy. El archivo ya no tiene esos 2 marcadores.

`git log` sobre el archivo muestra el commit `e3bf7886` ("fix(clientes): cierra 2 marcadores TODO
obsoletos en ClientController", 2026-08-26 15:37, autor Irving MegaISP), **ya en `main`**
(`git merge-base --is-ancestor e3bf7886 main` → sí es ancestro). El mensaje del commit responde
exactamente la pregunta que #623 traía pendiente:

- **L111**: el flag `import` de `store()` NO es código de una importación única — es el mecanismo
  permanente de SmartImport (`Client` está en `ModuleRepository::MODULES_FOR_IMPORT`, mismo patrón
  que `VozController`/`CustomController`/`BundleController`). El TODO mentía.
- **L514**: `editBalance()` NO es código temporal — es una feature activa consumida por
  `UpdateBalance.vue` y gateada por el permiso real `client_edit_balance`, ya integrado en el
  editor de roles. El TODO mentía.

Se borraron ambos comentarios sin tocar la lógica (cero cambio de comportamiento; diff de 1
inserción/2 eliminaciones, solo comentarios).

## Conclusión

La pregunta que #623 traía pendiente **ya fue decidida y ejecutada directamente en main** antes de
que este item llegara a ejecución (el commit está fechado el mismo día en que Thomas generó el
item de seguimiento). No hay nada que implementar: el estado actual del código YA refleja la
decisión de Irving documentada en el propio mensaje del commit `e3bf7886`.

Sin cambio de código en esta vuelta — solo este documento de verificación y el cierre del item.
