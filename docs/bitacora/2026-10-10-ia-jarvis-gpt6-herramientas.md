## 2026-10-10 — JARVIS con gpt-6: "No pude responder en este momento"

**Síntoma.** Con `gpt-6-luna` asignado a JARVIS (Integraciones → Módulos IA, clave
`asistente.chat_flotante`), el robotsito contestaba "No pude responder en este momento". Pero el
botón "Probar" del módulo decía "Funciona".

**Causa (log `[JARVIS] fallo al responder`).** JARVIS conversa con herramientas (function calling).
OpenAI rechaza herramientas con los gpt-6 en `/v1/chat/completions`:
`Function tools with reasoning_effort are not supported for gpt-6-luna … use /v1/responses or set
reasoning_effort to 'none'`. Este es otro problema que el de ayer (`max_tokens`/`temperature`,
`docs/bitacora/2026-10-09-ia-modelos-gpt6.md`), que ya estaba resuelto.

Medido contra la API real (llave del Hub, integración 3):

| Modelo | chat/completions + tools | + `reasoning_effort: none` | `/v1/responses` + tools |
|---|---|---|---|
| gpt-6-luna, gpt-6-sol | 400 | 200 | 200 |
| gpt-6-astra, gpt-6.1-sol | 400 | 400 (no acepta `none`) | 200 |
| gpt-5, gpt-5-mini | 200 | 400 | 200 |
| gpt-4o-mini | 200 | 400 | — |

El "Probar" mentía porque mandaba un mensaje simple, sin herramientas.

**Cambio.**
- `OpenAIAdaptador::conversarConHerramientas()` usa `/v1/responses` con estas tres condiciones a la vez:
  - hay herramientas;
  - el driver es `openai` real, no `openai_compatible` (Ollama, DeepSeek y Groq no tienen esa ruta);
  - el modelo es de razonamiento (o-series, gpt-5 o posterior).

  La llamada lleva `reasoning.effort = low` (se puede cambiar con `config_extra.reasoning_effort` del proveedor) y `strict: false` en las herramientas. Devuelve el mismo formato neutro que antes. gpt-4o y los compatibles siguen por `chat/completions` sin cambios.
- `IaModulosController::probar()`: los módulos que requieren herramientas (JARVIS y el agente de WhatsApp) ahora se prueban con una herramienta.

**Verificado.**
- **JARVIS real** (`AsistenteService::responder`, usuario 3841, transacción con rollback): respondió bien con gpt-6-luna, gpt-6-sol, gpt-6-astra, gpt-6.1-sol, gpt-5-mini y gpt-4o-mini.
  - "Busca al cliente Francisco Aguilar" → cliente 6810, encontrado con la herramienta.
  - "¿Cuántos pagos hoy?" → 0 pagos.
  - Tardó entre 2 y 6 s por respuesta.
- **Botón Probar:** OK con los 5 modelos, también con el agente de WhatsApp y con el changelog.
- **Botón Probar con el adaptador viejo:** ahora sí marca el error, cuando antes decía "Funciona".
- **Asignación:** intacta tras el rollback (`gpt-6-luna`).
- **Código en vivo:** el sitio toma el cambio solo, porque opcache revalida con los valores por defecto. Los workers de cola necesitan `queue:restart` solo si el agente de WhatsApp pasa a un gpt-5/6.
- **Versión:** la V1.55 salió ayer sin este arreglo; entra en la siguiente.
