# Mapa de clientes Claude y orígenes de clave (item Roadmap #214)

FASE 1 (read-only) del item #214: documentar quién usa `ClaudeAdaptador` (módulo IA) vs
`ClaudeApiClient` (Marketing) y de dónde saca la API key cada uno. Sin cambios de código —
insumo para que Irving decida el stack/origen canónico (FASES 2-4, `nivel_riesgo: B`,
requieren su decisión y no se ejecutan aquí).

## Los dos clientes

| Cliente | Ubicación | Origen de la API key |
|---|---|---|
| `ClaudeAdaptador` | `app/Modules/Addons/IA/Services/Adaptadores/ClaudeAdaptador.php` | **Solo** `IAProveedor->api_key` (tabla `ia_proveedores`). Sin fallback a Hub/`.env`. |
| `ClaudeApiClient` | `app/Modules/Addons/Marketing/Services/ClaudeApiClient.php` (trait `UsesApiIntegration`) | Cascada: **Hub** (`api_integrations`, provider `anthropic`) → `.env` (`CLAUDE_API_KEY`) → `marketing_settings` (`claude_api_key`). |

Rotar la key en el Hub de Integraciones (`/integraciones`) **no afecta** a nada que use
`ClaudeAdaptador`, y viceversa: cambiar `ia_proveedores.api_key` no afecta a nada que use
`ClaudeApiClient`.

## Consumidores de `ClaudeAdaptador` (vía `IAAdaptadorFactory`)

Origen de clave: **`ia_proveedores.api_key`** (BD, sin Hub).

| Módulo | Archivo |
|---|---|
| Manual (generador de manuales) | `app/Modules/Addons/Manual/Services/ManualGeneratorService.php` |
| IA (memoria de conversación) | `app/Modules/Addons/IA/Services/MemoriaService.php` |
| SmartImportExport | `app/Modules/Addons/SmartImportExport/Services/SmartImportService.php` |
| IA (admin del proveedor) | `app/Modules/Addons/IA/Services/IAProveedorService.php`, `app/Modules/Addons/IA/Controllers/IAProveedorController.php` |

## Consumidores de `ClaudeApiClient` (vía `UsesApiIntegration`)

Origen de clave: **Hub de Integraciones** (`api_integrations`, provider `anthropic`), con
fallback a `.env`/`marketing_settings`.

| Módulo | Archivo | Notas |
|---|---|---|
| IA — **Chat flotante** (`IaChatFloat.vue`) | `app/Http/Controllers/IA/IAChatController.php` | ⚠️ El chat IA (dueño designado = módulo IA) en realidad ya usa el cliente de Marketing/Hub, NO `ClaudeAdaptador`. |
| Roadmap (revisor del Circuito) | `app/Modules/Addons/Roadmap/Services/RevisorService.php` | |
| Deploy (changelog de releases) | `app/Services/ReleaseChangelogService.php` | |
| Marketing (Director Creativo) | `app/Modules/Addons/Marketing/Services/Personalization/CreativeDirectorService.php` | |
| Payments (extracción de comprobantes SPEI) | `app/Modules/Addons/Payments/Services/Extraction/PaymentReceiptExtractor.php` | |
| Talento (inspección de caja) | `app/Modules/Addons/Talento/Services/CajaInspectionService.php` | |
| Talento (validación de campo IA) | `app/Modules/Addons/Talento/Services/FieldIaValidationService.php` | |

## Lectura del mapa

- El stack **de facto** más usado (7 consumidores, incluido el propio chat IA) es
  `ClaudeApiClient`/Hub, aunque el "dueño designado" según `CLAUDE.md` (sección
  "servicios compartidos únicos") es el módulo IA.
- `ClaudeAdaptador` solo tiene 4 consumidores y ninguno es el chat IA en sí — es usado por
  features que llaman al adaptador genérico (`IAAdaptadorFactory`) pensando en soportar
  múltiples proveedores (no solo Claude), mientras que `ClaudeApiClient` es Claude-específico.
- Esto confirma la fragmentación descrita en el item: **dos** orígenes de clave viven en
  paralelo y rotar uno no rota el otro.

## Pendiente (fuera de alcance de esta FASE 1)

FASES 2-4 del item #214 — elegir el stack/origen canónico, migrar los consumidores y rotar
la key — quedan **pendientes de decisión de Irving** (nivel de riesgo B: decisión de
arquitectura + rotación de credencial compartida por 3 flujos productivos). Ver
`comentarios_claude` del item #214 en la Hoja de Ruta para las opciones ya evaluadas.
