# Puente de privilegios de la Torre — DISEÑO (sin implementar)

> Estado: **propuesta**. No se ha tocado `sudoers` ni se ha creado ningún script.
> Requiere aprobación explícita de Irving antes de ejecutar nada.

## El problema

El panel corre en php-fpm como `www-data`. Dos compuertas rojas —cron del ejecutor y
workers de supervisor— sólo se sueltan desde el sistema operativo. Hoy el tablero las
muestra con el comando exacto, pero Irving tiene que abrir una terminal.

## El principio que ordena el diseño

Esta semana un botón web ejecutó `migrate` sobre la base de producción de dev porque
Ignition exponía una acción con privilegios sin confirmación. **El puente no puede ser
una versión mejor peinada de ese botón.** Por eso el diseño no pregunta "¿cómo le damos
privilegios al panel?" sino "¿cuál es el conjunto más pequeño de verbos que resuelve el
problema, y qué pasa si mañana alguien ejecuta código como www-data?".

Si el panel es comprometido, el atacante gana **exactamente** los verbos de la lista
blanca. Nada más. Ese es el criterio de éxito.

## 1. El script envoltorio

**Ubicación:** `/usr/local/sbin/megaisp-torre-puente`
**Propiedad:** `root:root`, modo `0755`.

Fuera de `/var/www/megaisp` a propósito: si viviera dentro del repositorio, cualquier
escritura de la app —o un `git checkout` de una rama hostil— reescribiría el script que
se ejecuta como root. El directorio contenedor también debe ser `root:root`, no
escribible por `www-data`.

**Contrato: verbos cerrados, sin argumentos libres.**

| Verbo | Qué hace | Riesgo |
|---|---|---|
| `estado` | Imprime cron y workers en JSON. No modifica nada. | Ninguno |
| `cron-pausar` | Comenta las líneas `cron-wrap.sh` del crontab de `meganet`. | Bajo — *detiene* |
| `cron-reanudar` | Descomenta esas mismas líneas. | **Alto** — ver §5 |
| `workers-arrancar` | `supervisorctl start` de los tres programas por nombre literal. | Medio |
| `workers-detener` | `supervisorctl stop` de los tres. | Bajo — *detiene* |

Reglas de implementación, no negociables:

- El verbo se compara con `case` contra la lista literal. **Cualquier otro valor sale con
  código 64 y no ejecuta nada.** No hay verbo por defecto, no hay `eval`, no hay
  interpolación de variables en comandos.
- Los nombres de los programas de supervisor están **escritos en el script**, no vienen
  por argumento. `workers-arrancar` no acepta "qué worker": arranca los tres que conoce.
- El script no acepta más de un argumento posicional (más el `--pedido-por`, §3).
- `cron-pausar`/`cron-reanudar` **sólo alternan el comentario** de líneas que ya existen y
  que contienen `cron-wrap.sh`. No añaden líneas, no borran, no editan el resto del
  crontab. Antes de escribir, respaldan el crontab en `/var/backups/crontab-meganet-<ts>`.
- `set -euo pipefail`, `umask 077`, `PATH` fijo al inicio.

## 2. La línea de sudoers

En `/etc/sudoers.d/megaisp-torre-puente`, modo `0440`, validada con `visudo -c` antes de
instalarse:

```
Cmnd_Alias TORRE_PUENTE = \
    /usr/local/sbin/megaisp-torre-puente estado, \
    /usr/local/sbin/megaisp-torre-puente cron-pausar, \
    /usr/local/sbin/megaisp-torre-puente cron-reanudar, \
    /usr/local/sbin/megaisp-torre-puente workers-arrancar, \
    /usr/local/sbin/megaisp-torre-puente workers-detener

www-data ALL=(root) NOPASSWD: TORRE_PUENTE
```

Cada invocación se enumera **con su argumento exacto**. Así la lista blanca se aplica dos
veces: primero `sudo`, que ni siquiera lanza el script si el verbo no está enumerado, y
después el propio script. Una sola de las dos capas bastaría; tener ambas significa que un
error en una no abre la puerta.

Lo que este diseño **no** hace, y es deliberado:

- No `www-data ALL=(root) NOPASSWD: ALL`.
- No comodines (`/usr/local/sbin/megaisp-torre-puente *`), que permitirían argumentos
  arbitrarios.
- No `sudo` sobre `supervisorctl` ni sobre `crontab` directamente: son binarios de
  propósito general, y dárselos a `www-data` equivale a dárselo casi todo.
- El `--pedido-por` de §3 **no** se enumera en sudoers porque cambia en cada llamada; por
  eso el script lo trata como dato sucio y nunca como parte de un comando.

## 3. Registro de cada invocación

