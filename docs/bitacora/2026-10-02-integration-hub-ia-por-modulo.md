# Bitácora — Integration Hub: tipos IA/Servicios, catálogo de proveedores e IA por módulo

## 2026-10-02 11:07 — Rama `integrations-changes` lista para main (fases 1, 2 y pestaña "Módulos IA")

**Pedido de Irving:** quitar la dependencia directa de Claude en los módulos (WhatsApp, CRM,
Marketing, bots, etc.) para poder decir en cualquier momento "este módulo que usaba Claude ahora
usa OpenAI / Gemini / Ollama" sin que nada se rompa. Claude queda como una opción más del
catálogo (borrable). **El Circuito CC no se toca** (vuelta.sh, CLI `claude` por OAuth y los
services de Roadmap: Revisor, ValvulaContexto, ResumenNatural, asesor de cobranza).

### Qué quedó
- **Integration Hub (`/integraciones`)**
  - `api_integrations.type` (`ia` | `servicios`), heredado del proveedor; lo fija
    `ApiIntegration::booted()` en cualquier vía de alta (UI, seeders, tinker).
  - Tabla `api_integration_providers` + pestaña **Proveedores** (CRUD). Los 6 de siempre
    quedan `is_system` (no se borran, sí se desactivan). Un proveedor desactivado no esconde
    sus integraciones. Un proveedor borrado se puede recrear con el mismo identificador.
  - Proveedores de IA llevan **protocolo** (`driver`: claude / openai / openai_compatible /
    gemini) y si leen **imágenes / PDF**.
  - Pestaña **Módulos IA**: cada módulo elige una integración de IA del Hub + modelo.
    **Sin asignación el módulo no usa IA** (decisión de Irving: nada funciona hasta configurar).
  - Avisos con `toastr` arriba a la derecha.
  - Validar una integración de un proveedor sin validador ya no la marca "Válida".
- **Módulo IA**
  - `IA::enviar('<clave>', ...)` / `IA::proveedorPara()` resuelven desde el Hub; sin
    asignación lanzan `IANoConfigurada` con mensaje para el usuario. Registra uso y costo en
    la integración del Hub. `IA::json()` lee JSON de cualquier proveedor.
  - Catálogo de 20 módulos en `app/Modules/Addons/IA/config/modulos.php`
    (`config('ia_modulos')`). ⚠️ Las claves llevan punto: leer con `IA::modulo($clave)`,
    nunca `config("ia_modulos.$clave")`.
  - Adaptadores sobre `AdaptadorHttpBase`: reintentos 429/5xx (misma política que
    `ClaudeApiClient`), opciones por llamada (max_tokens, temperatura, timeout, json),
    **OpenAI lee PDF**, Gemini con la llave en header, resultado con `fin`.
    Payload de Claude/OpenAI verificado idéntico al anterior (los bots vivos no cambian).

### Hallazgo grave
La llave de Claude (la misma en Hub, `.env` y `ia_proveedores`) está **sin crédito desde
2026-09-26**: 0 llamadas exitosas y ~2,880 fallos al día (algo reintenta ~2/min, sin
identificar). Todo lo que usa Claude lleva una semana caído. Pagos y Flotas no leen PDF porque
su código solo acepta PDF con Claude.

### Pendiente (Fase 3)
- Conectar cada consumidor a `IA::enviar('<clave>')` y marcar `listo => true` en el catálogo.
  Mientras tanto la pantalla dice "Asignada · el módulo aún no la usa".
- Pagos con confirmación de Irving (dinero). `marketing.agente_whatsapp` necesita herramientas
  en formato neutro.
- `gaistudio` (proveedor creado por Irving) no tiene protocolo: editarlo y elegir Gemini.

### Notas operativas
- Migraciones `2026_10_02_100000`, `110000`, `120000` (módulo IA) y `130000` se aplicaron en
  dev con `--force-uncommitted` (guardrail #534, OK de Irving); quedan rastreables al mergear.
- El checkout `/var/www/megaisp` lo cambió otra sesión a `main` a las 07:54; el trabajo siguió
  en el worktree `/home/meganet/megaisp-wt-integrations`. Se recompiló el frontend de main en
  modo prod (quedaba un build dev de esta rama).
- `CLAUDE.md` dice que el admin es el usuario 3986: en dev ese id hoy es una cuenta cliente
  (`Meganet8f3d7255`). Resolver cuentas por rol/login, no por id.

Commits: `013ac08b`, `08177971`, `b73b69af`, `5e8f790d`, `26474c8e`, `b492a58d`, `027f42ad`,
`a2cacd22`, `53c8b6f5`.
