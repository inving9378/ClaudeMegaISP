# Medición #910 — Parte 1 (item #9990393): inventario del barrido (#908) + snapshots de ocupación

Ventana de medición: **2026-09-03 17:55:00** (merge de #986, última pieza dependiente de 5a-5c en
cerrar) → **2026-09-06 06:29** (momento de esta ejecución). Números crudos únicamente — el cálculo
del ratio útil/ruido y la redacción de cierre quedan para el sub-item hermano (parte 2, #9990394).

## 1. Inventario de items del barrido (#908)

Query (tinker), replicando exactamente la del spec de #910:

```php
RoadmapItem::where('created_at', '>=', '2026-09-03 17:55:00')
    ->where(function ($q) {
        $q->where('title', 'like', '[BARRIDO]%')
          ->orWhereRaw("JSON_EXTRACT(log,'$[0].por')='barrido'");
    })
    ->get(['id', 'title', 'estado_aprobacion', 'merge_commit', 'created_at']);
```

Resultado: **12 items** marcados `[BARRIDO]`, los 12 en `estado_aprobacion=completado` y los 12
con `merge_commit` no nulo (mergeados a `main`):

| id | estado | merge_commit | título (recortado) |
|---|---|---|---|
| 9990229 | completado | `a8af6291` | Barrido: TODO en app/Modules/Addons/GestionRed/mig… |
| 9990230 | completado | `ccbe9c49` | Barrido: Todo en app/Modules/Addons/Tickets/Contro… |
| 9990233 | completado | `3708d59e` | Barrido: todo en app/Modules/Addons/Talento/Contro… |
| 9990234 | completado | `69e7ae05` | Barrido: todo en app/Modules/Addons/Flotas/Control… |
| 9990236 | completado | `661e4b58` | Barrido: todo en app/Modules/Addons/Marketing/Cont… |
| 9990237 | completado | `42c7f2db` | Barrido: Todo en app/Modules/Addons/Payments/Conso… |
| 9990239 | completado | `c1694264` | Barrido: todo en app/Modules/Addons/MegaFamilia/Co… |
| 9990240 | completado | `51298d63` | Barrido: todo en app/Modules/Addons/VoIP/Controlle… |
| 9990242 | completado | `d1887db9` | Barrido: todo en app/Modules/Addons/WhatsAppAgent/… |
| 9990243 | completado | `8b67248d` | Barrido: todo en app/Modules/Addons/PortalCliente/… |
| 9990248 | completado | `d4e00b4c` | Barrido: Todo en app/Modules/Addons/PortalPago/Ser… |
| 9990252 | completado | `6a6c12a6` | Barrido: Todo en app/Modules/Core/Usuarios/Control… |

Contexto de la ventana: **177 items totales** creados desde 2026-09-03 17:55:00 hasta ahora
(`RoadmapItem::where('created_at', '>=', '2026-09-03 17:55:00')->count()`).

→ **Ratio barrido/total = 12 / 177 ≈ 6.8 %.**

(Reverificado de forma independiente en esta vuelta — coincide exactamente con lo que había dejado
una vuelta anterior que murió a media escritura del reporte; no hubo cambios en la ventana desde
entonces.)

## 2. Ocupación — limitación confirmada

`JarvisService::diagnostico()` (`app/Modules/Addons/Roadmap/Services/JarvisService.php:1611-1642`)
calcula `terminales => [total, ocupadas, libres]` **solo en memoria** en el momento de la llamada
(query en vivo sobre `estado_aprobacion=en_progreso` + `getParalelismo()`). No hay snapshot
histórico persistido en ninguna tabla ni en el log de items.

**Consecuencia:** la ocupación **"antes"** (el estado del sistema en cualquier instante previo al
2026-09-03 17:55, o incluso previo a esta misma ejecución) **NO es reconstruible retroactivamente**.
No se inventa ese número — se documenta la limitación tal cual el spec de #910 anticipaba.

## 3. Ocupación — snapshots "después" (4 muestras reales, ~90s de separación)

Tomadas vía `app(JarvisService::class)->diagnostico()` directo en tinker (no `circuito:jarvis`,
porque ese comando además resuelve consultas y dispara acciones reales — se quería una lectura
pura, sin efectos secundarios):

| # | hora | total | ocupadas | libres | cola_ejecutable | consultas_vivas | ocio_con_cola |
|---|---|---|---|---|---|---|---|
| 1 | 06:31:06 | 6 | 1 | 5 | 0 | 0 | false |
| 2 | 06:32:58 | 6 | 1 | 5 | 0 | 0 | false |
| 3 | 06:34:28 | 6 | 1 | 5 | 0 | 0 | false |
| 4 | 06:35:59 | 6 | 1 | 5 | 0 | 0 | false |

Las 4 muestras son **idénticas**: paralelismo configurado = 6 (`config/circuito.php:204`,
`CIRCUITO_PARALELISMO`), 1 sola terminal ocupada (`wt-1`, exactamente esta misma vuelta trabajando
#9990393 — `en_vuelo` solo trae ese item), 5 libres, **sin cola ejecutable** (`cola_ejecutable=0`)
en ningún instante de la ventana de 5 minutos observada. `colisiones_modulo=[]` y `pausado=false`
en las 4.

**Nota honesta:** esta muestra de 5 minutos coincide con muy baja actividad (1/6 ocupadas, cola en
0) — no es representativa de la ventana completa de 63 horas del punto 1 ni de las horas pico. Es
la única lectura real "después" que el sistema permite tomar hoy (no hay snapshot histórico, punto
2), y se deja tal cual para que la parte 2 decida cómo pesarla frente al inventario del barrido.

## 4. Proxy de actividad — `worker_sid` distintos en `en_progreso`

En el momento de la primera muestra (06:31:06) y confirmado de nuevo justo antes de tomarla:
**1 solo item** en `estado_aprobacion=en_progreso` en todo el sistema — el propio #9990393
(`wt-1`). Ningún otro `worker_sid` activo en ese instante.

## Para la parte 2 (#9990394)

Datos crudos disponibles arriba: 12/177 items de barrido (6.8%), limitación de ocupación histórica
confirmada, 4 muestras "después" idénticas (6 terminales, 1 ocupada, 0 en cola), 1 solo
`worker_sid` activo. La parte 2 calcula el ratio útil/ruido y redacta el cierre de #910 usando estos
números — sin volver a ejecutar las queries de este documento.
