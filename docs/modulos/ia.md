# Módulo IA

> Asistente IA interno multi-proveedor (Claude/OpenAI/Gemini) + registro central de credenciales IA del sistema. `app/Modules/Addons/IA/` · slug `addon-ia` · addon activo.

## 0. En simple
Es el chat de inteligencia artificial que usa el equipo interno para hacer preguntas, organizar proyectos de trabajo y guardar prompts reutilizables, y también el lugar donde se guardan las llaves de acceso a Claude/OpenAI/Gemini que otras partes del sistema usan.

## 1. Qué es
Módulo que provee (a) un asistente de chat IA multi-proveedor con historial, proyectos, tareas, notas, memoria persistente y prompts guardados, y (b) el catálogo `ia_proveedores` — el registro único de credenciales/API keys de IA (Claude, OpenAI, Gemini) que consumen otros módulos del sistema.

## 2. Para qué sirve
Le da al staff interno un asistente conversacional dentro del panel (sin salir a una app externa) para resolver dudas, mantener contexto de proyectos de trabajo y reutilizar prompts frecuentes. Además centraliza la configuración de proveedores de IA para que **cualquier módulo del sistema** que necesite hablar con un LLM lo haga a través de esta única fuente de credenciales, en vez de guardar su propia API key (regla de "servicios compartidos únicos" del proyecto).

⚠️ **No confundir con `IaChatFloat`** (`resources/js/components/IaChatFloat.vue` + `App\Http\Controllers\IA\IAChatController`, fuera de este módulo): es el widget flotante de ayuda que lee `ModuleRegistry::getAiContext()` y usa `ClaudeApiClient` (servicio de Marketing) directamente — un chat de orientación read-only, separado del asistente completo documentado aquí.

