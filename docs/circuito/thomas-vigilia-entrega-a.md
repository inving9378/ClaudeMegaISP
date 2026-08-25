# Thomas vigilante — ENTREGA A: modo mínimo, cron propio, hombre muerto

> Entregado el **2026-08-25** en dev, rama `circuito/item-183-torre-compuertas`. Sin push.
> Continúa `docs/circuito/thomas-vigilante-paso0.md` (el inventario medido que la justificó).
>
> **Lo que A hace: MEDIR y DEJAR CONSTANCIA.** Lo que A deliberadamente **no** hace: aislar, bajar
> concurrencia, comprimir, truncar, borrar, matar y pausar. Esa autoridad va en entregas
> posteriores, y su prerrequisito —el registro de PIDs— se estrena aquí.

---

## 1. Por qué A va primero

El vigilante vivía colgado del cron del scheduler, que es una de las nueve líneas comentadas el
24-ago. Estuvo **un día entero sin latir y ninguna pantalla lo dijo**. Un supervisor muerto en
silencio convierte el silencio en falsa calma: mientras no se note su muerte, todo lo demás que
se construya encima hereda esa mentira.

## 2. Lo que se construyó

| Pieza | Qué es |
|---|---|
| `config/umbrales_disco.php` | **La política única de disco.** 80 avisa · 85 comprime · 90 trunca · 95 pausa, y el mapa escalón → nivel de escalamiento. |
| `config/torre_salud.php` | Ya **no define umbrales de disco**: los deriva del archivo anterior (amarillo = avisa, rojo = trunca). Antes decía 85/93 y pintaba verde al 82 % con la otra política ya avisando. |
| `config/circuito.php → thomas.vigilia` | Rutas absolutas, umbral del hombre muerto (180 s), raíz de worktrees, umbral de `claude` viejo. |
| `Support/ThomasVigilia.php` | Estado en archivo: `latido.json` + `estado.json`, escritura atómica, sin base. |
| `Support/RegistroPids.php` | **El registro propio de procesos del circuito.** Identidad = PID **+ starttime**. Sin método `matar()`, a propósito. |
| `Console/ThomasVigilarCommand.php` | `circuito:thomas-vigilar` — mide todo desde archivo y /proc; la base va al final y entre `try/catch`. |
| `deploy/circuito/vuelta.sh` | Escribe y borra su entrada del registro (en bash, con `trap EXIT`). |
| `deploy/circuito/vigilia-wrap.sh` + línea de cron | Camino propio, **separado de `cron-wrap.sh`**. |
| `CompuertasService::cThomas()` | La compuerta del hombre muerto en el tablero de la Torre. |

## 3. Las tres decisiones que vale la pena defender

**a) El latido se escribe DESPUÉS del estado.** Si la medición grande falla a mitad, el latido
viejo se queda viejo y el hombre muerto se dispara solo. Preferimos que Thomas se declare muerto
a que se declare vivo mostrando una medición que no pudo guardar.

**b) Identidad de proceso = PID + `starttime`.** Los PID se reciclan; un registro que sólo guarda
el número puede apuntar mañana a un proceso ajeno, y eso es lo que convierte a un vigilante en un
arma. El campo 22 de `/proc/<pid>/stat` es inmutable para ese proceso. Verificado en las dos
direcciones: con el `starttime` correcto la entrada da `vivo y verificado`; alterándolo en uno,
da `el PID se reusó`.

**c) El registro lo escribe bash, no PHP.** Tiene que existir con MySQL caído y con la app rota,
que es exactamente cuando hace falta saber quién está corriendo.

## 4. Lo que ahora se mide y antes no

Medido en la primera vuelta real (2026-08-25 17:39):

- **Disco 65 %**, 49.8 GB libres → escalón `ok`. Ningún peldaño activo.
- **Swap 91 %** (1.86 de 2.0 GB) con 76 % de RAM disponible. Nadie del circuito lo miraba.
- **Nueve `laravel.log`, 1.8 GB en total.** El mayor es `wt-2` con **1.7 GB**; el que la sonda
  medía —el del checkout principal— pesa 38.1 MB. Ver §6.
- **Cinco sesiones interactivas de `claude`** más viejas que el umbral. Se reportan, **nunca se
  tocan**: una de ellas puede ser la sesión con la que Irving está trabajando.
- **Registro de PIDs**: 0 entradas (el circuito está detenido). Con vueltas corriendo tendrá una
  por slot, y `pasadasDeTimeout()` usa el timeout **con el que se lanzó cada vuelta**, no una
  constante.

## 5. Cómo se verifica que está vivo

```bash
php artisan circuito:thomas-vigilar --seco      # mide e imprime SIN tocar el latido
php artisan circuito:thomas-vigilar --print     # mide, guarda e imprime
crontab -l | grep vigilia-wrap                  # su línea propia, la que ningún barrido toca
```

En la Torre: la compuerta **«Vigilancia de Thomas»**, tercera fila del tablero. Verde con la edad
del latido; **ámbar** si mide en modo mínimo (vivo, pero sin base); **rojo** si nunca midió o si
el latido superó los 180 s. Probado: con el latido ausente la línea principal del tablero pasó a
`DETENIDO POR: Vigilancia de Thomas`.

## 6. El defecto de medición, registrado

