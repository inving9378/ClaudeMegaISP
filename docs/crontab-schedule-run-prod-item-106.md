# Item #106 — Crontab `schedule:run` en PROD (path correcto) — snippet + checklist para Irving

## Por qué el circuito no lo ejecuta directo

Toca el crontab del servidor **productivo** (`.108`/`.198`, `/var/www/ClaudeMegaISP`) — frontera
dura de producción. El propio brief de decisión del item (triaje nivel C) lo marcó para escalar, e
Irving aprobó el plan **recomendado** en las 3 preguntas del item (2026-08-28): el circuito prepara
en dev el diagnóstico + el snippet correcto; **él lo aplica a mano en el box de prod**. Este
documento es esa preparación — no toca ningún servidor.

## El hallazgo que motiva el item — ya reproducido en DEV (item #172, hermano de este)

En DEV el mismo síntoma ya ocurrió y quedó resuelto (ver `docs/bitacora-sesiones.md`,
sección `2026-08-26 15:09`): el crontab del usuario `meganet` en `.11` **no tenía ninguna línea
`schedule:run`** — el scheduler de Laravel entero (backups, facturación, notificaciones, syncs de
red) estaba mudo desde que existe ese crontab, con cero error visible (fallo callado: nada se
rompe, simplemente nunca corre). La causa no fue de código: `backup_db:process` y el resto de los
comandos ya estaban correctos y registrados en `app/Console/Kernel.php`; sólo faltaba que algo los
disparara cada minuto.

**El mismo patrón puede estar pasando en PROD**, y con un agravante propio de prod: según
`CONTEXTO-MEGAISP.md` §0, **el shell de prod arranca por default en `/var/www/MEGANET`** (la ruta
legacy, obsoleta — vhost deshabilitado) y no en `/var/www/ClaudeMegaISP` (la ruta real que sirve
prod hoy, BD `meganet_prod_claude`). Si alguna vez se armó la línea de cron copiando/pegando desde
una sesión de shell parada en el directorio por default, **apuntaría al proyecto equivocado** —
exactamente el escenario que describe el título del item ("Si apunta a `/var/www/MEGANET`,
corregir a la ruta real").

## Paso 1 — Diagnóstico (Irving, en el box de prod `.108`)

```bash
# Ver el crontab actual del usuario que corre PHP-FPM en prod (respuesta elegida: www-data)
sudo crontab -l -u www-data | grep -i schedule

# Si no hay nada bajo www-data, revisar también el usuario con el que se instaló originalmente
crontab -l | grep -i schedule
sudo crontab -l -u root | grep -i schedule
```

Tres resultados posibles:

1. **No aparece ninguna línea `schedule:run`** → el scheduler nunca corre en prod (mismo patrón que
   el bug ya resuelto en dev, item #172). Ir al Paso 2.
2. **Aparece pero con `cd /var/www/MEGANET`** (o cualquier ruta que no sea
   `/var/www/ClaudeMegaISP`) → apunta al proyecto legacy/obsoleto, corregir con el snippet del
   Paso 2.
3. **Ya aparece con `cd /var/www/ClaudeMegaISP`** → está bien, no hay nada que corregir; el item se
   cierra sin cambios en prod.

## Paso 2 — Snippet correcto (solo si el diagnóstico dio caso 1 o 2)

```bash
sudo crontab -e -u www-data
```

Línea a dejar (agregar si faltaba, o reemplazar la que apuntaba a `/var/www/MEGANET`):

```
* * * * * cd /var/www/ClaudeMegaISP && php artisan schedule:run >> /dev/null 2>&1
```

- **Usuario `www-data`** — mismo que corre PHP-FPM/Laravel en prod (opción recomendada y elegida
  por Irving para el item: permisos consistentes con `storage/`/`bootstrap/cache`, sin archivos con
  owner `root` mezclados ahí).
- **Ruta `/var/www/ClaudeMegaISP`** — es la ÚNICA ruta real de prod hoy (`CONTEXTO-MEGAISP.md` §0);
  `/var/www/MEGANET` es la instancia legacy, obsoleta.
- Verificar que quedó guardado: `sudo crontab -l -u www-data | grep schedule`.

## Paso 3 — Qué depende de que esto corra

Con `schedule:run` mudo, TODO lo que vive en `app/Console/Kernel.php` deja de dispararse en su
horario, sin ningún error visible. Lo más sensible:

- `invoice:create-proformas` (03:00) — facturación real.
- `billing:send-pending-notifications` — notificaciones de cobranza.
- `backup_db:process` (02:00) — respaldos reales de la BD de prod.
- `mikrotik:sync` (cada 5 min) / `smartolt:sync-critical` (cada 10 min) — sync de red/hardware.
- `activitylog:archive` (02:00) — archivado de logs de actividad.
- CobranzaBlaster (`cobranza:blast-activas`, cada 5 min) y domiciliación, si están activos en prod.

## Paso 4 — Si estuvo caído (decisión ya tomada por Irving para este item)

**NO re-disparar nada automáticamente.** La opción elegida por Irving en el item (pregunta q3) fue
que él mismo decida manualmente qué jobs re-lanzar tras revisar qué se perdió — para evitar
duplicar cobros o notificaciones. Ayuda para esa revisión, sin tocar nada:

```bash
cd /var/www/ClaudeMegaISP
php artisan schedule:list          # qué debería estar corriendo y en qué horario
```

Comparar contra los logs/timestamps reales de cada tarea (p. ej. el archivo más reciente en
`/var/backups/mysql/` de prod, o la última proforma/notificación generada) para estimar desde
cuándo dejó de correr, y decidir desde ahí qué re-lanzar a mano.

## Cierre

Este documento + el diagnóstico de arriba es la entrega completa del circuito para el item #106
bajo el plan aprobado (opciones recomendadas de las 3 preguntas). La aplicación en prod queda para
Irving; el circuito no ejecuta comandos ni edita archivos en `.108`/`.198`.
