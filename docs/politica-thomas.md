# Política de decisión y escalamiento de Thomas (Torre v2)

> **Qué es esto.** La regla fija con la que Thomas —el supervisor del Circuito CC— resuelve las
> dudas de las seis terminales de ejecución. Es la respuesta a un problema concreto: hasta ahora
> la única salida de una terminal que dudaba era `requiere_irving`, así que cualquier titubeo
> despertaba al humano y el item se quedaba parado en vez de avanzar sobre la opción recomendada.
>
> **Fuente de verdad ejecutable:** `config/circuito.php` → bloque `thomas`.
> **Implementación:** `app/Modules/Addons/Roadmap/Services/ThomasService.php`.
> Este doc explica el *porqué*; la config manda sobre el *qué*.

---

## 1. La regla de oro

**Por default se avanza, no se pregunta.**

Ante una duda de implementación, la terminal:

1. Elige la **opción recomendada** — la más simple, aditiva y reversible que resuelve el item.
2. **Sigue** trabajando.
3. **Registra** la decisión en el historial del item.

```bash
php artisan circuito:reportar <id> --sid=wt-K --tipo=decision \
  --resumen="Decidí X en vez de Y porque Z"
```

Irving revisa esas decisiones **después**, no antes. Una decisión registrada y reversible no
necesita permiso previo: ese es todo el punto del circuito.

---

## 2. Quién le pregunta a quién

```
  terminal (wt-1..wt-6)  ──duda──▶  THOMAS  ──sólo lo irreversible de alto impacto──▶  IRVING
                                      │
                                      └── todo lo demás: responde al instante y el item sigue
```

**Una terminal nunca escala a Irving por su cuenta.** Ese camino no existe para ella. Le pregunta
a Thomas con:

```bash
php artisan circuito:consultar <id> --sid=wt-K \
  --pregunta="<la duda en una frase>" \
  --opcion="<opción A>|recomendada|reversible" \
  --opcion="<opción B>"
```

El contrato es el **exit code**:

| Código | Significado                                                                 |
|--------|-----------------------------------------------------------------------------|
| `0`    | **PROCEDE** — sigue con la opción que Thomas indicó. No vuelvas a preguntar. |
| `1`    | **ESCALADO** — detente. El item ya está en la bandeja de Irving.            |

La respuesta es **inmediata**: la política es determinista (coincidencia de términos, sin llamada
a IA), así que la terminal no se bloquea esperando un turno del loop ni desperdicia su slot.

---

## 3. El conjunto de escalamiento (lo único que llega a Irving)

Thomas escala **sólo** si la acción es **irreversible y de alto impacto**. Son cuatro fronteras
duras, más un caso especial:

| # | Frontera | Ejemplos de lo que la dispara |
|---|----------|-------------------------------|
| 1 | **Tocar producción** | `.108` / `v1megaisp` / `remote:deploy` / `git push` a origin |
| 2 | **Borrar datos** | `migrate:fresh`, `drop table`, `truncate`, `delete from`, borrado masivo |
| 3 | **Gastar dinero** | aplicar/cobrar pagos, OpenPay, SPEI, domiciliación, timbrado, nómina |
| 4 | **Credenciales / seguridad** | API keys, secretos, contraseñas, `.env`, otorgar permisos, IDOR |
| 5 | **Spec contradictorio** | el item se contradice a un grado que impide avanzar (lo declara la terminal con esas palabras) |

Los términos exactos viven en `config/circuito.php` → `thomas.escalamiento`. Se evalúan contra la
pregunta **más** el título y módulo del item: un item de "cobros" cuya pregunta suena inocente
sigue siendo territorio de dinero.

> **Cada término que se agregue a esa lista es una interrupción más para Irving.** Por eso la lista
> es corta a propósito, y por eso se afinó con la lección del revisor #338: términos demasiado
> amplios (`token`, `login`, `prod` a secas) escalaban falsos positivos mecánicos por substring.

