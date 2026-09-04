# Cortar una vuelta a mano — runbook (item #215)

> Si necesitas parar una vuelta del circuito AHORA MISMO, no uses `pkill -f`. Usa:
>
> ```bash
> php artisan circuito:cortar-vuelta --sid=wt-K            # dry-run: solo muestra qué hay
> php artisan circuito:cortar-vuelta --sid=wt-K --confirmar # corta de verdad
> ```

## El incidente que esto evita

2026-08-25 15:47, durante la Fase 3 de #191, para cortar una vuelta se corrió:

```bash
pkill -TERM -f 'claude -p Eres un EJECUTOR ON-BOX'
```

`pkill -f` matchea contra la **línea de comando completa** de cada proceso vivo. El shell que
ejecutó ese `pkill` traía el patrón LITERAL dentro de su propia línea de comando (es el argumento
que se le pasó) → se encontró a sí mismo en el barrido y se mató. La sesión murió a media
verificación, justo cuando el operador necesitaba confirmar que había cortado lo correcto.

Agravante medido el mismo día: el latido `circuito:vivo --watch` (hijo en background de
`deploy/circuito/vuelta.sh`, ver ahí la línea que lo lanza con `&`) **no murió con la vuelta** —
quedó huérfano con `PPID=1`. Su línea de comando (`php artisan circuito:vivo --watch ...`) no
contiene el patrón que buscaba el `pkill`, así que ni siquiera lo alcanzaba.

## El patrón seguro

1. **Matar por PID (o PGID), nunca por patrón de línea de comando.** Un patrón de texto puede
   matchear procesos que no querías tocar — incluido el que lo está ejecutando.
2. **Si de verdad hace falta buscar por patrón** (fuera de esta herramienta, a mano): excluir el
   propio proceso ANTES de mirar la lista —`pgrep -f PATRON | grep -v $$`— y revisarla uno por uno
   antes de mandar la señal. Nunca `pkill -f` a ciegas.
3. **Tras matar una vuelta, verificar APARTE que no quedó nada huérfano** del mismo grupo (en
   particular `circuito:vivo --watch`). No asumas que la señal alcanzó a todo.

## Qué hace `circuito:cortar-vuelta`

Aplica las tres reglas de arriba usando el registro propio del circuito
(`App\Modules\Addons\Roadmap\Support\RegistroPids`, identidad = PID + `starttime`, inmune a
reciclado de PID):

1. Busca el `--sid` en el registro (nunca en `ps`/cmdline). Si no está o ya no está vivo, no hace
   nada — lo dice y sale.
2. Sin `--confirmar` es **dry-run**: solo muestra pid/pgid/item/log/edad para que el operador los
   revise antes de matar nada. Con `--confirmar`, manda la señal (`--senal=TERM` por default) al
   **grupo de procesos** (PGID negativo), que es el mismo grupo donde nace `circuito:vivo --watch`
   dentro de `vuelta.sh` — así el heartbeat cae junto con la vuelta.
3. Después de mandar la señal, **vuelve a escanear `/proc`** buscando cualquier proceso que
   comparta ese PGID. Si algo sigue vivo, lo reporta por PID exacto (nunca reintenta por patrón) y
   sugiere `--senal=KILL`. Si no queda nada, lo confirma explícitamente.
4. Cierra el estado en vivo (`circuito:vivo --end`) para que la Torre deje de mostrar "corriendo",
   y limpia el archivo de registro si el `trap EXIT` de `vuelta.sh` no llegó a correr.

Sin `--sid`, lista todo lo que hay registrado (vivo o no) para que el operador elija.

**Verificado (2026-08-28):** contra un par de procesos de prueba que comparten PGID (simulando
`claude -p` + `circuito:vivo --watch`) — dry-run no toca nada; `--confirmar` mata ambos y confirma
"sin huérfanos"; contra un proceso que ignora `SIGTERM` a propósito, reporta el PID que sobrevivió
en vez de asumir que murió. Las vueltas reales (`wt-1`, `wt-2`) en curso durante la prueba no se
tocaron en ningún momento.

## Qué NO resuelve esta herramienta

- No sustituye al kill switch (`php artisan circuito:pausar`), que evita que se **lancen** vueltas
  nuevas. `circuito:cortar-vuelta` corta la que ya está corriendo.
- No es la autoridad autónoma de un vigilante (Thomas/Jarvis) para matar procesos por su cuenta —
  eso sigue siendo una decisión aparte y deliberadamente no construida (ver
  `docs/circuito/thomas-vigilia-entrega-a.md` §2, "Sin método `matar()`, a propósito"). Esto es una
  herramienta que un HUMANO invoca a mano, con el registro como única fuente de identidad.
