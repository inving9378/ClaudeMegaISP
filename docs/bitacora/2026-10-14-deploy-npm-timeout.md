## 2026-10-14 21:30 — La V1.59 falló en prod en «Compilar frontend (npm)»

**Síntoma (prod):** «Paso crítico «Compilar frontend (npm)» falló (exit 1). Código revertido a
a0ad7ebd…». Prod estaba en V1.58 + `a0ad7ebd` (fix de facturación hecho en prod).

**Diagnóstico (desde dev, sin acceso a prod):**
- La V1.59 **compila bien**: `npm run prod` en un worktree limpio del tag → exit 0 en 8 min 24 s.
  `package.json`/`package-lock.json` sin cambios desde V1.58 → no es una dependencia nueva.
- El paso tenía un techo **fijo de 600 s** para `npm ci && npm run prod`; `npm ci` reinstala todo
  `node_modules` antes de compilar. `runShell` devuelve **exit 1** cuando Symfony lanza
  `ProcessTimedOutException` → coincide con el mensaje. Pendiente confirmar con la salida del paso
  en prod (debe decir `exceeded the timeout of 600 seconds`).
- Riesgo colateral: al vencer, Symfony solo mataba al `sh`; el webpack podía seguir vivo y escribir
  el JS de la V1.59 **después** del rollback, sobre el código viejo. La limpieza M2 no buscaba
  `mix --production`.

**Cambio (rama `fix/deploy-npm-timeout`):**
- `config/deployment.php`: `npm_timeout` (`DEPLOY_NPM_TIMEOUT`, 1800) y `npm_max_old_space_mb`
  (`DEPLOY_NPM_MAX_OLD_SPACE_MB`, 4096; 0 = no tocar).
- `RemoteDeployCommand::npmBuildCommand()`: `timeout -k 30 <N> sh -c …` (mata al grupo), mensaje
  `TIMEOUT:` si sale 124, `NODE_OPTIONS`, y omite `npm ci` si `package*.json` no cambió y existe
  `node_modules/.package-lock.json` (si aun así falla, reintenta una vez con `npm ci`).
- M2 suma el patrón `[m]ix --production`.

**Verificado en dev:** `php -l`; las 3 variantes del comando (deps sin cambio / con cambio / sin
commit previo); camino de timeout real → exit 124, mensaje `TIMEOUT:`, **cero procesos npm/node
vivos** después; camino completo con 4096 MB → **exit 0 en 434 s**, sin usar el reintento con `npm ci`, sin procesos vivos al terminar.

**⚠️ No llega solo a prod:** prod instala con su `RemoteDeployCommand` VIEJO (ya cargado en memoria
antes del checkout) → la versión que trae este arreglo fallaría en el mismo paso y se revertiría.
Una sola vez, en sesión abierta DIRECTAMENTE en `.108`:
```
cd /var/www/ClaudeMegaISP && git fetch origin
git checkout origin/main -- app/Console/Commands/Active/RemoteDeployCommand.php config/deployment.php
php artisan config:clear
```
(una vez que esta rama esté mergeada y empujada a origin/main) y luego "Buscar actualizaciones". Antes, comprobar que no quede un webpack huérfano del intento
fallido: `pgrep -fa "mix --production|npm run prod"`.
