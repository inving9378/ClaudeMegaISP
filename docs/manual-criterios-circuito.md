# MANUAL DE CRITERIOS — Circuito de Mejora Continua (MegaISP / Meganet)

> Documento vivo servido en `GET /api/roadmap-externo/{token}` (campo `manual_criterios`).
> Consolida TODAS las reglas vigentes. El **CLAUDE.md completo** viaja anexado al final
> de la respuesta (fuente viva del repositorio, nunca desincronizada).
> Última consolidación: 2026-07-08.

---

## 0. QUÉ ES ESTE CIRCUITO

Flujo de mejora continua entre dos actores:

1. **Claude Code (local, en la red de Meganet)** — audita el sistema y **llena la Hoja de Ruta**
   con hallazgos y planes de solución (`prompt_para_claude` por fases). Ejecuta SOLO lo aprobado.
2. **Claude Cowork (nube de Anthropic, FUERA de esta red)** — revisa diariamente los items vía
   el endpoint externo con token, asigna/ajusta `nivel_riesgo` y pone `estado_aprobacion`.

La Hoja de Ruta contiene **debilidades reales del sistema** → el acceso externo **JAMÁS** se
abre sin token.

### Niveles de riesgo (criterio para clasificar cada item)
- **A — Seguro**: aditivo, reversible, **NO** toca dinero / permisos / autenticación / producción.
  Puede ejecutarse en automático **si** `estado_aprobacion = aprobado_claude`.
- **B — Requiere confirmación de Irving**: cambios sensibles pero acotados. Solo se trabajan en
  sesión con Irving confirmando.
- **C — Decisión de diseño exclusiva de Irving**: cambios de arquitectura, negocio o política.
  **Jamás** se ejecutan sin decisión explícita de Irving.

> **Regla conservadora:** ante la duda entre dos niveles, elegir el **más restrictivo** (→ B sobre A, → C sobre B).

### Estados de aprobación
`pendiente_revision` (default) → `aprobado_claude` | `requiere_irving` | `rechazado` | `en_progreso` | `completado`.

### Reglas de ejecución
- **Modo automático** SOLO para items con `estado_aprobacion = aprobado_claude` **Y** `nivel_riesgo = A`.
- **Nivel B**: solo en sesión con Irving confirmando.
- **Nivel C**: jamás sin decisión de Irving.
- Al trabajar un item: `en_progreso` al arrancar → `completado` al cerrar, **con el hash del commit referenciado en el item**.

---

## 1. SERVICIOS COMPARTIDOS ÚNICOS — PROHIBIDO DUPLICAR

Antes de construir **cualquier capacidad** en **cualquier módulo** (existente o futuro),
verificar (grep cross-módulos) si ya existe. Si existe, el módulo se **CONECTA** al servicio
existente; **nunca** construye su propia versión.

1. **WhatsApp** → `WhatsAppAgent` es el **gateway único**. Los consumidores usan `WhatsAppGateway`
   + **eventos** (`WhatsAppMessageReceived`/`TextReceived`/`MediaReceived`). **Nadie** monta webhook
   ni línea propia. (La integración WhatsApp de Marketing se está consolidando a este gateway.)
2. **Mapas** → un solo módulo provee mapas y credenciales; los demás **agregan sus funciones dentro
   del mapa compartido**, no su propio mapa/clave.
3. **IA** → los proveedores viven **solo** en el módulo IA (`ia_proveedores`, `/ia/configuracion`).
   Cualquier uso de IA se conecta a los **adaptadores existentes** (`ClaudeApiClient`, Integration
   Hub) — **sin clientes HTTP propios ni keys por módulo**.

La lista **crece** al designar nuevos servicios únicos.

**Señales de violación a auditar:** clientes HTTP propios a APIs de IA, mapas/claves duplicados,
webhooks paralelos de WhatsApp, keys hardcodeadas o por módulo, servicios que reimplementan algo ya existente.

---

## 2. CONVENCIONES OPERATIVAS OBLIGATORIAS

### Git y despliegue
- **Commits en español**, mensaje claro por sub-paso.
- **`git add` SELECTIVO** por archivo — **NUNCA `git add -A`** (barre secretos/exports sin commitear).
- El pipeline de release migró su staging a **allowlist**; no reintroducir `git add -A`.

### Migraciones
- **Aditivas e idempotentes**: `create table` / `add column` con guard `hasColumn` / `firstOrCreate`.
  Nada destructivo en `up()` (drops solo en `down()`).
- **PROHIBIDO `migrate:fresh`** en dev/prod (destruye datos). `TestCase.php` corre `migrate:fresh --seed`
  → **no correr PHPUnit contra la DB de dev/prod**; validar con `tinker`.