### Lo que explícitamente NO se consulta

Elegir entre dos implementaciones razonables · nombres de variables, métodos o columnas · dónde
poner un archivo · si agregar un índice · qué texto va en un mensaje · un refactor menor · si el
item "valía la pena" (llega ya triado y aprobado) · una verificación que falla (eso es trabajo por
hacer, no una duda) · código muerto confirmado sin consumidores (se borra, es un nivel A).

---

## 4. Cómo decide Thomas, en orden

1. ¿La pregunta o el item caen en el **conjunto de escalamiento**? → **Irving**.
2. ¿La terminal declara el **spec contradictorio**? → **Irving**.
3. ¿Hay una opción marcada **`recomendada`**? → esa, y el item sigue.
4. Sin recomendada: la primera marcada **`reversible`** → esa, y el item sigue.
5. **Ninguna opción es reversible** (o no vinieron opciones) → **Irving**.

El paso 5 es deliberado: si nada de lo que la terminal propone se puede deshacer, esa ausencia
*es* la señal de riesgo. Se controla con `thomas.exige_reversible_sin_recomendada`.

`reversible` significa: aditivo, sin borrar datos, sin tocar dinero ni permisos, y se puede
revertir con un `git revert` o una migración inversa.

---

## 5. Qué pasa cuando Thomas escala

- El item pasa a `estado_aprobacion = requiere_irving` y **suelta la terminal** (`worker_sid = null`),
  así el slot queda libre para el siguiente item de la cola.
- Queda en el historial la cadena completa: la `consulta` de la terminal y la `escalacion` de
  Thomas con su motivo y su categoría.
- La terminal termina su vuelta con `ejecuto=false`. **No** deja trabajo a medias.

---

## 6. Lo demás que hace Thomas

- **Estima esfuerzo** (`eta_minutos`): minutos base por nivel de riesgo + tamaño del spec.
  Es **orientativo y nunca bloqueante** — nada se rechaza por pasarse del estimado.
- **Verifica el cierre**: exige que el item traiga `reporte_coloquial` (qué cambió y dónde, en
  llano) y `enlace_revision` (la ruta real de la UI donde verlo). Sin eso, Irving no puede revisar
  el resultado. Configurable en `thomas.cierre`.
- **Vigila las invariantes** del reparto: `php artisan circuito:thomas --diagnostico` reporta
  terminales libres/ocupadas, cola ejecutable, colisiones de módulo y si hay ocio con cola.

**Lo que Thomas NO hace: repartir el trabajo.** El reparto (slots, módulo-disjunto, reclamo
atómico, lease) ya lo hace `circuito:scheduler`, que es el único despachador desde #432 B1.
Duplicarlo crearía una segunda verdad sobre quién trabaja qué. Por eso la vuelta de Thomas va
enganchada al scheduler y no en un cron paralelo.

---

## 7. Frenos

| Freno | Efecto |
|---|---|
| `circuito_pausado` (kill switch, botón de la Torre) | Thomas no decide nada, igual que el autopilot. |
| `thomas.enabled = false` | Se apaga el loop; las consultas quedan vivas sin resolver. |
| Ampliar `thomas.escalamiento` | Más cosas llegan a Irving (menos autonomía). |
| Recortar `thomas.escalamiento` | Más autonomía. **Tocar con cuidado**: es la frontera de seguridad. |

---

## 8. Comandos

```bash
php artisan circuito:consultar <id> --sid=wt-K --pregunta="…" --opcion="…|recomendada|reversible"
php artisan circuito:reportar  <id> --sid=wt-K --tipo=decision --resumen="…"
php artisan circuito:sub-item  <padre> --sid=wt-K --titulo="…" --spec="…"
php artisan circuito:thomas --diagnostico     # estado del reparto
php artisan circuito:thomas --dry             # evalúa consultas colgadas sin escribir
```
