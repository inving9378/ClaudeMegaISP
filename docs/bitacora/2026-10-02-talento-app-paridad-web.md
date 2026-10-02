## 2026-10-02 — App móvil de Talento: paridad real con la ficha web

### Origen

David, tras ver la app con firma/exámenes funcionando: "la app es la parte de talento de la
web, no debe haber nada que sea solo lectura, se debe poder hacer lo mismo que se hace en la
web en la sección de talento en la app... firmar, hacer las mediciones de las cajas, los flujos
de campo, las tareas, tomar las fotos de evidencia, etc, todo, replica las funcionalidades de
talento en la app". También pidió mover el check-in/check-out de "Mi día" a la pestaña
Asistencia.

### Auditoría primero, código después

Antes de construir nada se auditó la ficha web real (`TalentoColaboradorFicha.vue`, 19
pestañas) para separar lo que de verdad es autoservicio del colaborador de lo que es
admin-only o ya está cubierto:

| Área | ¿Autoservicio real en la web? | Acción |
|---|---|---|
| Firmar documentos | Sí | Ya resuelto (1-oct) |
| Tomar cursos/exámenes | Sí | Ya resuelto (1-oct), corregido el detalle de materiales hoy |
| Cajas ODB (lectura dBm) | Sí, cualquier técnico | **Construido hoy** |
| Calidad de caja (foto+mediciones) | Sí, autoservicio | **Construido hoy** |
| Proyectos (reportar avance) | Sí, acotado a uno mismo/equipo | **Construido hoy** |
| Asistencia (check-in/out) | Sí, pero vivía en otra pantalla | **Movido hoy** |
| Flujo de campo | No aporta nada nuevo (duplicaría evidencia de OT) | Sin cambio, a propósito |
| Tareas | No existe aparte — ya fusionado con Órdenes de trabajo | Sin cambio, a propósito |
| Tomar fotos de evidencia | Ya existe completo (flujo de OT) | Sin cambio |
| Custodia/Dispositivos/Roles múltiples/Credenciales/Liquidaciones/Compensación/Información | Solo lectura o admin-only también en la web | Sin cambio |

### Backend (megaisp)

6 endpoints nuevos en `TalentoMobileEquipoController`, todos delegando directo a la lógica
que la web ya usa y prueba (cero duplicación):
- `POST /portal/cajas` → `TalentoCajaController::store`
- `POST /portal/inspecciones` (+foto) → `TalentoQualityController::storeInspection`
- `POST /portal/inspecciones/{id}/ia` → `TalentoQualityController::runIaAnalysis`
- `GET /portal/proyectos-catalogo` (+detalle) → `TalentoProjectController::data/show`
- `POST /portal/proyectos-catalogo/{id}/actividades/{actId}/reportes` → `::submitReport`

Verificado end-to-end con la cuenta demo: baseline de caja registrado, proyecto+actividad
sintéticos con reporte de avance (quedó "pending", puntos calculados), inspección con foto
real (resultado "pass" calculado automáticamente, baseline derivado auto-registrado), análisis
IA disparado correctamente. Toda la data de prueba se borró al terminar.

Commits: `c21727cd` (rama `feature/talento-app-cajas-calidad-proyectos`) + merge `d458e7af`.

### App (TalentoEquipo)

- **AsistenciaPanel** (nuevo componente): el check-in/check-out real + historial, ahora en la
  pestaña Asistencia de Talento. `MiDiaScreen` se quedó solo con las órdenes del día.
- **CajaLecturaScreen**: formulario simple (caja, potencia dBm, notas).
- **InspeccionCalidadScreen**: cámara integrada + GPS + mediciones (pérdida de fusión,
  potencia, puntaje estético) + notas, sube con foto real.
- **ProyectosPanel + ProyectoDetalleScreen**: catálogo de proyectos activos, barra de avance
  por actividad, modal de "Reportar avance" con selector de participantes (uno mismo + equipo
  directo).
- **CursoDetalleScreen corregido**: los materiales de tipo texto/referencia (antes invisibles,
  el código solo sabía de video/pdf con URL) ahora se muestran igual que en la web; el examen
  muestra el mejor puntaje ya alcanzado y cambia a "Re-presentar" si ya se aprobó.

Verificado: bundle regenerado, cadenas nuevas confirmadas dentro del APK empaquetado, MD5
idéntico en 3 puntos (build/servido/descargado).

**APK:** `http://192.168.105.11/downloads/talento-v1.9.apk` — versión **1.9** (versionCode 109).

Commit `e4fcf68` en `/home/meganet/TalentoEquipo` (rama `master`, sin remoto).

### Pendiente

Validación visual de David en el teléfono: check-in/out desde Asistencia, registrar una
lectura de caja, capturar una inspección de calidad con foto real, reportar avance de un
proyecto, y revisar que un curso con materiales de texto/referencia se vea bien.