Dos registros, con distinta autoridad:

**El del sistema** — `/var/log/megaisp-torre-puente.log`, `root:adm 0640`, sólo append,
con `logrotate`. Una línea por invocación: fecha, verbo, uid real, el valor de
`--pedido-por`, código de salida y las primeras líneas de salida.

**El de la aplicación** — `torre_compuerta_cambios`, que ya existe y ya registra quién,
cuándo y de qué valor a cuál. La app escribe la intención **antes** de invocar y el
resultado **después**, de modo que un puente que se cuelgue deje rastro igual.

Un matiz que importa: `--pedido-por` lo manda la aplicación, así que es **una afirmación
de la app, no una identidad verificada**. El script lo sanea a `[A-Za-z0-9._-]{1,40}` y lo
registra etiquetado como *declarado*. La identidad de verdad vive en la sesión de Laravel
y en la tabla. Si alguien ejecuta el script como `www-data` por fuera del panel, el log
lo mostrará sin `--pedido-por` o con uno inventado, y el cruce con la tabla no cuadrará:
esa discrepancia es la señal de alarma.

## 4. Confirmación en dos pasos, sin excepción

La UI ya lo hace y el servidor ya lo exige (`confirmado=true` validado en el controlador,
no en el navegador). El puente hereda ese camino: las acciones nuevas entran por el mismo
endpoint, con el mismo segundo paso, y el diálogo dice qué va a pasar.

Para `cron-reanudar` el texto tiene que ser explícito, algo como: *"El circuito volverá a
lanzar vueltas automáticas cada minuto. Cada vuelta ejecuta un agente con permisos de
escritura sobre el repositorio. Hay N items despachables ahora mismo."* — con N medido en
vivo, porque el número cambia la decisión.

## 5. El verbo peligroso, y una recomendación

Los cinco verbos no son iguales. Tres **detienen** cosas (`cron-pausar`,
`workers-detener`) o sólo miran (`estado`): en el peor caso, un atacante que los use causa
una interrupción, que es ruidosa y reversible.

`cron-reanudar` es distinto: **reinicia la ejecución autónoma**. Un atacante con RCE como
`www-data` que lo invoque consigue que el circuito empiece a lanzar agentes con escritura
sobre el repositorio. No es escalada a root, pero sí es "poner en marcha una máquina que
escribe código".

Tres opciones, de más a menos restrictiva:

1. **Dejar `cron-reanudar` fuera del puente.** Pausar y levantar workers desde el panel;
   reanudar el circuito sigue siendo un acto deliberado en terminal. Es la más segura y
   cubre el 80% de la molestia real: durante un incidente lo que urge es *detener*.
2. **Incluirlo con un segundo factor**: que exija que el freno de mano de la BD esté
   suelto Y que un `torre.config.edit` distinto del solicitante lo haya habilitado en los
   últimos N minutos. Caro de implementar, difícil de operar con un solo administrador.
3. **Incluirlo como los demás**, confiando en la confirmación de dos pasos y el registro.

**Recomiendo la 1.** El incidente de esta semana empezó porque una acción de alto impacto
estaba a un clic de distancia en una pantalla. La asimetría es favorable: detener desde el
panel resuelve la urgencia, y reanudar —que es cuando conviene pensar dos veces— conserva
la fricción de abrir una terminal.

## 6. Orden de instalación propuesto

1. Escribir el script y probarlo **como root, a mano**, con cada verbo y con verbos
   inválidos. Verificar que un argumento fuera de lista sale con 64 sin efectos.
2. `visudo -c -f` sobre el fichero de sudoers **antes** de moverlo a `/etc/sudoers.d/`.
   Un `sudoers` mal formado puede dejar el sistema sin sudo.
3. Probar `sudo -u www-data sudo -n /usr/local/sbin/megaisp-torre-puente estado`.
4. Probar que un verbo NO enumerado es rechazado por sudo antes de llegar al script.
5. Recién entonces, conectar los botones del panel.
6. Revisar el log tras las primeras invocaciones reales y cruzarlo con
   `torre_compuerta_cambios`.

## 7. Qué se necesita de Irving para avanzar

- ¿Se incluye `cron-reanudar`? (recomendación: no, §5)
- Confirmación para crear `/usr/local/sbin/megaisp-torre-puente` y
  `/etc/sudoers.d/megaisp-torre-puente` — ambos exigen root.
- ¿El log va a `/var/log/` con logrotate propio, o a `storage/logs/` para que el panel
  pueda mostrarlo? (recomendación: `/var/log/`, y que el panel lo lea vía el verbo
  `estado`; `storage/logs` es escribible por `www-data` y un log de auditoría no debe
  serlo por quien audita).
