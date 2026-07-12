# Módulo EvaluadorEmpresarial

> Cuestionario público de necesidades de conectividad empresarial + panel staff. `app/Modules/Addons/EvaluadorEmpresarial/` · slug `addon-evaluador-empresarial` · addon activo.

## 0. En simple
Es el cuestionario en línea donde una empresa cuenta qué necesita de internet y, al final, recibe un plan recomendado; el equipo de ventas ve todas esas respuestas en un panel aparte.

## 1. Qué es
Formulario público (sin login) que evalúa el perfil de conectividad de una empresa prospecto — criticidad, redundancia, ancho de banda, SLA — y produce un puntaje/categoría con un plan recomendado, junto con un panel staff de solo consulta para revisar y exportar esas evaluaciones.

## 2. Para qué sirve
Le da a ventas una forma de captar y calificar leads empresariales sin intervención manual: el prospecto llena el cuestionario, recibe su resultado al instante (con opción de enviárselo por correo) y queda registrado con su categoría (`BASICO`/`PROFESIONAL`/`CORPORATIVO`/`ENTERPRISE`) para que el equipo lo trabaje desde el panel `/admin/evaluaciones-panel` (listado filtrable, estadísticas por categoría/canal y exportación a CSV).

⚠️ **Estado real del formulario:** el componente Vue (`EvaluadorEmpresarial.vue`) es un **stub** — solo captura nombre/empresa/email/WhatsApp; el cuestionario de scoring real (criticidad/redundancia/ancho de banda/SLA) todavía no está pegado, y `calcularScoring()` genera valores de demostración fijos (`puntaje_total=50`, categoría `PROFESIONAL`). El backend (guardado, envío de email, panel, estadísticas, exportación, conexión a lead) sí está completo y funcional.

## 3. Cómo funciona
- **Modelo/tabla:** `evaluaciones_empresariales` (`EvaluacionEmpresarial`, con `SoftDeletes`) — datos de contacto (`nombre_contacto`, `empresa`, `email_contacto`, `whatsapp_contacto`, `cargo`), resultado (`puntaje_total`, `categoria`, `plan_recomendado`), desglose (`score_criticidad`/`score_redundancia`/`score_ancho_banda`/`score_sla`), `respuestas_json` (crudo del cuestionario), `canal_origen` (`whatsapp`/`email`/`directo`/`vendedor`), `vendedor_id` (FK a `users`, opcional), `lead_id` (FK a `Crm`, opcional — la conexión a un lead del CRM existe en el modelo pero el controller nunca la puebla automáticamente hoy) y `token_publico` (string único de 64 caracteres, autogenerado en `creating()`, usado como llave de la vista de resultado pública).
- **Flujo principal:**
  1. El visitante entra a `/evaluador-empresarial` (vista Blade standalone `master-without-nav` que monta el componente Vue `<evaluador-empresarial>`).
  2. Al enviar, el front hace `POST /evaluador-empresarial/guardar` → `EvaluadorEmpresarialController::guardar()` valida y crea el registro (asigna `token_publico` y `completado_at`).
  3. Si el prospecto dejó email, el front dispara (best-effort, no bloqueante) `POST /evaluador-empresarial/enviar-email` → envía `EvaluacionEmpresarialMail` con el resultado.
  4. El resultado también queda accesible por URL pública `GET /evaluador-empresarial/resultado/{token}` (vista `evaluador-resultado`).
  5. Staff con permiso entra a `/admin/evaluaciones-panel` para ver el listado (`GET /admin/evaluaciones`, paginado con filtros por fecha/categoría/vendedor/canal), el detalle de una evaluación (`GET /admin/evaluaciones/{id}`), estadísticas agregadas (`GET /admin/evaluaciones/estadisticas`) y exportación CSV en streaming (`GET /admin/evaluaciones/exportar`).
- **Rutas:** las 4 rutas públicas viven bajo middleware `web` solo y están listadas en `CheckRoutePermission::PUBLIC_ROUTES` (saltan el gating de permisos); las rutas staff van bajo `web`+`auth`+`check_route_permission` con prefijo `/admin`.
- **Menú:** suprimido del sidebar principal — según `ModuleSidebarConfigSeeder`, el acceso staff vive únicamente dentro de `/configuracion` (subsección "Evaluador Empresarial"), no como ítem de primer nivel.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas públicas** (sin auth, en `PUBLIC_ROUTES`): `GET /evaluador-empresarial` (cuestionario), `POST /evaluador-empresarial/guardar`, `POST /evaluador-empresarial/enviar-email`, `GET /evaluador-empresarial/resultado/{token}`.
- **Rutas staff** (auth + `check_route_permission`, prefijo `/admin`): `GET /evaluaciones-panel`, `GET /evaluaciones` (listado JSON), `GET /evaluaciones/estadisticas`, `GET /evaluaciones/exportar` (CSV), `GET /evaluaciones/{id}`.
- **3 permisos**: `evaluador_view_evaluacion`, `evaluador_view_estadisticas`, `evaluador_export_evaluacion` (además `evaluador_add_evaluacion`/`evaluador_email_evaluacion` catalogados en `PermisosRegistrarNuevosCommand`, sin ruta asociada aún).
- **Modelo/tabla `evaluaciones_empresariales`** — la consume el módulo `SmartImportExport` (import/export vía `EvaluacionEmpresarial`, conflicto por `token_publico`).
- No dispara eventos de Laravel ni tiene webhooks.

**Consume**
- **Correo saliente** — usa el mailer estándar de Laravel (`Mail::to()->send()`) con el Mailable propio `EvaluacionEmpresarialMail`; no tiene proveedor de email propio.
- **Módulo CRM** (`App\Modules\Core\CRM\Models\Crm`) — relación `lead_id` declarada en el modelo para vincular la evaluación a un lead existente, pero ningún controller la escribe automáticamente hoy (`lead_creado` siempre regresa `false` en `guardar()`).
- **`users`** — `vendedor_id` referencia al usuario (vendedor) que originó el canal, mostrado en listado/exportación.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
