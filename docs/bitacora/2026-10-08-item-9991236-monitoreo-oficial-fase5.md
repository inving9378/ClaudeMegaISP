## 2026-10-08 18:30 — Monitoreo Oficial, Fase 5: análisis de documentos con IA

### Contexto

Continuación directa de la Fase 4 (mergeada hoy). Última pieza nombrada explícitamente en la
épica como "F5 IA" antes de notificaciones/UI/validación.

### Qué se construyó

- `DocumentAnalysisService`: manda el PDF recién subido (y, si existe, el anterior del mismo
  expediente) al adaptador de IA **compartido** del sistema
  (`App\Modules\Addons\IA\Services\IA::enviar()`) — el mismo patrón ya probado y en uso por
  `FleetDocumentOcrService` (Flotas). **Nada de cliente HTTP propio ni clave propia** — regla
  "servicios compartidos únicos" de `CLAUDE.md`.
- La clave ante el Hub (`config('monitoreo.service_key')`) es la MISMA que `CapabilityDetector`
  ya usaba desde la Fase 1 para decidir si el indicador de "IA" sale disponible o sin configurar
  — se encienden juntos, nunca por separado.
- Cuando hay un documento anterior, se le mandan **los dos PDFs** a la IA para que compare de
  verdad: resumen del más reciente, si el cambio es de fondo (no solo de formato/escaneo), y qué
  cambió exactamente.
- `AnalyzeCaseDocumentJob` (en cola, no inline) se dispara automático al subir un documento
  genuinamente nuevo (la Fase 3 ya descarta los idénticos). Botón manual "Analizar ahora"/
  "Re-analizar" por documento (permiso `monitoring.run_manual`, declarado desde la Fase 1 sin
  usar hasta ahora).
- Si la IA no está configurada o la llamada falla, el documento queda con el error guardado,
  legible — **nunca un resumen inventado**.

### Verificación — con una llamada real a un proveedor de IA de verdad

No se validó solo el camino "sin configurar": se asignó temporalmente el módulo a un proveedor
real del Hub (y se retiró la asignación al terminar, dejando el módulo de vuelta en
`sin_config` — esa decisión le toca a Irving en Integraciones, no a esta sesión).

1. **Camino sin configurar** (estado real de hoy): se subió un PDF, el análisis quedó con el
   mensaje exacto `"monitoreo-oficial" no tiene una IA asignada. Configúralo en Integraciones →
   Módulos IA.` — claro, sin romper nada, con botón para reintentar.
2. **Camino real, con contenido real** (dos PDFs de prueba construidos a mano, con texto legible
   simulando una resolución del IMPI — ninguno con datos de un expediente real): primer intento
   contra Claude falló por saldo agotado de esa cuenta (hallazgo ya documentado antes en
   `project_megavoz_plan_atencion_clientes` — no es nuevo); se cambió la asignación de prueba a
   OpenAI (`gpt-4o-mini`) y la comparación salió **correcta de verdad**: *"El documento más
   reciente indica que la marca MEGANETMX TE CONECTA ha obtenido una resolución favorable y su
   registro ha sido renovado... Cambio relevante: El estatus ha cambiado de 'EN TRAMITE -
   declaración de uso presentada' a 'RESOLUCIÓN FAVORABLE - registro renovado'."* — exactamente
   lo que decían los dos PDFs de prueba, sin inventar nada de más ni de menos.

Datos de prueba (expedientes, documentos, usuario, y la asignación de IA temporal) borrados al
terminar — confirmado por conteo en cero.

### Pendiente (fases siguientes de la épica, fuera de esta Fase 5)

F6 notificaciones (avisar por correo/WhatsApp/campana cuando `ai_has_relevant_change=true`), F7
UI, F8 validación final. También sigue pendiente, sin decidir, si algún día se construyen
adaptadores de descarga automática para fuentes no-manuales (SIGA/VIDOC/gaceta/url) — decisión
aparte, más sensible, no resuelta en esta fase.

### Estado

Mergeado directo a `main` (mismo patrón manual del resto del día).
