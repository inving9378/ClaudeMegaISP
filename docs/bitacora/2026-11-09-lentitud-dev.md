## 2026-11-09 22:20 — Diagnóstico y arreglo parcial de la lentitud de dev (sesión de Irving)

Pedido de Irving en sesión: "diagnostica y repara". Respuestas suyas: lento sobre todo en Mapas y Mapa de
Red (un poco Clientes), para todos (otras PC, también ayer), igual a todas horas. En paralelo corre el item
#1242 (diagnóstico de solo lectura de todo el servidor, terminal wt-2); sus respuestas quedaron en el item.

### Mediciones (dev .11, 21:54–22:15)
- **CPU**: 4 núcleos, carga 9.9 / 11.0 / 13.1, idle 1–4 %. `mpstat -P ALL`: **%steal 38–42 % en los 4
  núcleos** (CPU que el hipervisor no le da a la VM). iowait 0–0.25 %. RAM: 8.3 GB disponibles de 21.5;
  swap 1.4 GB usados de 2 GB.
- **Builds del circuito**: 3 webpack a la vez (principal: `npm run prod` del rebuild post-merge vía
  `flock rebuild-post-merge.lock` → `deploy/circuito/npm-build.sh`; wt-3 y wt-4 en sus worktrees), uno
  con 4.6 GB RSS. Últimos 12 builds de producción post-merge: 7.3–27.4 min. Mezclas del circuito: 3 el
  11-07, 0 el 11-08, 21 el 11-09 (cada una dispara un build de producción).
- **PHP-FPM**: `pm = dynamic`, `pm.max_children = 5`. Prueba `/login`: secuencial mediana 0.12 s;
  12 simultáneas → mediana 0.67 s, peor 1.17 s (fila detrás de 5 procesos). El log de FPM es root-only.
- **Pantallas** (Playwright, usuario temporal DESARROLLADOR, 2 vueltas): /mapa-red 6.2–6.3 s, /mapas
  4.9–6.4 s, /cliente/listar 6.2–6.4 s de carga completa; solo el HTML 1.4–2.4 s de TTFB. Assets de
  Debugbar servidos por PHP: 0.5–1.8 s cada uno (Debugbar solo aplica al rol DESARROLLADOR).
- **Render en kernel** (CLI): /mapa-red 615 ms con Debugbar / 255 ms sin ella (287 KB vs 97 KB).
- **storage/debugbar** (2,017 peticiones reales de DESARROLLADOR, 06:56–21:58): /mapa-red mediana
  1.7 s con 86 ms de BD; /mapa-red/api/projects 2.6 s; /cliente/listar 1.9 s;
  **/get-stats-finance-card-in-dashboard: 40 s con 26,390 consultas**.
- **Frontend**: `app.js` 16.3 MB servido **sin gzip** (nginx tiene `gzip on` pero `gzip_types`
  comentado → solo HTML), sin Cache-Control; `?v=filemtime` cambia en cada build.

### Causas, por evidencia
1. CPU: ~40 % robada por el hipervisor + hasta 3 builds de webpack del circuito compitiendo con PHP.
2. PHP-FPM con 5 procesos: fila medida con concurrencia.
3. Frontend: 16 MB de JS sin comprimir, re-descargado tras cada mezcla.
Descartado: disco (iowait ~0), BD en las pantallas de mapas (86 ms de 1.7 s).

### Aplicado (decisión de Irving: solo "compilar en baja prioridad", directo a main)
- `deploy/circuito/npm-build.sh`: `nice -n 19 ionice -c 3 npm run …` (PHP le gana la CPU al build) y
  fallback de ranuras 3 → 1.
- `config/circuito.php`: `max_builds` por defecto 3 → 1 (no hay `CIRCUITO_MAX_BUILDS` en `.env`;
  `circuito:flags` ya devuelve `max_builds=1`; no hay config:cache en dev).
- Riesgo asumido: con 1 ranura, una vuelta (600 s) que espera tras un build de producción post-merge
  puede cortarse por timeout. Revertir/ajustar sin redeploy: `CIRCUITO_MAX_BUILDS=2` en `.env`
  (cuidando el grupo `www-data` del archivo). Revertir todo: `git revert` del commit.
- Ojo: las terminales llaman `bash deploy/circuito/npm-build.sh` en SU worktree → toman el cambio cuando
  su rama parte del main nuevo; el rebuild post-merge usa el del principal → efecto inmediato.

### Propuesto, NO aplicado (Irving no lo eligió hoy)
- nginx: `gzip_types` para JS/CSS/JSON (16 MB → ~3–4 MB); se puede hacer con el sudo permitido
  (`tee /etc/nginx/sites-enabled/*`, `nginx -t`, `reload`).
- PHP-FPM `pm.max_children` 5 → ~12 (necesita root: script para que Irving lo corra con sudo).
- Item para la tarjeta de finanzas del dashboard (40 s / 26,390 consultas).
- Steal ~40 %: revisar en el hipervisor (más vCPU para la VM o menos carga en el host).

### Limpieza
Usuario temporal `tmpperf_admin` y su sesión borrados al terminar.

## 2026-11-09 22:35 — Compresión gzip de nginx aplicada (pedido de Irving)
- Instalado el drop-in versionado `deploy/nginx-gzip-dev.conf` (item #9991136, preparado en septiembre y
  nunca instalado) en **`/etc/nginx/sites-enabled/00-gzip-dev.conf`** — no en `conf.d/` como dice el
  runbook, porque `meganet` solo tiene sudo para `tee` en `sites-enabled/`; nginx lo incluye en el mismo
  contexto `http`, justo después. `sudo nginx -t` OK → `sudo systemctl reload nginx` (sin corte).
- Resultado (curl con `Accept-Encoding: gzip`): `app.js` 16,274,606 → **3,634,456 bytes** (−78 %);
  `quasar.prod.css` 190,978 → 36,616 bytes. Sin `Accept-Encoding` sigue sirviendo sin comprimir.
  /login 200, /mapa-red 302 (sin sesión), :8081 200.
- Costo: la compresión es al vuelo (esta versión de nginx no trae `gzip_static`). Comprimir `app.js`
  cuesta ~0.75 s de CPU (2 s medidos con 49 % de steal) y solo ocurre en la PRIMERA descarga de cada
  build: las visitas siguientes son 304 (0 bytes, 4 ms) por `Last-Modified`/`ETag`. Nivel 1 no conviene
  (0.59 s y 18 % más grande).
- Revertir: `sudo tee /etc/nginx/sites-enabled/00-gzip-dev.conf` con solo comentarios (o borrarlo como
  root) → `sudo nginx -t` → `sudo systemctl reload nginx`.
- Mejora posible (no aplicada): servir `.gz` precomprimidos generados en el build (requiere nginx con
  `gzip_static` o el plugin de compresión en webpack, cuidando que nunca quede un `.gz` viejo).
