# #1213 — `users.password` vs «Contraseña WEB» (`client_main_information.password`)

Solo inventario y opciones. **No se cambió código ni datos.** La decisión es de Irving.

## 1. Hoy existen tres almacenes de contraseña por cliente

| Almacén | Formato | Quién lo usa |
|---|---|---|
| `users.password` (cuenta espejo rol `client`) | bcrypt (535) o base64 legacy (4449) de 4984 (medido en dev 2026-11-09) | Login admin (`LoginController`, vía `PasswordService::check`, rehash al vuelo), Talento API móvil, MegaFamilia API |
| `client_main_information.password` («Contraseña WEB» de la ficha) | **texto plano** (0 bcrypt); 913 de 5854 vacías | Login del Portal Cliente (`AuthController::login`, `hash_equals`); registro/recuperar/OTP la escriben |
| `client_main_information.web_password_hash` | bcrypt, **0 filas llenas** en dev | Portal: se escribe en registro/OTP/login pero el login solo lo lee si `password` está vacío |

Más `portal_password` (bcrypt, legacy, sin uso en login).

## 2. Puntos de sincronía (o falta de ella)
- `ClientController::createNewUserRoleClient` copia `CMI.password` → `users.password` (hasheado) **solo al crear** el espejo; luego divergen.
- Cambiar la clave en el Portal (registro, recuperar, OTP) escribe solo `CMI.password` (+ `web_password_hash`); **no toca `users.password`**.
- Cambiar `users.password` (UserController) no toca `CMI.password`.
- Comparación de muestra (3000 filas con espejo): 1183 no coinciden entre ambos almacenes → ya divergieron.
- 922 CMI no tienen `users` espejo (join por `login_user = CMI.user`); `BackfillClientUsers/OrphanClientUsers` existen como comandos manuales.
- Contraseñas en claro en BD: cualquiera con lectura de `client_main_information` ve todas las claves del portal; el personal las ve en la ficha.

## 3. Opciones (de menos a más cambio)
- **A. Dejar separados, documentar.** Cero riesgo; mantiene texto plano y la divergencia. Es lo actual.
- **B. Portal migra a bcrypt, la ficha deja de mostrarla.** Login portal compara contra `web_password_hash` (ya existe la columna y el código la escribe); backfill hashea las claves actuales; `password` en claro se vacía tras un periodo de gracia. Rompe: la ficha ya no puede mostrar «Contraseña WEB» ni soporte puede dictarla (habría que ofrecer «resetear» en su lugar). Reversible hasta vaciar la columna.
- **C. Fuente única = `users.password` (bcrypt).** El portal autentica contra el espejo `users` (hay que cerrar los 922 sin espejo y la unicidad de `login_user`); `CMI.password` se elimina. Más limpio, pero toca auth admin/portal/Talento/MegaFamilia a la vez y el vínculo CMI↔users por `login_user` (frágil, ver ids dev≠prod).
- **D. Sincronizar en escritura (doble escritura).** Cada cambio en cualquiera de los dos actualiza el otro. Parche chico, pero consolida el texto plano.

## 4. Recomendación (no ejecutada)
B primero (ya hay columna y escritura a `web_password_hash`), C como destino de largo plazo. Antes de B: decidir qué hace soporte cuando el cliente olvida la clave (hoy la lee de la ficha). Todo lo anterior toca autenticación → frontera dura: requiere decisión de Irving.
