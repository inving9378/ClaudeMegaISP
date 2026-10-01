## 2026-10-01 16:30 — Login móvil de Talento: no reconocía contraseñas ya migradas a bcrypt

### Origen

David preguntó "de dónde saca los datos de los usuarios?" sobre la app móvil, en medio de seguir
depurando el problema de login. Al explicar el flujo de autenticación (misma tabla `users` del
panel web, misma cuenta/contraseña, requiere un perfil activo en `talento_colaboradores`), se
revisó con cuidado el código de verificación de contraseña y se encontró un segundo bug real,
independiente del fix del bundle de JS congelado.

### Causa raíz

`TalentoMobileApiController::login()`:

```php
if (! $user || $user->password !== base64_encode($request->password)) {
    return response()->json(['message' => 'Credenciales incorrectas.'], 401);
}
```

Esto compara directo contra el esquema **legacy** (`base64_encode`). Pero el login del panel web
(`LoginController::attemptLogin()`) hace tiempo que usa `PasswordService::check()` — un verificador
**híbrido** que acepta tanto bcrypt como el legacy base64 — con **upgrade-on-login**: la primera vez
que alguien entra bien al panel admin, su contraseña se re-hashea a bcrypt automáticamente
(`password_legacy` guarda el valor anterior, `password_migrated_at` sella la fecha, para poder
revertir si hiciera falta).

**Verificado en dev** contra 10 cuentas de staff reales (muestra de técnicos y vendedores,
incluyendo Diana, Tere, Irving, Isaac, Rene, Guadalupe): **las 10 ya están en formato bcrypt**. Con
la comparación vieja del login móvil, **ninguna cuenta real podía entrar a la app**, aunque
escribiera la contraseña exactamente correcta — habría sido el siguiente bloqueo justo después de
resolver el problema de conexión/bundle de JS de hoy.

### Fix

```php
if (! $user || ! PasswordService::check($request->password, $user->password)) {
    return response()->json(['message' => 'Credenciales incorrectas.'], 401);
}

if (PasswordService::needsRehash($user->password)) {
    $user->password_legacy = $user->password;
    $user->password = PasswordService::make($request->password);
    $user->password_migrated_at = now();
    $user->saveQuietly();
}
```

Mismo patrón exacto que el login web — no se inventó un mecanismo nuevo, se replicó el ya existente
y probado.

### Verificación

2 casos sintéticos, en transacción revertida (usuario de prueba creado y destruido, **sin tocar
ninguna cuenta real** — ninguna contraseña real se cambió ni se intentó adivinar):

- **Contraseña ya en bcrypt** (el estado real de todo el staff hoy): acepta correctamente, no
  vuelve a reescribir nada (ya estaba migrada).
- **Contraseña todavía en legacy base64** (hipotético, para una cuenta que nunca se ha logueado al
  panel web): acepta correctamente Y migra a bcrypt con el rastro de auditoría completo
  (`password_legacy` = valor anterior, `password_migrated_at` sellado).
- En ambos casos, una contraseña **incorrecta** se sigue rechazando.

No requiere recompilar el APK — es un cambio de backend puro, el cliente móvil no cambia.

### Commit

`f80324b6` en la rama `fix/talento-mobile-login-password-hibrido` (desde `main`). Archivo:
`app/Modules/Addons/Talento/Controllers/TalentoMobileApiController.php`.
