# Item #9 — "Conectar chat IA al registry (knowledge/actions)" (VERIFICACIÓN — ya resuelto)

## Pedido del item

> Conectar `IaChatFloat.vue` al `ModuleRegistry` para que consuma `ai.knowledge` y `ai.actions`
> declarados por cada módulo.

## Hallazgo

El pedido, tal como está escrito, **ya está resuelto** por dos commits previos a este item:

1. **Fase 1** (`9ddb0ab6`, "#9 Fase 1 — controller del chat IA con contexto dinámico de módulos"):
   `IAChatController::buildSystemPrompt()` (`app/Http/Controllers/IA/IAChatController.php:111-144`)
   recorre `ModuleRegistry::getAiContext()` e inyecta al system prompt de Claude el `knowledge` y las
   `actions` (descripción + endpoint) de cada módulo activo. Explícitamente **read-only**: informa/
   orienta, no ejecuta nada (la ejecución vía tool-calling quedó separada en el item #171, "infra de
   auditoria para tool-calling", commit `3b454546`).
2. **Fase 3** (`7884e702`, "chat IA flotante Fase 3 — suggestions dinámicas + renderers genéricos"):
   `IAChatController::suggestions()` arma los chips de bienvenida tomando `example_intents` de cada
   módulo vía el mismo `ModuleRegistry::getAiContext()`; `IaChatFloat.vue`
   (`resources/js/components/ia/IaChatFloat.vue`) consume `GET /ia/suggestions` al montar y
   `POST /ia/chat` al enviar, con un renderer genérico (`describeAction`) que pinta cualquier
   `action_result` futuro por su FORMA sin hardcodear nombres de acción.

**Verificado en vivo (tinker, `ModuleRegistry::instance()->getAiContext()`):** 26 módulos activos
aportan contexto IA real (`knowledge` en los 26, `actions` en 3 — mapas/roadmap/vendedores,
`example_intents` en 25). El registry compila esto desde el bloque `"ai"` de cada `module.json`
(ver `app/Modules/Core/ModuleManager/Services/ModuleRegistry.php:70-74,178-181`), no hay nada
hardcodeado en el controller.

Rutas registradas y activas: `routes/web.php:230` (`POST /ia/chat`) y `:236`
(`GET /ia/suggestions`), ambas apuntando a `IAChatController`.

## Hallazgo colateral (fuera de alcance de este item, registrado aparte)

`IaChatFloat.vue` **no está montado en ningún layout** — no hay `import`/`app.component(...)` en
`resources/js/app.js`, ni un contenedor (`#ia-chat-root` o similar) en
`app/Modules/Core/Layout/views/master.blade.php`. Comparar con el patrón hermano `HelpFloat`
(`app.js:379,557,959-965`: import + registro + mini-app montada en `#help-float-root`, presente en
`master.blade.php:68`) — ese patrón nunca se replicó para el chat IA. Grep completo del repo
confirma cero consumidores de `IaChatFloat` fuera del propio archivo.

Esto es un gap real, pero **distinto** del pedido literal del item (que es sobre el cableado
knowledge/actions, ya resuelto). Montar el widget globalmente expone un botón de chat con costo de
API real por cada usuario que lo use — una decisión de visibilidad/costo que no estaba en el alcance
descrito y que merece su propio triaje (nivel de riesgo, quién lo ve, con o sin Fase 2 de
tool-calling ya lista). Se registra como sub-item nuevo del roadmap para que no se pierda.

## Conclusión

Sin cambio de código funcional — el cableado `IaChatFloat.vue` ↔ `ModuleRegistry` (knowledge +
actions) que pide el item ya existe, está en uso por las rutas `/ia/chat` y `/ia/suggestions`, y se
verificó en vivo. Lo único pendiente (montar el widget en el layout) queda fuera de este item y
pasa a un sub-item propio.
