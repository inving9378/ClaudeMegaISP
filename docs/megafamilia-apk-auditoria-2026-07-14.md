# Auditoría app móvil MegaFamilia — item roadmap #23 (2026-07-14)

## 0. El blocker de las 6 escaladas previas era una premisa falsa

Desde 2026-07-11 el item se re-escaló 6 veces asumiendo que el código Flutter vivía en
`/var/www/megaisp/mobile/megafamilia`, gitignoreado (`.gitignore:36`) e inaccesible desde
cualquier worktree del circuito por la regla de aislamiento #334.

**Verificado (`git log --all -- mobile/`):** ese path SÍ existió, pero el commit `5768e314`
(2026-06-03, "chore: mover proyecto Flutter megafamilia fuera del repo megaisp") lo sacó por
completo del repo `megaisp`. El código real vive hoy en **`/var/www/megafamilia`** — un repo
Git **independiente**, fuera de `/var/www/megaisp`, con permisos de lectura abiertos
(`drwxrwxrwx`). La regla dura #334 prohíbe tocar `/var/www/megaisp`; no dice nada de
`/var/www/megafamilia` porque es una ruta completamente distinta, sin relación con los
worktrees de `megaisp`. Por eso esta auditoría sí se pudo hacer (solo lectura, sin tocar nada
en `/var/www/megaisp` ni en `/var/www/megafamilia`).

## 1. Hallazgo no pedido pero crítico: migración Flutter → React Native en curso

Desde 2026-06-05 existe un proyecto paralelo **`/var/www/megafamilia-rn`** con plan de
migración documentado (`MIGRATION_RN.md`, dentro del propio repo Flutter). El backend
(Laravel, `/api/megafamilia/*`) **no cambia** — solo se reescribe el cliente móvil.

- **37 de 39 pantallas Flutter ya tienen su equivalente `.tsx`** en React Native (~95% de la
  superficie de pantallas portada). Faltan por confirmar 2 (`notificaciones_log_screen`,
  `followup_form_screen` de Embajadores no aparecieron en el listado de `src/screens/embajador/`
  de la RN, requiere confirmación).
- Mapeo 1:1 de stack: Provider→Zustand, GoRouter→React Navigation, `http`→Axios,
  SharedPreferences→AsyncStorage, flutter_map→react-native-maps, etc.
- **Implicación para el roadmap:** el Flutter auditado en este documento es la versión que se
  está reemplazando. Si el objetivo de negocio es decidir dónde invertir el próximo esfuerzo
  (nuevas pantallas, fixes, certificación de tiendas), **el target relevante es
  `megafamilia-rn`, no Flutter**. Este documento audita Flutter porque es lo que pedía
  literalmente el item; queda como tarea aparte (no bloqueante) decidir si vale la pena repetir
  este inventario contra la app RN.

## 2. Stack (Flutter, v0.3.5+8 — el item decía "0.3.4+7", desactualizado)

- Flutter 3.3 / Dart 3.3 (SDK `>=3.3.0 <4.0.0`)
- Routing: `go_router` ^13 (redirect centralizado por rol en `router.dart`)
- Estado: `provider` (ChangeNotifier) — 6 providers: auth, cliente, embajador, hijo, tecnico,
  control_parental
- HTTP: paquete `http` envuelto en `ApiService` propio (maneja 401→logout, timeouts,
  mensajes de error por código HTTP)
- Persistencia local: `shared_preferences`
- Biometría opcional al desbloquear: `local_auth`
- QR: `mobile_scanner` (nota en el propio pubspec: reemplazo de `qr_code_scanner`, que está
  archivado y rompe con AGP moderno)
- OTA self-update: `package_info_plus` + endpoint propio `GET /api/megafamilia/mobile/check-update`
  (público, sin auth — la app lo consulta antes de loguear)
- Mapas: `flutter_map` (OpenStreetMap, sin API key) + `latlong2`
- Gráficas: `fl_chart`
- Imágenes: `cached_network_image`, `image_picker` (comprobantes SPEI)
- Contactos del dispositivo: `flutter_contacts` (importar prospectos, Embajadores)
- Onboarding: `tutorial_coach_mark`
- **Firebase (push) y Google Maps deliberadamente omitidos** (comentario del propio pubspec:
  requieren `google-services.json` y Maps API key, pendientes de esos secretos)
