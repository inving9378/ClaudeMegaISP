## 2026-10-09 — Módulos IA: los modelos gpt-6 (gpt-6-luna, gpt-6.1-sol…) ya funcionan

**Síntoma:** en Integraciones → Módulos IA, al poner `gpt-6-luna` la función no respondía y la
pantalla decía "El proveedor no acepta ese tipo de archivo o de petición".

**Causa:** `OpenAIAdaptador::aplicarLimites()` solo reconocía como modelos nuevos los `o1/o3/o4…` y
`gpt-5…` (`/^(o\d|gpt-5)/i`). A gpt-6 le mandaba `max_tokens` + `temperature`, y OpenAI responde 400:
*Unsupported parameter: 'max_tokens'… Use 'max_completion_tokens'* y *'temperature' does not support
0.2… Only the default (1)*. El modelo existe y la llave del Hub sí tiene acceso
(gpt-6-astra, gpt-6-luna, gpt-6-sol, gpt-6.1-sol).

**Cambio:**
- Regex → `/^(o\d|gpt-([5-9]|\d{2,}))/i`: gpt-5 en adelante usa `max_completion_tokens` y sin temperatura.
  gpt-4o/4.1/3.5 y los compatibles (Ollama…) quedan igual.
- `IA::explicarError()`: nuevo motivo `parametro` ("Unsupported parameter/value") antes de `formato`,
  para no culpar al proveedor cuando es un parámetro que el modelo no acepta.

**Verificado:** `IA::enviar('releases.changelog', …)` dentro de transacción con rollback, con
gpt-6-luna, gpt-6.1-sol y gpt-4o-mini → los tres responden "OK" (asignación intacta tras el rollback).
Los workers de cola cargan el código nuevo hasta un `queue:restart`.
