## 2026-10-08 18:15 — Monitoreo Oficial, Fase 4: scheduler + colas para la revisión

### Contexto

Continuación directa de la Fase 3 (mergeada hoy). Según el nombre de la fase en la épica
(#9991236): "F4 scheduler/colas".

### Decisión de alcance — honestidad sobre lo que no existe todavía

Ningún adaptador de descarga automática (para fuentes `siga`/`vidoc`/`gaceta`/`url`) está
construido — eso es trabajo real de fases posteriores (scraping contra sistemas externos,
potencialmente sensible por su cuenta — requiere su propia decisión, no algo para improvisar
dentro de "scheduler/colas"). Construir un scheduler que **finja** revisar esas fuentes sin
hacerlo de verdad habría sido peor que no construir nada: una falsa señal de "ya se revisó".

Se construyó en cambio la pieza honesta y real que sí corresponde a "scheduler/colas": la
periodicidad y la infraestructura de colas en sí, con bookkeeping genuino para fuentes
`manual_pdf` (donde "revisar" correctamente significa "recordar que alguien debe subir el PDF a
mano", no fingir una descarga) y una traza explícita en el log para cualquier otro tipo de fuente,
dejando claro que falta su adaptador — nunca un silencio que parezca éxito.

### Qué se construyó

- Comando `monitoring:revisar-expedientes` (con `--dry-run`): encola **un job por expediente
  activo** — nunca procesa inline, para que un expediente problemático no frene a los demás.
- `ReviewMonitoringCaseJob` (en cola): sella `last_checked_at` + calcula `next_check_at` (+7
  días). Si la fuente del expediente no es `manual_pdf`, registra en el log que falta su
  adaptador — explícito, no silencioso.
- Registrado en el **schedule dinámico** (tabla `command_configs`, que `app/Console/Kernel.php`
  ya lee en cada tick) vía una migración idempotente — **sin tocar `Kernel.php`**, el archivo
  compartido de todo el sistema.
- UI: "Próxima revisión programada" ahora visible en la ficha del expediente (reusa la columna
  `next_check_at`, que ya existía desde la Fase 1 sin ningún consumidor).

### Verificación

Con un worker de colas real ya corriendo en este entorno: se crearon 3 expedientes de prueba (uno
con fuente tipo `url` sin adaptador, uno sin fuente/manual, uno pausado) y se corrió el comando.
Confirmado: el `--dry-run` cuenta correcto (2, excluye el pausado); la corrida real encoló 2; tras
procesarse, ambos expedientes activos quedaron con `last_checked_at`/`next_check_at` sellados
correctamente y el pausado **sin tocar**; el log muestra exactamente el mensaje esperado para la
fuente sin adaptador ("sin adaptador de descarga automática implementado todavía"). La ficha del
expediente muestra "Próxima revisión programada: en 6 días" correctamente. Datos de prueba
borrados al terminar.

### Pendiente (fases siguientes de la épica, fuera de esta Fase 4)

F5 IA (el análisis real del contenido; también donde correspondería decidir si se construyen
adaptadores de descarga automática para fuentes no-manuales, dada su sensibilidad), F6
notificaciones (usar `last_change_at` para avisar), F7 UI, F8 validación.

### Estado

Mergeado directo a `main` (mismo patrón manual del resto del día).
