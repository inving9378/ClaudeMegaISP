# Auditoría OLT multi-marca — estado real y plan de trabajo (Hoja de Ruta #57)

> Generado: 2026-07-15 · Circuito CC (worker wt-5) · **READ-ONLY** — código y config revisados,
> ninguna OLT en vivo fue tocada, cero escrituras ejecutadas.
> Actualiza y consolida `MULTIOLT_INVENTARIO.md` (2026-06-13), `MULTIOLT_FASES_AUTONOMAS.md`
> (2026-06-13), `MULTIOLT_SAAS_DISENO.md` (2026-06-13/16/07-13/14) y `MULTIOLT_PROVISION_HUAWEI.md`
> (W0, 2026-06-15) — esos documentos siguen vigentes como detalle histórico/técnico, pero **quedaron
> desactualizados** frente al código actual, sobre todo en el motor de escritura Huawei. Este
> documento es el punto de partida fresco para decidir el plan de #57.

---

## 1. Estado real por marca

### 1.1 Huawei — el más avanzado, con motor propio ya escribiendo (supervisado)

**Lectura (Bloque B1-B3): completa y en producción.**
- `HuaweiDriver` (`app/Services/OltDriver/Huawei/HuaweiDriver.php`, 1057 líneas) implementa
  `OltDriverInterface` con sesión Telnet única por operación (`withSession()`/`openSession()`/
  `closeSession()`), vía `HuaweiTransport` (424 líneas, TCP nativo `stream_socket_client`).
- Lectura: `listOlts`, `listSpeedProfiles`, `getUnconfiguredOnus`, `getOnusByOlt`, `getOnusSignals`,
  `getOnusStatus`, `getOnuDetails`, `getOnuSignal`, `getOnuStatus`, `findOnuBySn`,
  `listLineProfiles`, `listSrvProfiles` — todos implementados y parseados con 7 parsers dedicados
  (`OntListParser`, `OpticalInfoParser`, `OpticalBatchParser`, `OntInfoParser`, `AutofindParser`,
  `VersionParser`, `BoardParser`, `ProfileListParser`), probados contra fixtures reales de una
  MA5800-X7 (`docs/multiolt-w0-fixtures/`).
- Comando `gestionred:sync-huawei` corre cada 10 min en el Kernel — sync real de inventario/señales
  por Telnet, en producción.