## 3. Cómo funciona
- **Tablas/modelos:** `ia_proveedores` (`IAProveedor` — driver, `api_key`, `modelo_default`, `endpoint_url`, `soporta_imagenes`, `activo`, `estado`/`ultimo_error`/`probado_at`), `ia_proyectos` (`IAProyecto`, agrupa conversaciones, uno puede ser `es_default`), `ia_conversaciones` (`IAConversacion`, ligada a proveedor+proyecto), `ia_mensajes` (`IAMensaje`, rol user/assistant + tokens), `ia_message_files` (`IAMessageFile`), `ia_tareas` (`IATarea`), `ia_notas_proyecto` (`IANotaProyecto`), `ia_memoria_proyecto` (`IAMemoriaProyecto`, hechos persistentes **globales del proyecto**, no por usuario), `ia_prompts_usuario` (`IAPromptUsuario`, biblioteca de prompts guardados), `ia_sesiones_trabajo` (`IASesionTrabajo`), `ia_uso_tokens` (`IAUsoToken`, consumo de tokens para costeo).
- **Adaptadores por proveedor:** `IAAdaptadorFactory::crear($proveedor)` resuelve la clase adaptadora según `driver` (`claude`→`ClaudeAdaptador`, `openai`/`openai_compatible`→`OpenAIAdaptador`, `gemini`→`GeminiAdaptador`), todas implementando `IAAdaptadorInterface`. Agregar un proveedor nuevo = registrar su clase en el mapa, sin tocar el resto.
- **Flujo principal del chat:** `POST /ia/conversaciones/{id}/enviar` → `IAChatController::enviar()` → `IAProveedorService::enviarMensaje()`: valida imágenes (máx. 20, 5MB c/u, mimes jpeg/png/gif/webp), arma el historial de la conversación, obtiene el `system prompt` completo desde `ContextoProyectoService::buildSystemPrompt()` (ensambla contexto de git, estructura de archivos, BD, módulos activos y sesión de trabajo — cacheado 30s por usuario), llama al adaptador del proveedor, persiste mensaje de usuario y respuesta, registra uso de tokens (`IAPricingService`), actualiza estado del proveedor (`conectado`/`error`) y, cada `EXTRAER_MEMORIA_CADA=10` mensajes, dispara extracción de hechos hacia `ia_memoria_proyecto` (`MemoriaService::extraerHechos`, best-effort — nunca rompe la conversación si falla).
- **Memoria persistente:** `MemoriaService` usa el primer proveedor activo con API key para extraer hechos clave de la conversación reciente (JSON vía IA), construye el bloque de contexto (`construirContexto()`, máx. 20 hechos vigentes) inyectado en el system prompt de conversaciones nuevas, y puede marcar hechos obsoletos/eliminarlos (`limpiarObsoletos`/`limpiarAntiguos`, también con ayuda de IA para detectar contradicciones). Es memoria **global del proyecto**, no segmentada por usuario.
- **Proyectos/tareas/notas/sesiones:** CRUDs simples (`IAProyectoController`, `IATareaController`, `IANotaController`, `IASesionController`) para organizar el trabajo alrededor de conversaciones; las sesiones de trabajo (`IASesionTrabajo`) se abren automáticamente al enviar un mensaje si no hay una abierta (`SesionTrabajoService::abrirSiHaceFalta`).
- **Prompts guardados:** `IAPromptUsuario` + `IAPromptUsuarioController` (listar/usar/CRUD) — biblioteca reutilizable de prompts, separada del catálogo de proveedores.
- **Configuración/uso:** `IAConfiguracionController` sirve la vista de configuración general y el dashboard de uso de tokens (`ia_uso_tokens`); `IAProveedorController` expone el CRUD de proveedores (alta/edición/borrado/prueba de conexión/activar-desactivar), gateado a permisos de administración.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Vistas** (permiso `ia_view_chat`/`ia_view_prompts`/`ia_manage_*`, prefijo `/ia`): `GET /ia` (chat), `/ia/historial` (+ `/ia/historial/tabla`), `/ia/prompts`, `/ia/configuracion` (+ `/ia/configuracion/uso-tokens`).
- **API REST interna** bajo `/ia/*`: `conversaciones` (index/show/store/update/destroy, permisos split por acción), `proyectos`, `tareas` (+ `completar/{id}`), `notas`, `memoria` (+ `limpiar-antiguos`/`limpiar-obsoletos`), `sesiones` (abrir/cerrar), `prompts` (listar/usar/CRUD), `proveedores` (CRUD + `probar/{id}` + `toggle-activo/{id}`).
- **Endpoint clave para el resto del sistema:** `POST /ia/conversaciones/{id}/enviar` (permiso `ia_add_chat`) — enviar mensaje y recibir respuesta del asistente.
- **12 permisos** Spatie (`ia_view_chat`, `ia_add_chat`, `ia_edit_chat`, `ia_delete_chat`, `ia_view_prompts`, `ia_manage_prompts`, `ia_manage_proyectos`, `ia_manage_tareas`, `ia_manage_notas`, `ia_manage_memoria`, `ia_manage_sesiones`, `ia_manage_proveedores`).
- **Tabla `ia_proveedores` como servicio compartido único**: el resto del sistema resuelve sus credenciales de IA leyendo esta tabla en vez de duplicar API keys — consumida directamente por `WhatsAppAgent\Services\WhatsAppIAService` (agente conversacional de WhatsApp), `Manual\Services\ManualGeneratorService` (generación de manuales con IA) y `SmartImportExport\Services\SmartImportService` (asistencia IA en importaciones), todos vía `IAProveedor::where('driver', 'claude')`/`activo` + `IAAdaptadorFactory::crear()`.
- Declarado en `config_sections` del `module.json` como el punto de configuración de "IA — Proveedores y API Keys" (visible en `/configuracion`).

**Consume**
- **APIs externas de los proveedores** (Claude/Anthropic, OpenAI, Gemini) vía los adaptadores (`Services/Adaptadores/*`), usando la `api_key`/`endpoint_url` guardada en `ia_proveedores` — nunca hardcodeada, sin secretos en código.
- **Contexto interno del sistema** para armar el system prompt: `GitContextoService` (estado del repo), `EstructuraContextoService` (árbol de archivos), `BaseDatosContextoService` (esquema/datos), `ModulosContextoService` (módulos activos del `ModuleRegistry`) y `SesionTrabajoService`.
- **`BaseModel`** — todos los modelos propios (excepto `IAMemoriaProyecto`, que es `Model` plano) heredan el auto-stamp `created_by`/`updated_by` estándar del proyecto.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
