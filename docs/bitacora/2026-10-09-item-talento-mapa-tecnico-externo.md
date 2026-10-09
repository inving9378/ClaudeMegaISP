## 2026-10-09 — "Dónde debo ir hoy", Fase 4/4 del dashboard por rol (técnico externo)

### Contexto

Irving: "termina lo que encontraste ayer". La bitácora del 2026-10-08 dejó dos cosas
explícitamente sin cerrar: (1) un bug de login (TECNICO_PLANTA/TECNICO_INSTALADOR no
podían entrar, hallazgo de la pieza "Mi equipo") y (2) la Fase 4 del plan de dashboards
por rol — "Técnico Externo con mapa" — marcada "pendiente, requiere más investigación".
Se resolvieron ambas.

### 1. Fix de login (TECNICO_PLANTA/TECNICO_INSTALADOR)

`LoginController::STAFF_ROLES` tenía `TECNICO` pero no sus dos variantes reales. Agregadas
ambas. Verificado con usuario de prueba (rol único TECNICO_PLANTA): pasaba de no poder
entrar a poder entrar. Mergeado a `main` directo (cambio de una línea, sin riesgo).

### 2. Fase 4 — mapa del técnico externo

**Investigación:** "Técnico Interno/Externo" no es el enum `talento_colaboradores.type`
(ese campo es sobre otra clasificación, personal externo administrativo, y está en 0
colaboradores con valor 'externo' en dev — no aplica aquí). La distinción real ya existía
en `Actor::trackPrincipal()`: `TECNICO_PLANTA` → `tecnico_planta_interna`, `TECNICO`/
`TECNICO_INSTALADOR` → `tecnico_planta_externa`. Solo faltaba una faceta nueva que la
exponga (`esTecnicoExterno()`) y el feature que la consuma.

**Diseño:** en vez de una pantalla nueva, una tarjeta más DENTRO de "Mi progreso" (mismo
patrón ya validado en la Fase 1) — visible solo si el técnico es de track externa. Pinea
los trabajos pendientes/en curso (los que SÍ necesitan visita) usando la coordenada real
del cliente (`client_main_information.geodata`), **no** `talento_work_orders.latitude/
longitude` (esa columna solo se llena por evidencia DESPUÉS del trabajo — inútil para
planear a dónde ir). Leaflet UMD (mismo archivo ya servido en `public/assets/libs/leaflet/`
para los mapas del admin), sin build propio — el Portal sigue siendo JS a mano.

**Verificado:**
- Backend (tinker, datos reales): colaborador de prueba con 3 OTs pendientes (clientes 17
  y 19, con geodata real, + un cliente sin geodata) + 1 completada → el mapa devuelve
  exactamente 2 puntos con lat/lng/dirección reales, cuenta 1 "sin_coordenadas", excluye la
  completada.
- Navegador real (Playwright): técnico externo de prueba → tarjeta "Dónde debo ir hoy"
  visible, 2 marcadores dibujados, popup del primero con el nombre y dirección reales del
  cliente 17, aviso de 1 cliente sin coordenada. Técnico de planta de prueba → "Mi
  progreso" se ve igual pero SIN la tarjeta del mapa (el candado `es_externo` funciona).
  Sin errores de consola nuevos (el 401 de `/permissions-auth` es ruido preexistente de la
  herramienta de prueba, ya documentado en sesiones previas — no es un bug de esta pieza).
- Datos de prueba (2 usuarios, 2 colaboradores, 3 OTs) borrados y confirmados en cero.
  Ningún cliente/dirección real se modificó — solo se leyó su geodata ya existente.

### Estado

Ambos cambios mergeados a `main`. Con esto, las 4 fases del plan de dashboards por rol del
Portal de Colaborador (pedido en la llamada del 2026-10-08) quedan completas: técnico,
vendedor, mostrador, mapa del técnico externo — más "Mi equipo" para supervisión.

### Fuera de alcance (sin decidir, no urgente)

- Cuotas semanales del vendedor (mencionado en la llamada original, ligado a las reglas de
  desempeño) — sigue sin construirse, es trabajo nuevo, no reutilización.
- Zoom/ruta óptima entre los puntos del mapa — hoy solo pinea y centra, no traza ruta.
- El `talento_colaboradores.type` ('interno'/'externo', personal administrativo) sigue sin
  usarse en ningún feature — no se tocó, es un campo distinto al de esta pieza.
