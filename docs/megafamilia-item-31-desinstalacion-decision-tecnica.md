# Item #31 — Control parental: impedir desinstalación sin autorización del padre

**Decisión técnica del enfoque.** El título del propio item lo enmarca así ("decisión técnica del
enfoque cuando llegue su turno"): este documento es el entregable — la implementación real vive en
`/var/www/megafamilia-rn` (React Native), un repo **separado y fuera del mandato de este circuito**
(ya establecido por la actualización del checklist del 2026-08-26, `docs/megafamilia-checklist-actualizacion-2026-08-26.md`,
punto 4: *"Este repo NO es parte del worktree/mandato de la circuito on-box de megaisp"*). No hay
código Android/RN en `megaisp` que tocar para este item.

## 1. Contexto verificado (no supuesto)

- `parental_devices` (`app/Modules/Addons/MegaFamilia/migrations/2026_05_16_000004_create_parental_devices_table.php`)
  ya tiene `os` como `enum('android','ios')` — **la app es multiplataforma**, no solo Android.
  Esto es determinante para la decisión (§3).
- `/var/www/megafamilia-rn/package.json`: sin ninguna librería de device admin/MDM instalada hoy
  (`react-native-device-info` es solo lectura de metadatos del dispositivo, no control). Cero
  código existente de protección de desinstalación (grep en `src/` y manifiestos: sin resultados
  reales, solo coincidencias del texto "uninstall" en artefactos de build de Proguard).
- El checklist de julio (`docs/megafamilia-checklist-clasificado-2026-07-15.md`, fila 2.5
  "Protección de desinstalación") ya la clasificó como 🔴 bloqueada por el vínculo padre-hijo
  (#29/#32). **#29 ya se completó** (2026-08-26, commit `a87004aa`): existe
  `POST /profiles/{id}/invite` + `POST /devices/link` con `link_token` expirable. **#32 (panel del
  padre) sigue sin código**, `requiere_irving`. Protección de desinstalación no depende
  estrictamente de #32 para decidirse (este documento), pero SÍ para que el padre tenga una
  pantalla donde activarla/desactivarla — ver §5.
- No existe hoy ningún mecanismo de "PIN del padre" o segundo factor en el backend para autorizar
  una acción sensible de la app hijo (los `parental_consents` existentes son de **términos y
  condiciones**, no un PIN operativo).

## 2. Las tres opciones evaluadas

### A. Device Admin API (`DevicePolicyManager` + `DeviceAdminReceiver`)
- Se activa por el usuario (el padre, en el teléfono del hijo) desde Ajustes → una pantalla del
  sistema pide "Activar esta app como administrador del dispositivo". **No requiere factory
  reset ni provisioning especial** — funciona sobre un teléfono ya en uso, instalado por Play
  Store o APK directa, exactamente como se instala hoy la app.
- Efecto concreto y verificable: mientras el admin está activo, Android **deshabilita el botón
  "Desinstalar"** en Ajustes → Apps para esa app (el usuario ve la opción en gris). Para
  desinstalarla, primero hay que desactivar el admin desde Ajustes → Seguridad → Administradores
  de dispositivo — paso que la propia app puede interceptar mostrando su pantalla de bloqueo antes
  de ceder el control (ver §4).
- Reversible en cualquier momento por el padre; no compromete otras apps ni el dispositivo.
- Es la API que Android **deprecó parcialmente en apps nuevas orientadas a Play Store por abuso
  histórico en malware/ransomware**, pero sigue siendo la vía estándar y soportada para apps de
  control parental legítimas (Google la permite explícitamente bajo la política de "Apps de
  seguridad familiar" del Play Console).

### B. Device Owner (provisioning completo)
- Requiere que el dispositivo esté **sin configurar** (recién salido de fábrica o tras
  `factory reset`) y se aprovisione vía código QR, NFC o zero-touch **antes** de la primera
  configuración de la cuenta Google. Una vez el teléfono ya tiene cuenta y apps, **no se puede
  convertir a Device Owner sin borrar el dispositivo**.
- Da control mucho más fuerte (impide desinstalación de forma incondicional, bloquea factory
  reset, permite kiosk mode), pero el costo de adopción es altísimo para este producto: el
  público objetivo son teléfonos de hijos **ya en uso**, no equipos nuevos que Meganet distribuye
  de fábrica. Pedirle a cada familia que resetee el teléfono del hijo para activar control
  parental es una barrera de fricción y soporte que mata la adopción.
- Encaja en escenarios de "dispositivo dedicado/corporativo" (kioscos, flotas de tablets
  distribuidas por la empresa) — no en el patrón BYOD de esta app.

### C. MDM / Android Enterprise (Work Profile o Fully Managed Device)
- Mismas restricciones de provisioning que Device Owner (requiere enrolamiento gestionado,
  típicamente vía una consola EMM/UEM y registro en la Android Management API de Google), más la
  complejidad de operar un backend EMM propio o integrarse con uno de terceros.
- Pensado para flotas corporativas administradas centralizadamente, no para que un padre active
  control sobre el celular personal de su hijo con un clic. Sobra para el caso de uso.

## 3. Restricción que ninguna opción de Android resuelve: iOS

`parental_devices.os` acepta `ios`. **Apple no expone ninguna API a apps de terceros para impedir
la desinsalación de la propia app** — ni siquiera con Supervised Mode (eso requiere que el
dispositivo esté inscrito en Apple Business/School Manager y gestionado por un MDM, lo cual no
aplica a un iPhone personal de un menor que la familia ya posee). La única superficie de Apple
para esto es **Screen Time / Family Sharing**, controlada por Apple, no por la app.
**Implicación:** la protección de desinstalación es, y seguirá siendo, **Android-only**. En iOS la
mitigación tiene que ser de producto (notificar al padre cuando el hijo desinstala, en vez de
impedirlo técnicamente) — esto no es una laguna de esta decisión, es un límite de la plataforma.

## 4. Recomendación

**Device Admin API (opción A), con "desactivación gateada por autorización del padre" construida
en la propia app**, no dejar la desactivación como el flujo nativo de Android sin más:

1. Al vincular el dispositivo del hijo (`POST /devices/link`, ya existe), la app ofrece activar
   protección de desinstalación → dispara el prompt nativo de `DevicePolicyManager` para volverse
   administrador del dispositivo.
2. La app hijo intercepta el intento de abrir Ajustes → Administradores de dispositivo (o, más
   simple y robusto: no lo intercepta — deja que Android muestre su pantalla nativa de
   desactivación, que ya es fricción suficiente para un menor sin acceso al panel del padre).
   Lo que sí construye la app es: **antes de dejar que el usuario llegue a esa pantalla desde
   dentro de la propia app** (si se ofrece un botón "Desactivar protección" en la UI de la app),
   exigir el PIN/contraseña del padre contra el backend antes de invocar
   `DevicePolicyManager.removeActiveAdmin()`.
3. Distribución: **sin impacto** — funciona igual instalada por Play Store o por APK directa, no
   cambia el plan de distribución vigente.
4. Reversibilidad: total. No compromete el dispositivo, no requiere reset, el padre puede
   desactivarlo desde el panel (cuando exista, #32) revocando el estado en backend y notificando
   a la app para que se autolibere.

**Device Owner y MDM se descartan para esta fase** por el costo de adopción (exigir factory reset)
frente al beneficio marginal sobre Device Admin para este caso de uso — quedan documentados aquí
por si en el futuro Meganet decide vender/distribuir equipos ya preconfigurados para el hijo (otro
modelo de negocio, decisión aparte).

## 5. Qué falta para poder construirlo (fuera de alcance de este item)

Trabajo real de implementación, todo fuera de este repo o pendiente de otra decisión:
- **Cliente móvil** (`megafamilia-rn`, repo separado, fuera del mandato del circuito on-box de
  `megaisp`): `DeviceAdminReceiver` nativo Android (vía código nativo o librería RN tipo
  `react-native-device-admin` — no evaluada en profundidad, evaluar al implementar), pantalla de
  activación/desactivación, pantalla de PIN del padre.
  Sobre iOS-only: **sin desarrollo** — solo notificar (ver §3).
- **Backend `megaisp`** (aditivo, no incluido en esta vuelta porque no hay UI/flujo que lo
  consuma todavía): endpoint para verificar el PIN/contraseña del padre antes de autorizar la
  desactivación (p. ej. `POST /devices/{id}/authorize-uninstall-protection-change`, reusando
  Sanctum + `PasswordService::check()` como ya hace el login, `ApiController.php:135`), y un
  flag en `parental_devices` (p. ej. `uninstall_protection_enabled`) para que el panel del padre
  (#32) pueda ver/gestionar el estado.
- **#32 (panel del padre)**: sigue `requiere_irving`, sin código — es donde el padre vería y
  gestionaría esta protección desde la app. No bloquea *decidir* el enfoque (este documento), sí
  bloquea *operar* la función end-to-end.

No se abre un sub-item de la Hoja de Ruta para la implementación: el trabajo vive en un repo fuera
del mandato de este circuito (megafamilia-rn) y depende de una decisión de Irving aún pendiente
(#32), tal como ya lo documentó la actualización del 2026-08-26 para el resto del checklist de
MegaFamilia. Queda registrado aquí para cuando llegue su turno, como pedía el título del item.