**La sonda medía un `laravel.log` de nueve.** Cada worktree tiene su `storage/` REAL —es la misma
razón por la que el centinela del freno usa ruta absoluta (#170)—, así que hay un log por worktree
más el del checkout principal. El de `wt-2` acumuló **1.86 GB** y ninguna pantalla lo dijo.

Esto **no es un problema de limpieza, es uno de medición**: la compuerta de cascada mostraba
"log 38 MB · disco 65 %" y estaba diciendo la verdad sobre el archivo equivocado. Falsa calma.

Corregido **dentro de Thomas** (recorre los nueve con glob, y un worktree nuevo entra solo) y
**no** en la sonda, respetando la regla de que nada nuevo mida por su cuenta. La sonda queda
reconciliada en la entrega B, cuando compuertas pase a ser el instrumento único de Thomas.

## 7. Las tres pilas: investigadas, no tocadas

**a) Los cinco `claude` viejos.** Cuatro arrancaron **el mismo segundo**, el martes 14-jul a las
19:53:21, en `pts/5` a `pts/8`, con `cwd = /var/www/megaisp`, colgando de cuatro `-bash` hermanos
cuyo abuelo es `tmux new -s cc` (PID 520099, vivo desde el 8-jul). Es decir: **cuatro paneles de
una sesión de tmux abandonada**, abiertos de un tirón — la fecha coincide con las pruebas de la
Fase 1 en paralelo (`crontab.bak.FASE1_20260715`). El quinto (24-ago 09:41, `pts/4`,
`cwd=/home/meganet`, 580 MB) es del día del incidente P0. Ninguno es del circuito: las vueltas
corren `timeout N claude -p` **sin TTY**. Suman ~1.2 GB de RSS. **Recomendación:** cerrarlos desde
el propio tmux (`tmux kill-session -t cc` tras comprobar que no hay nada a medio hacer), no con
`kill` a ciegas. No lo hice: son sesiones de humano.

**b) `wt-2/storage/logs/laravel.log` — 1.86 GB, última escritura 24-ago 09:56.** Por fecha y
tamaño es **el log de la cascada del 22-24 ago** (las 268.896 excepciones). No es basura: es la
evidencia. Es también el ejemplo perfecto de la regla de oro — **comprimido baja de 100 MB sin
perder una línea**. Recomendación: `gzip` (no truncar, no borrar) después de que Thomas tenga
su directorio forense en la entrega de RECURSOS.

**c) `storage/backup_test` — 4.0 GB en ~30 carpetas de versión** (`V1.1` … `V1.25-09.07.2026`),
~140 MB cada una, de jun-12 a jul-09; nada nuevo desde entonces. Son respaldos de release, no
del circuito. **No entran en la lista blanca de Thomas** ni deberían: son respaldos, y los
respaldos son intocables por política. Es una decisión tuya de retención, no suya.

## 8. Consolidación: qué se absorbe y qué merece item propio

| Engrane | Destino | Por qué |
|---|---|---|
| `WatchdogService` | **Absorber en B** | Ya *es* la escalera: detecta, se auto-cura acotado y escala a los 3 intentos. Su estado vive en `settings` (hoy 0 filas), justo lo que la vigilia mueve a archivo. |
| `SupervisorService` | **Absorber en B, forzado** | Es una vista read-only que **depende** de `WatchdogService`; si el watchdog se mueve y ésta no, se rompe. No es trabajo aparte: es la misma operación. |
| `CompuertasService` | **No absorber: volverlo el instrumento** | Ya tiene las 14 compuertas y la sonda. Thomas **lee** compuertas en vez de re-medir. Cableado, no código nuevo. |
| `EnvironmentHealthService` (#891) | **Item propio** | Es un panel humano (certificado, migraciones, jobs, respaldos) que sólo se solapa en disco y errores. Mover una UI es otro trabajo. Por ahora basta con lo ya hecho: **sus umbrales de disco salen de la fuente única**. |

Regla que queda escrita: **nada nuevo que mida por su cuenta. Todo lo nuevo, dentro de Thomas.**

## 9. El puente de privilegios, con el nombre correcto

Aceptada la corrección: no le da poderes nuevos a `meganet` —que ya tiene `ALL:ALL`—, **se los da
al código no atendido que corre como `meganet`**. Hoy ese código no puede hablar con supervisord
porque le falta una contraseña; después podrá, sin nadie presente. Es un cambio de postura, no una
comodidad. Con eso queda aprobado el envoltorio limitado a los verbos de supervisord sobre los tres
programas nombrados, con el script `root:root` no escribible por `meganet` y cada invocación
registrada con quién la pidió y por qué.

**No está construido en A** — requiere `sudoers` y `visudo`, que no se tocan desde una sesión sin
TTY. Va con su propio entregable, y hasta entonces la compuerta de workers sigue midiendo por `ps`
y diciendo `ctl_legible: false` en vez de inventar.

## 10. Lo que sigue abierto y no cerré

- **El freno no aborta la vuelta EN CURSO.** El centinela se consulta en cada iteración del pool
  (tres capas, ver el informe del Paso 0), pero el `claude -p` que ya está corriendo llega hasta su
  `timeout` de 600 s. Ventana máxima: 10 min por slot. Cerrarla es barato ahora que existe el
  registro de PIDs —el latido `circuito:vivo --watch` ya corre cada 15 s dentro de la vuelta y
  puede consultar el centinela y terminar su propio grupo de procesos— pero es trabajo aparte y no
  lo metí en A.
- El log de errores de la vigilia (`/home/meganet/circuito/logs/vigilia-errores.log`) no rota. Sólo
  crece cuando algo falla; entra en la lista blanca de RECURSOS.
