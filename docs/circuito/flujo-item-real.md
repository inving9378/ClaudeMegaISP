# Ciclo de vida de un item — el flujo REAL

> **Medido el 2026-08-19 en dev.** Descriptivo, no aspiracional: si algo funciona de una forma rara
> pero real, está dibujado raro y real. Este documento sirve para depurar, no para presentar.

## Los estados que existen de verdad

`SELECT estado_aprobacion, COUNT(*) FROM roadmap_items GROUP BY 1` — no la lista teórica:

| estado_aprobacion | filas |
|---|---:|
| `completado` | 436 |
| `aprobado_irving` | 72 |
| `requiere_irving` | 53 |
| `rechazado` | 10 |
| `cancelado` | 8 |
| `en_progreso` | 2 |
| `aprobado_revisor` | 1 |
| `pendiente_revision` | **0** |
| `aprobado_claude` | **0** |

⚠️ **`pendiente_revision` y `aprobado_claude` marcan cero, y no es que no se usen: son
TRANSITORIOS.** Un item A nace `pendiente_revision` y el scheduler lo reclama en la vuelta siguiente
(≤ 1 min); `aprobado_claude` dura lo mismo. Que estén en cero es señal de que el pool está drenando
bien, no de que estén muertos. La misma foto con la flota parada los mostraría acumulados.

⚠️ **`aprobado_revisor` en 1 contra `aprobado_irving` en 72** es la firma del destrabe de bandeja
muerto (ver `torre-como-funciona-hoy.md` §3): lo que Irving aprueba se acumula porque el motor que
lo movía no corre.

---

## El diagrama

```mermaid
flowchart TD
    %% ── NACIMIENTO ─────────────────────────────────────────────
    subgraph N["① NACIMIENTO — 7 puntos"]
        N1["RoadmapIntakeService::crear<br/>externa · auditor · sub-items<br/><b>→ pendiente_revision</b>"]
        N2["RoadmapController::store<br/>Irving desde la Torre · #566<br/><b>→ aprobado_irving</b>"]
        N3["ThomasService::aprobarMecanico<br/>4 puertas + tope diario 25<br/>tope B<br/><b>A→aprobado_claude · B/C→aprobado_revisor</b>"]
        N4["ThomasService «ya decidido»<br/>brief 100% contestado<br/>⚠️ SIN tope de nivel hasta 2A<br/><b>A→aprobado_claude · B/C→aprobado_revisor</b>"]
        N5["AutopilotService::aplicar<br/>confianza alta + reversible<br/>tope C<br/><b>A→aprobado_claude · B/C→aprobado_revisor</b>"]
        N6["RevisorService::aplicarVeredicto<br/>veredicto adversarial IA<br/>⚠️ SIN tope de nivel<br/><b>aprobado_revisor | requiere_irving</b>"]
        N7["RevisorService des-trabador :979<br/>Opus · anti-loop por rebotes<br/>⚠️ SIN tope de nivel<br/><b>A→aprobado_claude · B/C→aprobado_revisor</b>"]
    end

    %% ── VÍA EXTERNA: carril aparte ─────────────────────────────
    subgraph X["⑂ VÍA EXTERNA — carril separado"]
        X1["Cowork / MCP · token en .env"]
        X2["RoadmapCircuitoService::guard()<br/>a· nivel_riesgo solo ENDURECE<br/>b· solo A puede quedar aprobado_claude<br/>c· #260 el A debe venir de origen INTERNO"]
        X1 --> X2
    end
    X2 -->|"B y C topan aquí"| RI
    X2 -->|"A de origen interno"| AC

    %% ── ESTADOS ────────────────────────────────────────────────
    PR["pendiente_revision<br/><i>transitorio · 0 filas</i>"]
    RI["requiere_irving<br/><b>53 filas</b> — la bandeja"]
    AC["aprobado_claude<br/><i>transitorio · 0 filas</i>"]
    AR["aprobado_revisor<br/><b>1 fila</b>"]
    AI["aprobado_irving<br/><b>72 filas</b>"]
    EP["en_progreso<br/><b>2 filas</b> — worker con lease"]
    CO["completado<br/><b>436 filas</b>"]
    RE["rechazado · 10"]
    CA["cancelado · 8"]

    N1 --> PR
    N2 --> AI
    N3 --> AC
    N3 --> AR
    N4 --> AC
    N4 --> AR
    N5 --> AC
    N5 --> AR
    N6 --> AR
    N6 --> RI
    N7 --> AC
    N7 --> AR

    %% ── TRIAJE ─────────────────────────────────────────────────
    PR -->|"revisor adversarial<br/>circuito:revisar-backlog · <b>2 min</b>"| N6
    RI -->|"brief de decisión<br/>circuito:brief-c · <b>10 min</b>"| BR["preguntas escritas<br/>(confianza + reversible)"]
    BR -->|"síncrono al escribir el brief"| N5
    RI -->|"circuito:destrabe · <b>4 min</b>"| N7
    RI -->|"ThomasService::tick<br/>dentro del scheduler · <b>1 min</b>"| N3
    RI -->|"destrabar-bandeja · throttle 5 min"| N4

    %% ── DESPACHO ───────────────────────────────────────────────
    AC --> D{"scopeDespachable<br/>circuito:scheduler · <b>cada minuto</b>"}
    AR --> D
    AI --> D
    PR -->|"solo si nivel A"| D

    D -->|"NO: fuera del pool"| FUERA["excluir_pool_automatico<br/>esperando_merge_irving<br/>freno HUMANO<br/>nivel &gt; techo"]
    D -->|"NO: footprint en vuelo"| ESPERA["mismo módulo ocupado<br/>«Sin clasificar» bloquea a las 6"]
    D -->|"SÍ + slot libre"| CLAIM["claimNextParalelo<br/>UPDATE atómico + lease"]

    CLAIM --> EP
    EP -->|"vuelta.sh en wt-1..wt-6<br/>timeout 600s"| RAMA["rama circuito/item-N<br/>commits + reporte"]
    RAMA -->|"nivel A/B · auto"| MERGE["MergeRunner::drain<br/>dentro del scheduler · <b>1 min</b>"]
    RAMA -->|"nivel C con commits"| EMI["esperando_merge_irving<br/>parqueado en Integración"]
    EMI -->|"botón de la Torre<br/>o auto-merge de Thomas"| MERGE
    MERGE --> CO

    %% ── CALLEJONES ─────────────────────────────────────────────
    EP -.->|"worker muere<br/>reap-stuck · 2 min · <b>25 min fríos</b>"| RI
    N6 -.->|"3 escalaciones misma huella"| BUCLE["bloqueado_por_bucle<br/>+ fuera del pool"]
    RI -.->|"Irving pone el freno"| FRENO["origen_bloqueo = humano<br/><b>NO caduca nunca</b>"]
    FRENO -.->|"digest lo recuerda cada 7 días<br/>solo Irving lo quita"| RI
    RI -.-> RE
    RI -.-> CA

    classDef muerto fill:#fee2e2,stroke:#b91c1c,stroke-width:2px,color:#7f1d1d
    classDef freno fill:#fef3c7,stroke:#d97706,color:#78350f
    classDef ok fill:#dcfce7,stroke:#16a34a,color:#14532d
    class BUCLE,FUERA,ESPERA muerto
    class FRENO,EMI freno
    class CO ok
    class N4 muerto
```

