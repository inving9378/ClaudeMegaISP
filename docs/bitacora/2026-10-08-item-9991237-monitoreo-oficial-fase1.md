## 2026-10-08 17:35 — Monitoreo Oficial, Fase 1: retomada de otra terminal, verificada y mergeada a main

### Contexto

El usuario preguntó "cómo va la fase 1 del módulo de avisos" → aclaró "es un módulo de monitoreo
oficial" → encontrado en la Hoja de Ruta: item #9991237 "Monitoreo Oficial — Fase 1: estructura
modular portable + candados de seguridad", hijo de la épica #9991236 ("MÓDULO Monitoreo Oficial y
Alertas Inteligentes", 8 fases planeadas). Luego pidió explícitamente: "quiero que tú des
continuidad... continúes haciendo las fases o cerrando lo que dejaron pendiente".

### Qué se encontró

Otra terminal había construido el módulo completo en el worktree compartido `/var/www/megaisp-cc`
(actualmente en una rama de Talento no relacionada) como archivos **sin commitear** — riesgo real
de perderse si alguien corría `git clean`/cambiaba de rama ahí.

### Qué se hizo

1. **Verificación independiente, archivo por archivo**, antes de confiar en el autorreporte del
   item: los 17 archivos PHP pasan `php -l`; las clases que referencia (`WhatsAppGateway`,
   `IA::configurada()`, `RolInstancia`, `ModuleDefinition` base) existen de verdad en el repo —
   nada inventado; el id de módulo (260) y el slug (`addon-monitoreo-oficial`) no chocan con nada
   existente.
2. Movido a una rama propia del repo principal (`circuito/item-9991237-...`) y commiteado
   (`5eca4220`).
3. **Instalado de verdad** con `php artisan module:lifecycle install addon-monitoreo-oficial`
   (pasó el guardrail de migraciones porque la rama sigue la convención `circuito/item-N-...`).
4. **Los 2 candados de seguridad que el item afirmaba, verificados EN VIVO, no solo leídos:**
   - La migración que asegura la tabla compartida `notifications` tiene `down()` vacío a
     propósito. Se probó con una desinstalación real (`--keep-data`): la tabla **no se tocó**.
   - El expediente de ejemplo de MEGANET (marca "MEGANETMX TE CONECTA" ante el IMPI, expediente
     2806655) **no se sembró solo** en esta base de datos — confirmado que esta instancia de dev
     está marcada como "cliente", no "operador", y la tabla de expedientes quedó en 0 filas tras
     el install. Exactamente el comportamiento que el candado promete.
5. **Ciclo completo probado real**: instalar → previsualizar desinstalación → desinstalar
   (conservando datos) → reinstalar. En cada paso se verificó el estado real de las tablas, no
   solo que el comando no tronara.
6. **Validación visual en navegador** (cuenta de prueba, creada y borrada): `/monitoreo-oficial`
   y `/monitoreo-oficial/configuracion` cargan completos, sin errores, aparece en el menú lateral;
   la pantalla de configuración avisa honestamente "se habilita en la Fase 3" en vez de fingir
   funcionalidad que todavía no existe.
7. Mergeado a `main` (commit `8dc91c37`).

### Nota sobre el cierre formal en la Hoja de Ruta

El item tenía la marca `en_desarrollo_humano` activa, que bloquea el cierre automático por el
camino del circuito (`circuito:rama`/`circuito:integrar`) a propósito — es el candado que evita
que el sistema automático le quite el control a un humano que ya está encima de un item. Se
respetó: el trabajo se mergeó por el mismo camino manual que se usó para todo lo demás hoy (rama
propia → verificación → `git merge` directo), no se forzó el camino automatizado. El item quedó
con un comentario detallado de todo lo verificado.

### Pendiente (fases siguientes de la épica #9991236, fuera de esta Fase 1)

F2 datos, F3 fuentes + PDF manual (la propia pantalla de configuración ya avisa que se habilita
ahí), F4 scheduler/colas, F5 IA, F6 notificaciones, F7 UI, F8 validación.

### Nota operativa — limpieza pendiente, no urgente

La copia original sin commitear sigue en `/var/www/megaisp-cc/app/Modules/Addons/MonitoreoOficial/`
(untracked, en la rama `feature/talento-app-credenciales-certificacion-rutas-darkmode`). Ya no se
necesita — el trabajo real vive en `main` — pero no se borró desde aquí por no ser una rama/worktree
propio de esta sesión. Quien use ese worktree después puede limpiarlo con confianza.