- Fallback a datos mock activable/desactivable en build time: `MOCK_FALLBACK` (default `true`)

## 3. Perfiles de usuario — son 5, no 4 como decía la descripción del item

El item decía "Cliente, Embajador (100%), Tecnico (mock), Hijo (parcial)". La realidad actual
(`router.dart`) tiene 5 áreas con dashboard propio, más 2 sub-áreas embebidas dentro de Cliente:

1. **Cliente** (`/cliente`) — servicio, facturas, pagos, tickets, perfil, control parental.
2. **Cliente → Flotas** (`/cliente/flotas/*`) — vista multi-vehículo para el cliente dueño de
   una flota (resumen, detalle, documentos, mantenimientos, geocercas, historial de posición).
3. **Cliente → Embajador** (`/cliente/embajador/*`) — CRM de prospectos + red multinivel +
   comisiones + recompensas + notificaciones + compartir masivo. (Vive bajo el árbol de rutas
   de Cliente, no es un rol/dashboard separado como sugería el item.)
4. **Conductor** (`/conductor`, Flotas) — dashboard con tabs de mapa, documentos,
   mantenimientos, eventos de geocerca del vehículo asignado.
5. **Técnico** (`/tecnico`) — dashboard, listado/detalle de órdenes, workflow, cierre de orden.
6. **Hijo** (`/hijo`, control parental) — dashboard, tareas, logros, solicitar permiso,
   pantalla de bloqueo.
7. **Admin / super-administrator / DESARROLLADOR** — sin app propia: `_homeForRole` los manda a
   `_AdminRedirectScreen` ("Las cuentas administrativas usan el panel web" + botón cerrar sesión).

## 4. Endpoints — son 45 llamadas reales, no 26 como decía la descripción del item

Inventario completo de las 45 llamadas a `_get/_post/_put/_delete` en `lib/services/api_service.dart`,
cruzadas contra las rutas reales del backend (`app/Modules/Addons/MegaFamilia/routes.php`,
grupo `api/megafamilia`):

**42 de 45 SÍ tienen ruta backend correspondiente** (existencia de ruta verificada por grep de
ambos lados; no se auditó la lógica interna de cada controller — eso es fuera de alcance de
este inventario).

**3 de 45 NO tienen ruta backend → siempre resuelven a datos mock:**
| Endpoint llamado por la app | Usado por | Nota |
|---|---|---|
| `GET /servicio` | Mi servicio (plan/velocidad/estado ISP) | Comentario explícito en el código: *"Endpoint específico del servicio ISP... NO existe aún en el backend Laravel"* |
| `GET /payments/clabe` | Pagos → tarjeta CLABE para transferencia | Sin ruta en `routes.php` |
| `POST /payments/notify-transfer` | Pagos → avisar transferencia realizada | Sin ruta en `routes.php` |

**Patrón defensivo (`_tryEndpoint`):** la mayoría de los `GET` intentan el endpoint real primero
y, si falla (por cualquier razón: 404, 500, timeout, sin ruta), caen silenciosamente a
`MockData`. Esto es bueno para UX/demos pero **oculta fallos reales** de un endpoint que sí
existe si responde con error — no hay telemetría visible en la app cuando eso pasa.

## 5. Inventario de pantallas — funcional / parcial / pendiente

Criterio aplicado: **funcional** = pantalla completa, conectada a un endpoint real que existe en
el backend, sin marcadores de incompletitud en el código. **Parcial** = la pantalla funciona
pero tiene una sub-funcionalidad explícitamente marcada como no implementada (comentario/UI
"pendiente"/"Próximamente") o depende de un endpoint sin backend. **Pendiente** = sin ninguna
integración real (datos 100% hardcodeados en el cliente).