> **`N4` va en rojo** no por su lógica sino porque el comando que lo invoca
> (`circuito:destrabar-bandeja`) **lleva 8 días fallando cada minuto**. Ese carril está dibujado
> porque existe en el código; hoy no se ejecuta.

---

## Tabla de consulta — «este item lleva días quieto, ¿por qué?»

| Estado / bandera | Qué significa | Quién lo saca de ahí | Cada cuánto |
|---|---|---|---|
| `pendiente_revision` | Recién creado, sin triar | Nivel A: el scheduler directo · B/C: el revisor | 1 min / 2 min |
| `requiere_irving` | La bandeja: espera decisión | Irving · autopilot (con brief) · des-trabador · Thomas | manual / 10 min / 4 min / 1 min |
| `aprobado_claude` | Nivel A autorizado por máquina | El scheduler lo reclama | **1 min** |
| `aprobado_revisor` | B/C autorizado por máquina | El scheduler lo reclama | **1 min** |
| `aprobado_irving` | Irving lo autorizó explícitamente | El scheduler (siempre pasa el tope de nivel) | **1 min** |
| `en_progreso` | Un worker lo tiene con lease | El propio worker · o `reap-stuck` si murió | — / **25 min** |
| `esperando_merge_irving` | C terminado, rama con commits | `MergeRunner` (botón o auto-merge) | 1 min tras encolar |
| `bloqueado_por_bucle` | 3 escalaciones con la misma huella | Un cambio material, o destrabe manual | manual |
| `excluir_pool_automatico` | Master switch fuera del pool | Quitarlo a mano, o `forzar=true` | manual |
| `origen_bloqueo = 'humano'` | **Freno de Irving. No caduca.** | **Sólo Irving** | recordatorio cada 7 días |
| `origen_bloqueo = 'clasificador'` | Consejo automático; **no frena** | Caduca a los 14 días… si `circuito:re-triage` corriera | ⛔ **no agendado** (#808) |
| `modulo` = «Sin clasificar» | Footprint desconocido: corre solo y bloquea a las 6 | `circuito:clasificar-modulo` | manual |
| `completado` / `cancelado` / `rechazado` | Terminales de verdad | — | — |
