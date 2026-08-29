# Item #633 — Cola 'default' sin worker en DEV (RESUELTO — se resolvió solo antes de tomarlo)

## Contexto

El item #633 (sub-item de seguimiento de #186) reportaba que, verificado el 2026-08-26 ~22:00,
la cola `default` de Laravel Queue tenía **579 jobs atorados** (2+ días sin drenar) porque no
había ningún proceso `queue:work`/`queue:listen` corriendo (`pgrep -fa` vacío) y la terminal no
tenía `sudo` para leer `supervisorctl status` ni recargar el conf de supervisor
(`/etc/supervisor/conf.d/megaisp-queue.conf`, ya existente pero no cargado). Consecuencia real
verificada entonces en el código: `RoadmapItem.php` (`ClasificarRiesgoJob::dispatch()->afterCommit()`)
y `RevisorService.php` (`ProponerOpcionesJob::dispatch()`) van a la cola `default` sin cola propia
→ los items nuevos del Roadmap se quedaban sin clasificar/sin opciones de decisión.

Irving aprobó el item el 2026-08-28 15:14 (`aprobado_irving`), eligiendo para las 3 preguntas del
brief la opción recomendada en cada una: (q1) levantar worker permanente vía systemd/supervisor,
(q2) filtrar/descartar jobs con side-effects externos y procesar solo los internos, (q3) registrar
el worker en el mecanismo de supervisión ya existente del box con `autorestart`.

## Hallazgo: la cola ya se drenó y el worker ya está corriendo bajo supervisor, ANTES de tomar el item

Al reclamar el item (2026-08-28 22:27, `wt-3`) y verificar el estado real del box:

```
$ php artisan tinker --execute='echo \DB::table("jobs")->count();'
1   # (era 579 el 26-ago; hoy solo queda 1, un MonitorCampaignJob con delay legítimo de 3 min)

$ ps aux | grep queue:work
www-data  1672  ...  php /var/www/megaisp/artisan queue:work --queue=cobranza,referrals,database,default ...
www-data  1673  ...  php /var/www/megaisp/artisan queue:work --queue=cobranza,referrals,database,default ...

$ ps -o pid,ppid,cmd -p 1672
PPID 769 → /usr/bin/python3 /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf

$ stat -c '%y' /var/run/supervisor.sock
2026-08-28 20:55   # supervisord se (re)arrancó hoy
```

Los 2 procesos `queue:work` son hijos directos de `supervisord` (pid 769), confirmando que están
**supervisados** (no procesos sueltos): heredan `autostart=true`/`autorestart=true` del propio
`megaisp-queue.conf` que ya existía desde junio. Llevan corriendo desde las 20:56 de hoy (~1h32min
al momento de esta verificación), y el log de actividad (`/var/log/supervisor/megaisp-queue-worker-{1,2}.out.log`)
muestra procesamiento continuo y sano desde entonces, **incluyendo exactamente los jobs que el
item reportaba bloqueados**:

```
2026-08-28 22:12:29  App\Modules\Addons\Roadmap\Jobs\ClasificarRiesgoJob  RUNNING → DONE (67.62ms)
2026-08-28 22:14:12  App\Modules\Addons\Roadmap\Jobs\ProponerOpcionesJob  RUNNING → DONE (30s)
2026-08-28 22:16:18  App\Modules\Addons\Roadmap\Jobs\ProponerOpcionesJob  RUNNING → DONE (30s)
2026-08-28 22:20:11  App\Modules\Addons\Roadmap\Jobs\ProponerOpcionesJob  RUNNING → DONE (30s)
```

Es decir: alguien con acceso de administrador al box (Irving, único con `sudo` — la propia
consulta_opciones del item lo señalaba como acción suya) ya corrió el
`supervisorctl reread && update && start` recomendado por el brief, en algún momento entre el
26-ago (cuando se detectó el atasco con 579 jobs) y hoy 20:55-20:56 (cuando aparece el socket de
supervisor recién creado y los 2 workers arrancan). El worker drenó el backlog completo por su
cuenta antes de que este item fuera reclamado.

## Respuesta a las 3 preguntas ya aprobadas por Irving, verificadas contra el estado actual

- **q1 (destrabar la cola):** ✅ ya resuelto — worker permanente vía supervisor, corriendo.
- **q3 (prevenir que vuelva a pasar):** ✅ ya resuelto — el worker está bajo el `[group:megaisp-queue]`
  de `/etc/supervisor/conf.d/megaisp-queue.conf` con `autorestart=true` (sobrevive crashes; sobrevive
  reinicios del box si supervisord arranca por systemd, que es el patrón estándar de este stack —
  no se tocó ni se necesitó tocar ese conf, ya estaba correcto desde junio).
- **q2 (jobs con side-effects al reprocesar tras 2+ días):** el worker ya procesó los 579 tal cual
  venían en la cola (no se filtró nada porque para cuando se verificó ya no había nada que filtrar).
  El log muestra 2 `SendOutboundMessageJob` con `FAIL` (22:12:29) — es el único indicio de un
  side-effect real, y es un fallo de envío (no un envío duplicado exitoso), consistente con jobs
  de marketing viejos fallando limpio en vez de disparar algo indeseado. No hay evidencia de daño.
  `failed_jobs` tiene 35 filas (baja desde las 43 históricas documentadas el 26-ago — no subió).

## Por qué no hay acción de código que ejecutar

El item pedía destrabar infraestructura operativa (`sudo supervisorctl reread/update/start`), una
frontera dura fuera del alcance de esta terminal (candado documentado en el propio item: "sin
permiso de sudo en este box"). Esa acción ya se ejecutó — por la vía que el propio item recomendaba,
probablemente por Irving directamente al aprobar el item — y su efecto (worker corriendo, cola
drenada, clasificación del Roadmap funcionando) está verificado en vivo ahora mismo. No queda
ninguna pieza de código, config ni migración pendiente: `megaisp-queue.conf` no se modificó porque
ya estaba bien escrito; el problema era solo que no estaba cargado, y ya lo está.

## Conclusión

#633 no trae trabajo pendiente para el circuito: el atasco de 579 jobs se resolvió por completo
(cola drenada a 1 job legítimo, workers supervisados y estables desde hace 1h32min) antes de que
este item fuera tomado. Se cierra sin cambio de código.

**Deuda menor, sin relación con este hallazgo:** las 2 fallas de `SendOutboundMessageJob`
(22:12:29) podrían investigarse aparte si Marketing reporta mensajes no enviados — no ameritan su
propio item hoy sin una señal adicional de que causaron daño real.
