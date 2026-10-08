## 2026-10-07 — JARVIS (robotsito): memoria persistente, instrucciones, guía del sistema y herramientas

Rama: `feature/jarvis-asistente-memoria` (worktree `/home/meganet/megaisp-wt-jarvis`). Sin item de la
Hoja de Ruta, por decisión del equipo (rama aparte normal).

**Qué se hizo** (sobre el agente que ya existía, `IaChatFloat.vue` + `IAChatController`):
- Conocimiento en archivos `resources/asistente/` (MD + JSON): instrucciones sin lenguaje técnico,
  mapa del sistema, guías por tema, enlaces permitidos, límites por herramienta, ajustes de memoria.
- 12 herramientas con el permiso de su pantalla (`app/Services/Asistente/Herramientas/`).
- Cambios (actualizar contacto, crear ticket) solo con el "sí" del usuario, validado por el servidor;
  bitácora `activity_log` con `log_name='jarvis'`.
- Memoria en BD (4 tablas `asistente_*`), autocompactado por tokens reales y recuerdos por usuario.
- Robotsito: carga la plática al abrir, tarjeta de confirmación, enlaces directos, HTML escapado,
  movido a `right:154px` para no tapar la burbuja de la Torre. Ficha del cliente acepta `?tab=` de
  cualquier pestaña.

**Migración:** `2026_10_07_120000_create_asistente_jarvis_tables` ya corrida en la BD de dev con
`--path` + `--force-uncommitted` (la rama no está ligada a un item, el candado lo exige). Al mergear a
main ya aparece como `Ran`.

**Verificado en dev** (usuario de pruebas `circuito_test_126`, IA OpenAI gpt-4o-mini):
- Consultas reales (cliente 4010): búsqueda, ficha, estadísticas de pago, facturas, tickets, resumen de
  tickets y cobranza del día. Todas en menos de 100 ms.
- Conversación de 3 preguntas con memoria entre ellas.
- Cambio con confirmación sobre el cliente de prueba 6810 (teléfono 3) → "sí" → aplicado + bitácora
  "Vía JARVIS"; teléfono restaurado a `null` después. Ticket preparado → "no" → 0 tickets creados.
- Autocompactado forzado: 20 → 12 mensajes vivos, resumen correcto, recuerdo guardado, y respondió
  bien una pregunta sobre lo ya resumido.
- Filtro de permisos: rol client solo recibe guía, manual y recordar; técnico no recibe cobranza del día.
- `npm run dev` compila en la rama.

**Pendiente:**
- Tras mergear: `npm run prod` en `/var/www/megaisp` (el bundle lo sirve el checkout principal).
- Probar en el navegador (abrir el robotsito, confirmar un cambio, clic en un enlace).
- Datos de prueba que quedaron: conversaciones/acciones del usuario 4875 en `asistente_*` (inofensivas).
- Siguientes capacidades: más acciones (prospectos CRM, agendar tareas, notas), pantalla "Lo que
  recuerdo de ti" y gasto por usuario.
