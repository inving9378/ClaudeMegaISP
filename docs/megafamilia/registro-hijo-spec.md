# MegaFamilia — Spec: registro del hijo desde la app del padre

> Origen: item Hoja de Ruta **#471** (reemplaza a #29, PARKED-ESPEC). Decisiones tomadas por
> Irving el 2026-08-04 vía brief multi-pregunta (`preguntas` del item #471, log de decisión
> `2026-08-04T12:39:05-06:00`). Este documento congela esas decisiones como especificación
> ejecutable; los items derivados que la implementan viven en la Hoja de Ruta y referencian este
> archivo (ver sección "Items derivados").

## 1. Decisiones de Irving (fuente de verdad)

| # | Pregunta | Decisión |
|---|----------|----------|
| Q1 | ¿Cómo se inicia el registro del hijo? | **QR/código de invitación**: el padre lo genera desde su app; el hijo lo escanea o lo introduce al instalar su app. |
| Q2 | ¿Qué datos mínimos del hijo se capturan? | **Mínimo viable**: solo nombre y edad/fecha de nacimiento. |
| Q3 | ¿Cómo se autentica el hijo después del registro? | **PIN corto controlado por el padre** desde su app (el padre puede resetearlo/cambiarlo). |
| Q4 (Fase 2) | ¿Cuántos hijos puede registrar un padre? | **Límite ligado al plan del ISP** (ej. plan básico = 2, plan familiar = 6). |
| Q5 | ¿Formato de entrega del brief? | **Ambos**: este documento + items derivados en la Hoja de Ruta que lo referencian. |

## 2. Estado actual de la infraestructura (verificado en código, no se re-diseña desde cero)

El esquema de `app/Modules/Addons/MegaFamilia/migrations/` ya cubre buena parte de lo decidido:

- `parental_accounts` — cuenta padre (`client_id` → `users`, `plan_id` → `parental_plans`, `status`,
  `terms_accepted_at`/`terms_ip`/`terms_version_accepted`, `client_isp_id`).
- `parental_profiles` — perfil del hijo (`account_id`, `name`, `age`, `school_level`, `profile_type`,
  `photo`, `active`). **Ya es "solo nombre y edad" en la práctica** (Q2): no tiene teléfono, email ni
  credenciales propias — el mínimo viable ya es el shape actual del modelo.
- `parental_devices` — dispositivo del hijo (`profile_id`, `account_id`, `os`, `status`,
  `last_seen_at`, `fcm_token`, **`link_token` (64, unique) + `linked_at`**). Este `link_token` es
  exactamente el mecanismo de vinculación por código/QR que pide Q1 — ya existe la columna, falta el
  flujo que la genera/consume (ver sección 7).
- `parental_plans` — **`max_children`, `max_devices`, `max_parents`** ya existen como columnas. La
  decisión Q4 (límite ligado al plan) **ya está modelada**; no requiere migración nueva, solo
  aplicar el enforcement al momento de crear el perfil (ver sección 7, ítem D).
- `parental_consents` — versiones de términos + `parental_accounts.terms_version_accepted` para el
  consentimiento del padre (sección 5).
- `parental_requests` — solicitudes hijo→padre (`time_extra`/`app_unlock`/`web_unlock`), reusable
  como precedente de UI para "solicitud pendiente de respuesta", pero **no** es el mecanismo de alta
  del hijo (ese es nuevo, ver sección 7).

Lo que **no existe todavía** y requiere migración/código nuevo:
- PIN del hijo (Q3): no hay columna de credencial en `parental_profiles`. Falta `pin_hash` (o tabla
  aparte `parental_profile_pins` si se prefiere separar del perfil) + endpoint de verificación.
- Expiración/estado explícito de la invitación QR (Q1): `link_token`/`linked_at` alcanzan para saber
  "vinculado o no", pero falta `link_token_expires_at` (o similar) para invitaciones caducables
  (sección 5, regla de seguridad).
- Endpoint de generación de código (`POST /profiles/{id}/link-token` o similar) y de canje
  (`POST /devices/link` con el token) — no están en el listado de endpoints existentes de la
  auditoría `docs/megafamilia-auditoria-2026-05-26.md`.

## 3. Flujo de registro del hijo (Q1)