- **ids de DEV ≠ ids de PROD** (auto-increments divergentes). **Jamás** resolver una cuenta/módulo por
  `id` copiado de otro entorno; resolver por `login_user`/email/slug/nombre.

### Autenticación / datos legacy
- Login field = **`login_user`** (NO `email`).
- Passwords admin interno = **`base64_encode`** (patrón existente, NO bcrypt). Portal cliente usa `password` plano vía `hash_equals`.
- **Fechas legacy** en formato **DD/MM/YYYY** → parsear con `STR_TO_DATE`, no asumir ISO.

### Permisos (spatie/laravel-permission)
- Permisos **acumulativos**: para agregar usar **`givePermissionTo`** (aditivo). **JAMÁS `syncRoles`/`syncPermissions`** (destructivos).
  Footgun conocido: `UserController::update` (~línea 278) hace `syncRoles` destructivo — NO blindado aún.
- Hay **dos caminos** de verificación de permisos que pueden divergir (middleware `CheckRoutePermission`
  vs gates propios de módulo como OLT). Auditar consistencia.

### Blade / Frontend
- **`@can` NO funciona en el Blade del sidebar** de este proyecto para todos los casos — usar `@canany`/`@hasanyrole` como está establecido; el sidebar es **Blade estático** (agregar módulos a mano).
- **Un solo app Vue**; componentes registrados en `resources/js/app.js`. Tras cambios: `npm run dev`.

### Jobs / colas
- **`php artisan queue:restart` tras tocar jobs o listeners** (los workers cargan código en memoria;
  sin restart corren la versión vieja). Verificar uptime del worker vs timestamp del commit.
- `QUEUE_CONNECTION=database`; en dev requiere worker vivo para procesar (abonos de saldo, media, IA async).

### Proceso
- **Paso 0 de confirmación**: antes de tocar datos sensibles (roles/permisos/dinero), investigar
  **read-only** y confirmar el objetivo real ANTES de escribir. (Casi se le dio super-admin a un cliente por arrastrar un id.)
- **Servicios compartidos primero** (ver §1): grep antes de crear código nuevo.

---

## 3. CONTEXTO DE NEGOCIO

- **Meganet Telecomunicaciones / MegaISP**: sistema de gestión para ISP (clientes, facturación, CRM,
  planes Internet/VoIP/Custom/Bundle, MikroTik, OLT/ONU, fibra, tickets, inventario, vendedores/comisiones,
  agendamiento). Codebase y UI en **español**.
- **DEV** = `192.168.105.11` · **PROD** = `192.168.105.198` (bases distintas divergidas).
- **MegaFamilia**: control parental (16 tablas `parental_*`), app Flutter, activable desde el portal cliente.
- **Embajadores**: programa de referidos multinivel. **PROHIBIDO usar la palabra "piramidal"** (marketing multinivel legítimo, no esquema piramidal).
- **Talento**: gestión de personal de campo (OTs, compensación, academia, penalizaciones), app React Native.
- **Flotas**: gestión vehicular (GPS, geocercas) — uso interno + producto SaaS.
- **Conciliación de pagos por WhatsApp con IA**: comprobante → IA lee → identifica al cliente conversando →
  aplica el pago como usuario de sistema **MEGAISP**; dudoso → cola humana (Tere). Freno maestro
  `payments.auto_apply_enabled` (default OFF).
- **Portal Cliente** (`/portal/*`) y **Portal Colaborador** (`/talento/portal`) tienen guards separados del admin.

---

## 4. DIMENSIONES DE AUDITORÍA (qué busca cada revisión)

Por módulo:
- **(a) Violaciones al manual**: duplicación de servicios/capacidades, clientes HTTP propios a IA,
  mapas propios, webhooks paralelos, keys hardcodeadas o por módulo.
- **(b) Bugs latentes**: queries rotas, referencias a tablas/columnas inexistentes, rutas muertas.
- **(c) Seguridad**: permisos mal gateados, endpoints sin protección, secretos en git, inyección.
- **(d) Inconsistencias de arquitectura**: los dos caminos de permisos, componentes Vue duplicados, código muerto.
- **(e) Deuda registrable**: TODOs, placeholders, migraciones pendientes.

Cada hallazgo → un item en la Hoja de Ruta con: título claro, descripción con evidencia
(`archivo:línea`), módulo, `nivel_riesgo` (conservador), `estado_aprobacion = pendiente_revision`,
y `prompt_para_claude` con plan por fases (fases, verificación, commits).