### Cliente
| Pantalla | Ruta | Estado | Detalle |
|---|---|---|---|
| Dashboard | `/cliente` | Funcional | |
| Mi servicio | `/cliente/servicio` | **Parcial** | `GET /servicio` no existe en backend → siempre mock |
| Facturas (lista) | `/cliente/facturas` | Funcional | `GET /facturas` real |
| Factura (detalle) | `/cliente/facturas/:id` | **Parcial** | Botón "Descargar PDF" muestra snackbar *"pendiente de implementar en backend"* |
| Pagos | `/cliente/pagos` | **Parcial** | Solo transferencia SPEI activa; resto de métodos con etiqueta "Próximamente"; depende de `/payments/clabe` y `/payments/notify-transfer` (sin backend) |
| Tickets (lista + nuevo) | `/cliente/tickets`, `/tickets/nuevo` | Funcional | `GET/POST /tickets` reales |
| Perfil | `/cliente/perfil` | Funcional | |
| Control parental (lista) | `/cliente/parental` | Funcional | `GET /profiles` real |
| Control parental (detalle hijo) | `/cliente/parental/:id` | **Parcial** | Editor de horarios de bloqueo: *"pendiente de implementar"* (placeholder explícito) |

### Cliente → Flotas
| Pantalla | Ruta | Estado |
|---|---|---|
| Resumen multi-vehículo | `/cliente/flotas` | Funcional (`GET /cliente/flotas/resumen`, `/vehiculos`) |
| Detalle de vehículo | `/cliente/flotas/:vehicleId` | Funcional (documentos/mantenimientos/geocercas/historial reales) |

### Cliente → Embajador
| Pantalla | Estado |
|---|---|
| Prospectos (lista/CRUD/import/seguimiento) | Funcional — todos los endpoints (`/embajadores/prospects*`) existen en backend |
| Red multinivel | Funcional |
| Recompensas | Funcional |
| Historial de comisiones | Funcional |
| Log de notificaciones | Funcional |
| Compartir masivo | Funcional |

### Conductor (Flotas)
| Pantalla | Estado |
|---|---|
| Dashboard (tabs mapa/documentos/mantenimientos/eventos) | Funcional — todos los endpoints `/conductor/*` existen en backend |

### Técnico
| Pantalla | Estado |
|---|---|
| Dashboard | Funcional |
| Órdenes (lista) | Funcional (`GET /tecnico/ordenes`) |
| Orden (detalle) | Funcional |
| Workflow de orden | Funcional |
| Completar orden | Funcional (`PUT /tecnico/ordenes/:id`) |

### Hijo
| Pantalla | Ruta | Estado | Detalle |
|---|---|---|---|
| Dashboard | `/hijo` | Funcional | |
| Tareas | `/hijo/tareas` | Funcional | `GET /hijo/tareas` + `POST /tasks/:id/complete` reales |
| **Logros** | `/hijo/logros` | **Pendiente** | Insignias y puntos son una lista `const` hardcodeada en el widget (`_badges`), **sin ninguna llamada a API** — no refleja progreso real del usuario |
| Solicitar (permiso extra) | `/hijo/solicitar` | Funcional | `POST /requests` real |
| Pantalla bloqueada | `/hijo/blocked` | Funcional | Informativa, no requiere red por diseño |

## 6. Resumen ejecutivo

- **26 pantallas ruteadas** (+ splash/login) repartidas en 5 perfiles + sub-área Embajador.
- **20 funcionales, 5 parciales, 1 pendiente** (Logros del Hijo, 100% mock).
- **3 de 45 endpoints consumidos por la app no tienen ruta backend** (`/servicio`,
  `/payments/clabe`, `/payments/notify-transfer`) — la app original decía 10 pendientes de 26;
  el backend avanzó mucho desde entonces (queda solo Mi Servicio y la parte de pagos con CLABE).
- El dato más importante para decisiones de producto: **hay una migración a React Native ya
  ~95% completa en pantallas** corriendo en paralelo desde hace más de un mes
  (`/var/www/megafamilia-rn`). Cualquier inversión nueva en UI debería evaluarse contra esa app,
  no contra el Flutter aquí auditado.

---
*Generado por el Circuito CC (worker wt-1) el 2026-07-14, en lectura de `/var/www/megafamilia`
(repo independiente, no gitignoreado dentro de `megaisp`, no protegido por la regla de
aislamiento #334 que solo aplica a `/var/www/megaisp`). Sin cambios de código; solo lectura.*
