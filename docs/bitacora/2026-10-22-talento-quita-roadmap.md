## 2026-10-22 — Talento: se verifica el roadmap fase por fase y se retira

Pedido: "quita el roadmap de talento, pero antes de quitarlo verifica que todos los pasos estén
cumplidos y como se debe según ese roadmap". Alcance elegido: por fase, con pruebas.

### De dónde sale el plan

El roadmap (`talento_roadmap_items`, pantalla `/talento/roadmap`) se sembró el 3-jun con las fases
0–9 de un plan que se construyó completo esa noche (commits del 3 y 4 de junio, uno o varios por
fase, con sus sub-pasos). La conversación original ya no está en el servidor: el plan sobrevive en
esos mensajes de commit. En la tabla, el texto de "alcance" de las fases 3 a 9 no corresponde a su
título (quedó de una plantilla), así que se verificó contra los commits.

Copia de las filas al retirarlo:

| fase | título | estado |
|---|---|---|
| 0 | Auditoría e infraestructura base | done |
| 1 | Esqueleto e identidad del colaborador | done |
| 2 | Motor de compensación | done |
| 3 | Asistencia y geocercas | done |
| 4 | Planta interna (campo + cajas) | done |
| 5 | Planta externa (proyectos + calidad) | done |
| 6 | Control (penalizaciones + credenciales + fondos + préstamos + finiquito) | done |
| 7 | Academia y niveles | done |
| 8 | Dashboards, escalafón y War Room | done |
| 9 | Embajadores y resto del personal | pending |
| 10 | FieldFlowController dual-source: aceptar/activar/onboarding | done (21-sep) |
| 11 | WarrantyWindowService::refreshWindow() para tasks | done (21-sep) |

### Resultado por fase

- **0–1 Esqueleto, identidad, custodia, dispositivos:** existen y abren. El menú se recortó el
  30-sep (casi todo vive como pestaña de la ficha).
- **2 Compensación:** existe y probado esta semana (reglas, cuota diaria ×6, liquidaciones, cierre
  solo por administrador). La semana de pago es Sáb 18:00 → Sáb 18:00 desde julio.
- **3 Asistencia:** existe y probada (entrada y salida por rol). Falla: el radio de checada por
  defecto queda en 0 (ver abajo).
- **4a Flujo de campo:** existe (evidencias, IA, firmas, activación, encuesta). Falta: el cierre
  automático de encuestas (`FieldFlowService::autoCloseSurveys()`) nunca se programó. No se probó a
  fondo porque depende de fotos reales, la IA y la OLT.
- **4b Cajas, garantía, rutas:** existen; la ruta del día se arma y se activa (probado en copia).
  El bono de salud de red usa los valores por defecto del código (no hay ajustes guardados).
- **5 Proyectos y calidad:** existen; reportes de avance probados el 20-oct. Inspecciones con IA
  sin datos.
- **6 Control:** penalizaciones y apelaciones probadas esta semana. Préstamo: se registra, se
  autoriza y la liquidación descuenta el abono semanal ($1,600 → $1,350, probado en copia).
  Finiquito en borrador: suma créditos, resta movimientos y el saldo del préstamo. Falla:
  registrar una licencia de manejo da error 500 (ver abajo).
- **7 Academia:** curso, examen, práctica y certificado funcionan de punta a punta (probado en
  copia). Quien evalúa la práctica necesita ficha de colaborador. Niveles: retirados a propósito
  el 21-oct.
- **8 Dashboards, escalafón, War Room:** existen; el dashboard suelto se consolidó en `/talento`
  el 29-sep.
- **9 Embajadores y resto del personal:** sigue `pending`. Lo de embajadores existe (solo lectura,
  cada quien ve lo suyo); la decisión de compensación de roles no técnicos (#121) sigue abierta,
  aunque por fuera ya se construyeron reglas semanales de ventas y mostrador, comisiones y caja
  de vendedor.
- **10–11:** existen.

Pantallas: el administrador abre las 32 pantallas de Talento sin errores; técnico, supervisor,
vendedor y mostrador abren solo las suyas (los 403 que se ven son consultas de la pantalla para
decidir si muestra botones de administración).

### Fallas encontradas (sin corregir en este cambio)

1. Registrar una licencia de manejo da error 500: `TalentoCredentialController::ensureRenewalFund()`
   llama a `setting()`, que no existe. La licencia se guarda pero el fondo de renovación no se crea.
2. El radio de checada por defecto es 0: `(int) …->value('value') ?? 300` en
   `TalentoWorkSiteController:73` y `AttendanceService:56` (el cast va antes del `??` y la fila no
   existe en `settings`). Una entrada lejos de un sitio con radio propio sale siempre "fuera".
3. El cierre automático de encuestas nunca se programó.
4. El enlace "Talento" en Administradores › Usuarios lleva a `/talento?colaborador=N`, que nadie
   lee; debería abrir `/talento/colaborador/N`.
5. `GET /talento/api/ventas/ranking` sigue abierto a Vendedor (la pantalla se cerró el 21-oct).
6. Por verificar: la contraseña temporal que genera el onboarding de campo se escribe en otra
   columna que la que valida el portal del cliente.

### Qué cambia (rama `fix/talento-quita-roadmap`)

Fuera la pantalla `/talento/roadmap`, su API, el modelo, el menú, los permisos
`talento.roadmap.view/manage` y su página del manual. Migración
`2026_10_22_110000_retira_permisos_talento_roadmap` (permisos y manual). La tabla
`talento_roadmap_items` se borra en la contracción (rama `contraccion/talento-niveles-puestos`),
igual que las de niveles y puestos.