**Escritura (Bloque B4-W1→W3): implementada, con dry-run por defecto y allow-list — YA NO es un
stub.** Esto contradice `MULTIOLT_INVENTARIO.md` (que decía "lanzan `WriteNotEnabledException`,
Bloque C aún no iniciado") — el código avanzó desde entonces:

| Método | Estado actual | Evidencia |
|---|---|---|
| `rebootOnu` | Implementado, dry-run por defecto, ejecución real posible | `HuaweiDriver.php:421` |
| `deauthorizeOnu` | Implementado (undo service-port + ont delete), dry-run por defecto | `HuaweiDriver.php:440` |
| `setOnuEnabled` | Implementado (ont activate/deactivate), dry-run por defecto | `HuaweiDriver.php:457` |
| `authorizeOnu` | Implementado (ont add + service-port), dry-run por defecto | `HuaweiDriver.php:514` |
| `setOnuSpeedProfile` | Implementado (parseo confirmado "ONT not found" en W3 dry-run) | `HuaweiDriver.php:563` |
| `descOnt` (extra, fuera de interfaz) | Implementado y ya usado como primer write real aprobado en W3 | `HuaweiDriver.php:481` |

- Flag `dry_run` (`private readonly bool $dryRun`, default `true` — "safe default: always dry-run").
- `ReadOnlyGuard::WRITE_ALLOW_LIST` = **allow-list explícita por SN** (`['HWTCFEFCC9A2']` hoy —
  un solo ONT de laboratorio). Cualquier SN fuera de la lista es rechazado antes de cualquier I/O
  (`WriteTargetDeniedException`).
- `abort-on-error`: `WriteExecutionAbortedException` corta la secuencia si un paso VRP responde con
  error, evitando dejar el ONT a medio provisionar.
- Campo `olts.motor_modo` (enum `smartolt`/`propio`, migración `2026_06_16_100000`) — **hoy las 3
  OLTs de producción siguen en `smartolt`**; el flip a `propio` (activación funcional real) no se
  ha hecho en ninguna. Es puramente informativo hasta que se active OLT por OLT con Irving presente.
- `ProvisionPreCheckService` (246 líneas) — valida antes de `ont add` (ONT no provisionado, puerto/
  slot existe, perfiles existen, VLAN existe, ont-id libre).
- **Decisión vigente de Irving** (`MULTIOLT_SAAS_DISENO.md` §9, item #415, reafirmada 2026-07-14):
  retomar la paridad de escritura, priorizando alta/baja/suspensión de ONU, **con condición dura**:
  todo write nuevo se prueba primero contra una OLT de laboratorio, nunca directo a campo.
- **No implementa ninguna capacidad `Supports*` opcional** (`SupportsCatalog`,
  `SupportsOltTopology`, `SupportsOnuPorts`, `SupportsVoip`, `SupportsCatv`,
  `SupportsVlanManagement`, `SupportsAdvancedOnuConfig`, `SupportsAdvancedDiagnostics`,
  `SupportsBulkOperations`) — todas esas quedan exclusivas de `SmartOltDriver` por ahora. El
  driver Huawei solo cubre el contrato base de `OltDriverInterface`.

**Tests:** 329 tests verdes (1524 assertions) en `tests/Unit/OltDriver/*` — corridos en esta
auditoría (`php artisan test --filter=OltDriver`), 100% mocks/fixtures, **sin tocar OLT real**.
Cubre driver, command builder, transport, ReadOnlyGuard, TelnetSession, W3WriteCommand,
WriteExecution y ProvisionPreCheckService. Supera el conteo de "238 tests" citado en el inventario
de junio — confirma que hubo trabajo real posterior no documentado en ese archivo.

### 1.2 ZTE — cero código, ni siquiera el stub recomendado

- `grep -ril "zte" app/ config/` → **un solo falso positivo** (`BancosCatalogo.php`, coincide con
  "azteca", nada de OLT).
- `Olt::DRIVER_ZTE` **no existe** (solo `DRIVER_SMARTOLT` y `DRIVER_HUAWEI` en `app/Models/Olt.php`).
- `MULTIOLT_SAAS_DISENO.md` §5 ya recomendaba (2026-06-13) crear solo un stub `NullZteDriver` que
  lance `RuntimeException('ZTE driver no implementado')` para reservar el espacio — **esa
  recomendación nunca se ejecutó**. Sigue en cero, tal cual estaba hace un mes.
- Razón documentada y aún vigente: no hay ninguna OLT ZTE en el inventario real (las 3 OLTs de
  producción son Huawei), y los protocolos ZTE varían mucho por modelo/firmware — implementar sin
  hardware de referencia produciría un driver sin verificar.

### 1.3 V-SOL — cero código, cero mención en ningún documento previo

- `grep -ril "vsol\|v-sol"` sobre `app/`, `resources/`, `docs/`, `config/` → **cero resultados**.
- No aparece en `MULTIOLT_SAAS_DISENO.md` (que sí cubre ZTE como "segundo driver" candidato) ni en
  ningún otro documento MultiOLT. El título del item #57 lo menciona como parte del alcance final
  ("Huawei + ZTE + V-SOL"), pero **no hay ninguna auditoría, diseño ni siquiera mención previa de
  V-SOL en el repo** — es la marca menos explorada de las tres, por debajo incluso de ZTE.
- No hay constante `Olt::DRIVER_VSOL`, ni driver, ni fixtures, ni documento de protocolo.

### 1.4 SmartOLT — capa de compatibilidad multi-marca "de facto" (no un objetivo del item, pero es el estado real hoy)

- `SmartOltDriver` (324 líneas) implementa `OltDriverInterface` + las 9 capacidades `Supports*`
  completas, delegando en `OLTsService` (~1310 líneas) — el servicio HTTP más maduro del módulo.
  SmartOLT en sí ya soporta múltiples marcas de OLT (incluyendo Huawei, ZTE y otras) por su propia
  cuenta, pero **eso es exactamente lo que el item #57 busca reemplazar** (variante B del item:
  "conexión a SmartOLT para ISPs ya casados con ese servicio" — sigue siendo la opción de fallback,
  no la de independencia).
- Las 3 OLTs reales (`olts.driver`) están **todas en `smartolt`** hoy, incluso las que ya tienen
  motor Huawei propio leyendo en paralelo vía `gestionred:sync-huawei`. El flip de `driver` (routing
  de operaciones) a `huawei` por fila sigue siendo manual y no se ha aplicado a ninguna en prod.

---

## 2. Qué comparte la arquitectura entre marcas y qué es específico

### 2.1 Compartido (ya diseñado para ser multi-marca)

| Pieza | Rol |
|---|---|
| `OltDriverInterface` (15 métodos: lectura + escritura) | Contrato único que cualquier marca debe implementar |
| 9 interfaces `Supports*` (`Capabilities/`) | Capacidades opcionales (catálogo, topología, VoIP, CATV, VLANs, diagnóstico avanzado, bulk) — un driver puede implementar solo el subset que su marca soporta |
| `OltDriverManager` | Resuelve el driver por la columna `olts.driver` (`match()` — agregar una marca = un caso más en el match) |
| `Olt::motor_modo` | Campo por-OLT que decidirá, marca por marca, si las escrituras van por el motor propio o por SmartOLT (hoy solo aplica a Huawei) |
| Las 13 tablas `olt_*` | Esquema agnóstico de marca (SN, señal, board/port, VLANs, etc.) — no hay columnas Huawei-específicas en el esquema |
| `ReadOnlyGuard` (patrón, no la clase en sí) | Patrón de allow-list + dry-run + abort-on-error es reusable como diseño para cualquier marca con CLI/Telnet, aunque la implementación concreta (comandos VRP) es 100% Huawei |
| 73 rutas API + ~75 componentes Vue | Consumen el contrato del driver activo, no hardcodean Huawei (aunque en la práctica hoy solo ejercitan `SmartOltDriver` en producción) |

### 2.2 Específico por marca (no reusable tal cual)

| Pieza | Marca | Por qué no es portable |
|---|---|---|
| `HuaweiTransport` + `TelnetSession` + `ReadOnlyGuard` (comandos concretos) | Huawei | Protocolo Telnet + sintaxis VRP (space-eater bug, prompts `(y/n)`, paginador `---- More ----`) son de este vendor/firmware |
| 7 parsers (`OntListParser`, `OpticalInfoParser`, etc.) | Huawei | Parsean el output textual exacto de comandos `display ...` de VRP V100R018 |
| `HuaweiCommandBuilder` | Huawei | Genera strings de comandos VRP (`ont add`, `service-port`, `undo service-port`) |
| `ProvisionPreCheckService` | Huawei (pero el patrón de pre-checks es genérico) | Los checks concretos (`display ont info by-sn`, `display board`) son sintaxis VRP |
| `OLTsService` (HTTP a SmartOLT API) | SmartOLT (no es "una marca" — es un agregador que ya habla con varias OLTs por su cuenta) | Endpoints REST propios de SmartOLT, no de ningún fabricante |

**Conclusión de diseño:** la abstracción (`OltDriverInterface` + capacidades + manager) ya está
lista para sumar marcas sin tocar el resto del sistema — es la parte que **no** hay que rehacer.
Lo que falta es 100% trabajo nuevo por marca: transporte + parsers + command builder + guard, tal
como se hizo para Huawei.

---

## 3. Brecha para multi-marca completo (priorizada)

1. **ZTE — ni el stub existe.** Bloqueador real: no hay hardware ZTE en el inventario de Meganet
   para validar contra equipo real (mismo hallazgo que en junio, sigue sin resolverse). Sin acceso a
   una OLT ZTE (modelo específico) o a un ISP cliente que preste acceso, cualquier `ZteDriver` sería
   código sin verificar — alto riesgo de bugs silenciosos en producción de un cliente.
2. **V-SOL — no hay ni auditoría previa.** Más atrás que ZTE: no hay ni siquiera el análisis de qué
   necesitaría un driver V-SOL (autenticación, protocolo CLI/SNMP/REST según modelo). Antes de
   diseñar hay que decidir si V-SOL es prioridad real (¿algún ISP piloto lo usa?) — hoy no hay
   evidencia de que Meganet u otro ISP objetivo tenga hardware V-SOL.
3. **Huawei — cerrar el ciclo de escritura antes de sumar marcas nuevas.** El motor Huawei ya
   escribe en dry-run + un write real supervisado (W3, `descOnt`); faltan por implementar en vivo
   contra una OLT de laboratorio (según la decisión #415 vigente): alta/baja/suspensión de ONU real,
   luego cambio de perfil de velocidad (B5), luego service-ports/VLANs. Recomendación: **no abrir
   ZTE/V-SOL hasta que Huawei tenga paridad completa de escritura validada en lab** — repartir
   esfuerzo entre 3 frentes de escritura a la vez multiplica el riesgo de red en vivo sin ganar
   velocidad real (un solo ingeniero/circuito, no un equipo).
4. **GR-6 — modelos OLT fuera del módulo (`app/Models/Olt*.php`).** No bloquea sumar marcas, pero sí
   bloquea el Camino A de multi-tenant (SaaS con un solo deploy) documentado en
   `MULTIOLT_SAAS_DISENO.md` §4. Si el plan de negocio real es "instancia por ISP" (Camino B,
   recomendado en ese documento), GR-6 dejar de ser bloqueante — se vuelve limpieza opcional.
5. **`Supports*` en HuaweiDriver.** Ninguna de las 9 capacidades opcionales está implementada para
   Huawei — hoy VoIP, CATV, gestión avanzada de VLANs, etc. solo funcionan vía SmartOLT. Si el
   objetivo es "replicar funciones principales de SmartOLT" (como dice el alcance del item #57),
   esta es la lista concreta de qué falta en Huawei más allá del ciclo CRUD básico de ONT.

---

## 4. Plan de trabajo por fases

> Todas las fases de escritura real (Fase B en adelante) requieren OLT de laboratorio + presencia de
> Irving, por la condición explícita del item #415. Ninguna fase de este plan autoriza tocar OLTs de
> producción por sí sola.

| Fase | Contenido | Depende de | Riesgo | Esfuerzo aprox. |
|---|---|---|---|---|
| **A — Cerrar escritura Huawei (alta/baja/suspensión) en laboratorio** | Continuar W3→W4: probar `authorizeOnu`/`deauthorizeOnu`/`setOnuEnabled` reales contra OLT de lab (no en `WRITE_ALLOW_LIST` de producción), expandir allow-list solo tras validación | OLT de laboratorio accesible (condición ya puesta por Irving en #415) | Medio (mitigado por dry-run + allow-list + abort-on-error, ya existentes) | 1-2 sesiones |
| **B — Cambio de perfil de velocidad (B5) + service-ports/VLANs** | `setOnuSpeedProfile` real + gestión de VLANs por ONT | Fase A validada | Medio-Alto (toca planes contratados = dinero indirecto vía Promociones) | 2-3 sesiones |
| **C — Capacidades `Supports*` para Huawei** | Implementar VoIP/CATV/VLANs avanzadas/diagnóstico si el negocio las necesita para paridad con SmartOLT | Fase B | Bajo-Medio (mayormente lectura + writes ya cubiertos por el patrón) | Variable por capacidad |
| **D — Decisión de negocio ZTE/V-SOL** | Antes de escribir código: confirmar con Irving qué ISP piloto (interno o cliente) tiene hardware ZTE o V-SOL real disponible para pruebas; sin esto cualquier driver nuevo es teórico | Decisión de Irving | Bajo (es decisión, no código) | — |
| **E — Stub ZTE (solo si D confirma acceso a hardware a mediano plazo)** | `Olt::DRIVER_ZTE` + entrada en `OltDriverManager` apuntando a `NullZteDriver` (lanza excepción clara) — reserva el espacio sin código sin verificar | D | Bajo (aditivo, no ejecuta nada) | Trivial |
| **F — `ZteDriver` real** | Implementación completa contra hardware ZTE confirmado | E + acceso a hardware | Alto si se hace sin validar en lab | Grande (equivalente a todo el Bloque B de Huawei) |
| **G — Auditoría + diseño V-SOL** | Repetir el ejercicio que ya se hizo para ZTE en `MULTIOLT_SAAS_DISENO.md` §5: qué protocolo usa, qué necesitaría el driver, viabilidad | D (si V-SOL resulta prioridad) | Bajo (solo análisis) | 1 sesión |
| **H — `VsolDriver`** | Implementación completa, análoga a F | G + acceso a hardware | Alto si se hace sin validar en lab | Grande |
| **I — GR-6 (opcional, no bloqueante)** | Encapsular los 13 modelos OLT dentro del módulo `GestionRed` | Ninguna (deuda técnica propia) | Medio (~60 puntos de código a mover, alto blast radius si se hace mal) | 1-2 sesiones, solo si se decide ir por Camino A de multi-tenant |

**Recomendación de secuencia:** A → B → D. Las fases E-H (ZTE/V-SOL reales) están bloqueadas por
falta de hardware, no por falta de diseño — no tiene sentido adelantar código antes de resolver esa
dependencia externa. C e I son independientes y pueden intercalarse según prioridad de negocio.

---

## 5. Confirmación de alcance de esta auditoría

- **Solo lectura de código/config/BD.** Ninguna sesión Telnet fue abierta contra una OLT real en
  este trabajo; los 329 tests corridos usan mocks/fixtures, no hardware.
- **No se modificó ningún driver, modelo, ruta ni componente.** Este documento es el único artefacto
  nuevo.
- El supuesto de partida del item #57 ("módulo MultiOLT con avance real: motor nativo Huawei por
  Telnet llegó a W3, ~330 tests") se confirma con evidencia directa y se actualiza: el motor de
  escritura avanzó más allá de W3 (ya cubre authorize/deauthorize/enable/reboot/speed-profile en
  dry-run + un write real supervisado), y el conteo de tests real hoy es **329** (no ~330, pero
  consistente).
- El supuesto de "Huawei + ZTE + V-SOL" como alcance final se confirma como **correcto en dirección,
  pero sin ningún trabajo previo en ZTE (salvo el diseño de junio, no ejecutado) ni en V-SOL (cero
  trabajo previo, ni siquiera análisis)**.