1. El padre, desde su app (perfil ya con `parental_account` activa), entra a "Agregar hijo".
2. Captura los datos mínimos del hijo (sección 4): nombre + edad/fecha de nacimiento.
3. El backend crea el `parental_profile` (`account_id` = cuenta del padre, `active=true`) — el
   perfil existe **antes** de que haya un dispositivo vinculado (un hijo puede tener perfil sin
   dispositivo todavía, ej. es muy chico y aún no tiene teléfono propio).
4. El padre pulsa "Vincular dispositivo del hijo" → la app pide un código/QR de invitación
   (`link_token` con expiración corta, ej. 15 minutos — igual que un pareo de WhatsApp Web).
5. En el dispositivo del hijo: instala la app → pantalla "Tengo un código de mi papá/mamá" →
   escanea el QR o teclea el código → la app manda el token al backend.
6. El backend valida: token existe, no expiró, no fue usado → crea/actualiza el
   `parental_device` (`profile_id`, `account_id`, `os`, `linked_at=now()`) y **consume** el token
   (invalida para reintentos).
7. El backend responde con las credenciales de sesión del hijo (ver sección 3.1 — PIN) y el
   dispositivo queda operativo.
8. El padre ve en su app "Hijo vinculado" con el dispositivo activo.

### 3.1 Vínculo con la cuenta del padre

- Un `parental_profile` pertenece a exactamente una `parental_account` (FK `account_id`, ya
  existente) — el hijo **no** es una cuenta independiente del sistema ISP (`users`); vive
  enteramente bajo el padre.
- Unicidad: un `link_token` es de un solo uso y expira; no se reutiliza entre dispositivos. Un
  mismo perfil puede tener **más de un dispositivo** vinculado (ej. tablet + celular del mismo
  hijo) — cada uno con su propio ciclo QR de alta.
- Revocación: el padre puede desvincular un dispositivo desde su app en cualquier momento
  (`parental_devices.status` → dejar de reportar / borrado lógico). Desvincular un dispositivo
  **no** borra el `parental_profile` ni su historial (ubicaciones, tareas, alertas) — solo corta el
  canal activo. Re-vincular = repetir el flujo de la sección 3 generando un nuevo `link_token`.

## 4. Datos mínimos capturados (Q2)

Al alta del hijo, el padre solo captura:
- **Nombre** (`parental_profiles.name`, requerido).
- **Edad o fecha de nacimiento** (`parental_profiles.age`, requerido — determina `profile_type`
  sugerido: nino/preadolescente/adolescente).

Explícitamente **fuera** del alta inicial (se completan después, opcionales, ya soportados por el
modelo): `school_level`, `photo`. No se captura teléfono ni email del hijo — el vínculo con el
dispositivo se hace por el QR/código (sección 3), no por identidad de contacto.

## 5. Reglas de seguridad

- **Consentimiento del padre**: reusa `parental_consents` — el padre debe tener
  `terms_accepted_at`/`terms_version_accepted` vigente en su `parental_account` (ya exigido al
  activar el plan) antes de poder dar de alta hijos. No se pide consentimiento aparte por cada
  hijo — es el mismo consentimiento de la cuenta.
- **Invitación caducable**: el `link_token` expira (propuesta: 15 minutos) y es de un solo uso.
  Vencido, el padre debe generar uno nuevo (no hay "reenviar" el mismo código).
- **PIN del hijo** (Q3): 4-6 dígitos, generado o elegido en el momento de vincular el dispositivo,
  **el padre lo controla** (puede verlo/resetearlo desde su app en cualquier momento — no es un
  password que el hijo controle de forma autónoma). El PIN autentica al dispositivo del hijo frente
  al backend en cada sesión (no hay usuario/contraseña tipo `users` estándar).
- **Protección de datos del menor**: los datos del hijo (nombre, edad, ubicación, actividad) solo
  son visibles para la `parental_account` dueña (multi-tenant ya aplicado vía `client_isp_id` en
  las 14 tablas hijas — ver migración `2026_06_25_100000_...`). Ningún endpoint debe exponer datos
  de un perfil fuera de su cuenta (mismo patrón anti-IDOR que ya usa el Portal Cliente).
