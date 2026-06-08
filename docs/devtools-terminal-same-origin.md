# DevTools Terminal — Same-Origin + Copiar al Seleccionar

**Fecha:** 2026-06-08  
**Commit:** pendiente (ver abajo)

---

## Causa raíz

El `<iframe>` de ttyd apuntaba a `http://<host>:7681` (puerto diferente al 80 de la app), lo que hace que el navegador lo trate como **cross-origin**. Eso bloqueaba:

- Acceso a `iframe.contentWindow.term` (la instancia de xterm.js que expone ttyd)
- Cualquier lectura de selección o buffer desde el padre Vue
- El botón "Copiar terminal" solo enfocaba el iframe y mostraba "Ctrl+Shift+C" (atajo reservado en Chrome → abre DevTools del navegador)

---

## Solución aplicada

### 1. Nginx proxy `/ttyd/` (ya existía, no se modificó)

`/etc/nginx/sites-available/megaisp.conf` ya tenía:

```nginx
location /ttyd/ {
    proxy_pass         http://127.0.0.1:7681/;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade          $http_upgrade;
    proxy_set_header   Connection       "upgrade";
    proxy_set_header   Host             $host;
    proxy_set_header   X-Real-IP        $remote_addr;
    proxy_set_header   X-Forwarded-For  $proxy_add_x_forwarded_for;
    proxy_read_timeout 86400s;
}
```

Los headers `Upgrade` y `Connection` son imprescindibles para que el WebSocket de ttyd no se corte.

### 2. Controller: `DevToolsController::resolveTtydUrl()`

Antes devolvía `http://<host>:7681` (cross-origin). Ahora devuelve `/ttyd/` (mismo origen).

```php
// Antes
$host = request()->getHost();
return "http://{$host}:7681";

// Ahora
return '/ttyd/';
```

El override `TTYD_URL` del `.env` sigue funcionando para entornos donde ttyd corre en otro host.

### 3. Vue: `DevtoolsPanel.vue`

**`effectiveTtydUrl`** — simplificado a devolver la prop directamente (el controller ya resuelve la URL correcta):

```js
const effectiveTtydUrl = computed(() => props.ttydUrl);
```

**`hookCopyOnSelect`** — una vez que el iframe carga, espera hasta que `window.term` esté disponible y registra el listener de selección:

```js
term.onSelectionChange(() => {
    const sel = term.getSelection();
    if (sel) navigator.clipboard.writeText(sel).catch(() => {});
});
```

Resultado: **cualquier texto seleccionado en la terminal se copia automáticamente al portapapeles** sin ningún gesto extra. No depende de Ctrl+Shift+C (que Chrome reserva para su inspector).

**`copyTerminal`** (botón "Copiar todo") — ahora usa la API de xterm:

```js
term.selectAll();
const text = term.getSelection();
term.clearSelection();
await navigator.clipboard.writeText(text);
```

Copia todo el scrollback buffer de la terminal de un clic.

---

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Modules/Addons/DevTools/Controllers/DevToolsController.php` | `resolveTtydUrl()` → `/ttyd/` |
| `resources/js/components/module/devtools/DevtoolsPanel.vue` | `effectiveTtydUrl`, `onIframeLoad`, `hookCopyOnSelect`, `copyTerminal`, template botón |
| `/etc/nginx/sites-available/megaisp.conf` | **Sin cambios** (proxy ya existía) |

---

## Verificación

1. Abrir `/devtools` en el navegador
2. La terminal carga vía `/ttyd/` (verificar en DevTools de red: iframe src debe ser `/ttyd/`, no `http://...:7681`)
3. Seleccionar texto en la terminal → se copia solo (pegar en cualquier campo con Ctrl+V)
4. Clic en botón "Copiar todo" → muestra "Copiado ✓" → pegar en un editor confirma el buffer completo
5. Las demás pestañas y el chat Claude funcionan igual
6. WebSocket de ttyd: la terminal responde comandos normalmente (verificar `ls`, `echo hola`)

---

## Por qué funciona el WebSocket a través del proxy

ttyd usa WebSocket (`ws://`) para el flujo bidireccional shell↔navegador. Cuando el navegador hace el handshake WS desde `/ttyd/ws`, nginx lo reenvía a `http://127.0.0.1:7681/ws` con los headers `Upgrade: websocket` y `Connection: upgrade`. El `proxy_read_timeout 86400s` (24h) evita que nginx corte sesiones largas.

---

## Limitaciones eliminadas

- ~~"Copiar todo el buffer" imposible por cross-origin~~ → **resuelto**
- ~~Ctrl+Shift+C abre inspector Chrome en lugar de copiar~~ → **resuelto** (ya no se necesita)
- ~~`allow="clipboard-write clipboard-read"` en iframe como workaround parcial~~ → **sigue presente pero innecesario para la selección**; no causa daño, se puede dejar
