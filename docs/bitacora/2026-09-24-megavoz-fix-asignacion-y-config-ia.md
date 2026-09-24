## 2026-09-24 15:30 — MegaVoz: 2 pendientes de Irving/David — asignar VoIP a un usuario y guardar la config de María

### 1. Asignar extensión/VoIP a un usuario no creaba su línea del navegador

**Reporte:** "Irving fue a probar con Diana y no le salía el botón de VoIP de llamada" — necesitan
poder dar de alta/asignar extensiones a usuarios desde la pantalla de Extensiones.

**Investigado:** la pantalla YA tenía un selector de usuario en el formulario de alta/edición
(`form.user_id`), y el backend (`ExtensionController::store()/update()`) YA aceptaba asignarlo —
Diana de hecho SÍ tenía la extensión **1003** asignada. El problema real: el mini-teléfono
(`MegaVozTelefono.vue`) "no se dibuja nada si el usuario no tiene una extensión **WebRTC**
asignada" — y esa gemela (`web1003`) **nunca se creaba** cuando la asignación se hacía a mano
desde esta pantalla. Solo el alta AUTOMÁTICA de colaboradores (`ReclamadorExtensionAutomatico`,
Fase 1, solo roles TECNICO*/Vendedor/Mostrador*) creaba la gemela — la propia clase ya
documentaba que el camino manual "vía la pantalla de Extensiones" debía estar soportado, pero
nunca se conectó.

**Fix:** `ReclamadorExtensionAutomatico::crearGemelaWebrtc()` pasó de `private` a `public`
(idempotente, ya lo era) y `ExtensionController::store()/update()` la llaman ahora tras guardar,
siempre que la extensión tenga `user_id` y no sea ella misma una gemela (`es_webrtc=false`).
Best-effort — un fallo no tumba el guardado de la extensión.

**Backfill inmediato:** se creó `web1003` para Diana en el momento (mismo método, sin esperar a
que alguien re-guarde su extensión desde la pantalla) — verificado provisionado en PJSIP
(`allow: (ulaw|alaw|opus)`, `webrtc: yes`, `context: from-internal`), lista para que su navegador
se registre en cuanto abra MegaISP.

### 2. La configuración de María (`/voip/ia-bot`) no guardaba NADA

**Reporte:** "que se pueda guardar la configuración de la asistente de IA que no está guardando
nada nuevo que se le pone."

**Causa real, confirmada reproduciendo el guardado exacto que hace el frontend:** el endpoint
`GET /ia-bot/config` devuelve `horario_inicio`/`horario_fin` tal cual los entrega MySQL —
`"09:00:00"`, **con segundos**. La pantalla (`VoipIaBotManager.vue`) no tenía ningún campo para
esas dos horas — las recibía del GET dentro del objeto `config` y las reenviaba intactas en
**cada** guardado, sin importar qué campo hubiera tocado el usuario. La validación del backend
exige el formato `H:i` (**sin** segundos) → **todo guardado fallaba con 422**, siempre, por esas
dos horas fantasma, nunca por lo que realmente se estaba editando. Reproducido y confirmado con
el propio controller antes del fix:
```
VALIDACION FALLO: {"horario_inicio":["...no corresponde al formato H:i."],"horario_fin":[...]}
```

**Fix backend:** `IaBotController::saveConfig()` normaliza `horario_inicio`/`horario_fin` a
`H:i` (recorta a 5 caracteres si vienen más largos) ANTES de validar — acepta tanto `"09:00"`
como `"09:00:00"`, sin importar qué formato mande el cliente. Reproducido el mismo guardado tras
el fix: `status=200`, el campo editado se guarda correctamente.

**Fix frontend (de paso, faltaba también):** `VoipIaBotManager.vue` no tenía NINGÚN control para
`piloto_porcentaje`/`horario_inicio`/`horario_fin` — el único %que Irving/David pudieron mover
hoy fue porque Claude lo cambió a mano por `tinker` durante las pruebas en vivo de Fase 6. Se
agregó una sección "Piloto" con slider de 0-100% y dos `<input type="time">` para el horario de
oficina, más normalización defensiva en `loadConfig()` (recorta segundos antes de mostrar en los
inputs nativos de hora).

### Verificado

- `npm run dev` compila sin errores.
- Guardado simulado end-to-end (mismo payload exacto que produce el bug) → `200 OK`, cambio
  persistido.
- `web1003` de Diana provisionada y visible en `pjsip show endpoint web1003`.

### Estado dejado

`piloto_porcentaje` sigue en 100% (de las pruebas de la sesión anterior) — ahora Irving/David
pueden bajarlo/subirlo ellos mismos desde la pantalla, sin depender de que alguien lo cambie por
consola.