- **Rate limiting** en el endpoint de canje de token (evitar fuerza bruta sobre códigos de 6-8
  caracteres).

## 6. Casos de uso

| Caso | Comportamiento esperado |
|------|--------------------------|
| **Alta feliz** | Padre crea perfil → genera QR → hijo escanea dentro de la ventana → dispositivo vinculado y operativo. |
| **Invitación no aceptada** | El `link_token` expira sin canjear → el perfil del hijo queda `active=true` pero **sin** dispositivo vinculado (`parental_devices` vacío para ese profile) → la app del padre lo muestra como "pendiente de vincular", con botón para generar un código nuevo. |
| **Dispositivo perdido** | El padre desvincula el dispositivo desde su app (revocación, sección 3.1) → el dispositivo perdido deja de tener sesión válida (el backend rechaza su PIN/token de sesión) → el padre puede vincular un dispositivo de reemplazo generando un nuevo QR. |
| **Revocación** | Igual que "dispositivo perdido" pero iniciada porque el padre ya no quiere que ese dispositivo tenga acceso (no por pérdida). Mismo mecanismo. |
| **Re-vinculación** | Repetir el flujo completo de la sección 3 desde el paso 4 (el perfil ya existe, solo se genera un nuevo `link_token` y se canjea en el dispositivo). |
| **Límite de hijos alcanzado (Fase 2, Q4)** | Si `parental_profiles` activos de la cuenta == `parental_plans.max_children` del plan vigente → el backend rechaza el alta con un mensaje claro ("tu plan permite hasta N hijos") y la app ofrece upgrade de plan. |

## 7. Criterios de aceptación

- **A.** El padre puede crear un `parental_profile` con nombre + edad únicamente; el registro no
  pide ni permite campos fuera del mínimo (sección 4) en el alta inicial.
- **B.** El padre puede generar un código/QR de vinculación (`link_token` + expiración) desde su
  app para un perfil dado, y ese código deja de ser válido tras expirar o tras un canje exitoso.
- **C.** El hijo, con el código vigente, puede vincular su dispositivo sin necesidad de credenciales
  del sistema ISP (`users`) — el vínculo es 100% dependiente del código emitido por el padre.
- **D.** El alta de un hijo respeta `parental_plans.max_children` de la cuenta: al límite, el
  backend responde con error explícito (no un fallo genérico) — **Fase 2**, no bloquea el resto del
  MVP (A/B/C/E/F/G).
- **E.** El padre puede ver, en cualquier momento, si un perfil tiene dispositivo vinculado o está
  pendiente, y puede revocar/desvincular un dispositivo desde su app.
- **F.** El hijo se autentica en su dispositivo vía PIN gestionado por el padre (no hay
  autoservicio de contraseña del lado del hijo); el padre puede resetear el PIN desde su app.
- **G.** Ningún endpoint nuevo expone datos de un `parental_profile`/`parental_device` fuera de la
  `parental_account` dueña (verificación anti-IDOR, mismo patrón que Portal Cliente).

## 8. Fuera de alcance de este documento

- UI visual/mockups (queda para el item de implementación de cada pantalla).
- Notificaciones push del alta (FCM sigue pendiente de configurar en el proyecto — ver
  `docs/megafamilia-auditoria-2026-05-26.md` sección 6).
- Cobro/upgrade de plan cuando se alcanza el límite de hijos (Fase 2, solo se especifica el
  comportamiento de rechazo, no el flujo de compra).

## 9. Items derivados (Hoja de Ruta)

Ítems creados a partir de esta especificación (`origen_item_id=471`, nivel de riesgo B —
requieren confirmación de Irving por tocar auth/vinculación de dispositivos):

- **#498** — Migraciones: `pin_hash` en `parental_profiles` (o tabla dedicada) +
  `link_token_expires_at` en `parental_devices`.
- **#499** — Backend: endpoints de generación/canje de `link_token`, alta de perfil con
  enforcement de `max_children`, autenticación por PIN del dispositivo del hijo.
- **#500** — Frontend (apps): pantalla "Agregar hijo" (padre), pantalla "Tengo un código"
  (hijo/QR scan — `mobile_scanner` ya está instalado según la auditoría), pantalla de gestión de
  dispositivos vinculados (padre). Depende de #499.
