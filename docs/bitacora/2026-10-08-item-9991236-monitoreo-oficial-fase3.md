## 2026-10-08 18:05 — Monitoreo Oficial, Fase 3: documentos PDF manuales por expediente

### Contexto

Continuación directa de la Fase 2 (mergeada hoy). Según el nombre de la fase en la épica
(#9991236): "F3 fuentes+PDF manual" — la propia pantalla de Configuración de la Fase 2 ya avisaba
"la descarga automática por fuente se habilita en la Fase 3".

### Decisión de alcance

El nombre de la fase menciona "PDF manual", no scraping automático de `siga`/`vidoc`/`gaceta`/`url`
(eso encajaría mejor en fases posteriores, scheduler+IA). Se interpretó como: dejar que alguien
suba a mano el PDF de un expediente (la declaración, el comprobante, la resolución recibida) y que
el sistema guarde un historial con huella (hash) para distinguir "subiste el mismo documento otra
vez" de "esto es distinto a lo que ya tenías" — el primer ladrillo real de "detección de cambios"
que describe el módulo, sin construir todavía el análisis de contenido (eso es IA, fase aparte).

### Qué se construyó

- Tabla nueva `monitoring_case_documents` (propia del módulo, `down()` segura — se borra sola al
  desinstalar).
- `CaseDocumentService::subir()`: guarda el PDF, calcula su sha256, y lo compara contra el último
  documento del mismo expediente. Si es idéntico, se guarda en el historial pero **no** se marca
  como cambio; si es distinto, se actualiza `last_change_at` del expediente — la señal que una fase
  futura (notificaciones/IA) podrá usar para avisar.
- Pantalla nueva "Ver expediente" (antes solo existían listar/editar): datos completos, formulario
  de subida, e historial de documentos con descarga y borrado.
- Candados: solo PDF, máximo 15 MB; Laravel genera el nombre del archivo en disco (nunca el que
  manda el usuario, evita colisiones/path traversal); subir/borrar requiere
  `monitoring.manage_cases`, descargar solo requiere `monitoring.view` (ya exigido por la página).

### Dos hallazgos reales corregidos en el camino

1. Había escrito `@can('monitoring.manage_cases')`/`@endcan` en las vistas — viola la convención
   dura del propio repo (Blade debe usar `@if(auth()->user()->can('x'))`, nunca `@can()`).
   Corregido antes de probar en navegador.
2. **Bug real encontrado probando el flujo completo, no solo el feliz**: borrar un expediente que
   todavía tenía documentos adentro (sin borrarlos uno por uno primero — el caso normal de uso)
   dejaba los PDFs físicos huérfanos en disco para siempre; la cascada de la base de datos solo
   limpiaba las filas, nunca los archivos. Corregido con un gancho en el modelo que borra los
   archivos del expediente antes de que la cascada se lleve sus filas. Verificado en navegador:
   crear expediente, subir PDF, borrar el expediente directo → sin error, limpio.

### Nota técnica — por qué no se tocó `module.json`

La memoria del proyecto es explícita: la versión de un addon se sube **una sola vez, al cerrar
todo el plan multi-fase**, no como marcador de cada fase intermedia (corrección de David,
23-sep-2026, sobre MegaVoz). La migración nueva de todos modos corre sin depender de ese campo:
`BaseModuleServiceProvider` ya registra el directorio de migraciones del módulo en cada arranque
(`loadMigrationsFrom`), así que `php artisan migrate --path=...` la recoge igual — el mecanismo de
versión de `module:lifecycle upgrade` es solo para los hooks de DML/permisos, no un requisito para
que la migración en sí se aplique.

### Verificación

Ciclo completo en navegador con cuenta de prueba (creada y borrada): crear expediente → ver su
ficha (datos en 0, sin documentos) → subir un PDF (mensaje "nueva versión") → resubir el MISMO PDF
(mensaje "idéntico... no se marcó como cambio") → subir un PDF DISTINTO (mensaje "nueva versión" de
nuevo, `last_change_at` se actualiza) → descargar un documento (llega el archivo correcto) →
eliminar un documento (el contador baja) → eliminar el expediente completo con documentos
pendientes adentro (sin error, limpio). Datos de prueba confirmados en cero al terminar.

### Pendiente (fases siguientes de la épica, fuera de esta Fase 3)

F4 scheduler/colas, F5 IA (el análisis real del contenido del PDF), F6 notificaciones, F7 UI, F8
validación.

### Estado

Mergeado directo a `main` (mismo patrón manual del resto del día).
