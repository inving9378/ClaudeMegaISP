# Auditoría — Módulo OLT propio multi-marca (item #57 de la Hoja de Ruta)

> Generado: 2026-07-12 · Circuito CC (ejecutor on-box, worktree aislado) · **100% read-only** — no se
> tocó red viva, credenciales ni el driver activo de ninguna OLT real.
> Alcance: exactamente lo que pide el item #57 — auditar el estado actual y armar un plan de fases.
> **No se escribió código nuevo** (fuera de este documento y los items de Hoja de Ruta derivados).

---

## 0. Hallazgo principal — el item #57 ya tiene una auditoría y un diseño previos

El item #57 se creó el **2026-06-01**, pidiendo "auditar el módulo OLT actual" y "armar plan de
trabajo" para un módulo OLT propio multi-marca (Huawei primero, después ZTE/V-SOL). Se quedó
`pending` en la Hoja de Ruta durante 6 semanas.

**Mientras tanto, entre el 2026-06-10 y el 2026-06-16, corrió una iniciativa completa llamada
"MultiOLT"** (67 commits en `main`, fuera del circuito de items numerados de esta Hoja de Ruta) que
hizo exactamente ese trabajo: auditó el módulo, diseñó la abstracción de drivers, la implementó para
Huawei con paridad de lectura completa y arrancó la paridad de escritura. Dejó tres documentos vivos:

| Documento | Contenido |
|---|---|
| `docs/MULTIOLT_INVENTARIO.md` | Auditoría completa de estructura: módulo, tablas (13), modelos, controllers, rutas, permisos, dispersión módulo/`app/Models` (deuda GR-6) |
| `docs/MULTIOLT_SAAS_DISENO.md` | Diseño multi-tenant SaaS (Camino A: `tenant_id` compartido vs Camino B: instancia por ISP) + plan ZTE + modelo de motor de escritura Huawei (fuente de verdad, independencia, `motor_modo`) |
| `docs/MULTIOLT_PROVISION_HUAWEI.md` | Captura de campo real de la OLT `MEGANETWORKX7` (perfiles, T-CONT/DBA, sintaxis VRP verificada) |
| `docs/MULTIOLT_FASES_AUTONOMAS.md` | Bitácora de fases 1-3 (cron, mapa, alertas PON) |

**Este documento NO repite esa auditoría** (sería trabajo duplicado, contra la regla de minimalismo).
Su función es: (a) confirmar que ese trabajo sigue vigente y sin regresión, (b) actualizar el estado
al día de hoy, y (c) convertirlo en el "plan de trabajo en items concretos" que pide el item #57,
con las decisiones de negocio/arquitectura correctamente escaladas a Irving (nivel C).

---

## 1. Estado actual verificado (2026-07-12)

### 1.1 Arquitectura de drivers — ya existe y está en uso

```
App\Services\OltDriver\
├── OltDriverInterface.php     — contrato núcleo (17 métodos: listado, lectura de ONU/señal/estado, ciclo de vida)
├── OltDriverManager.php       — resuelve driver por Olt::$driver ('smartolt'|'huawei')
├── SmartOltDriver.php         — wrappea App\Services\OLTsService (API SmartOLT actual, 324 líneas)
├── Capabilities/Supports*.php — 8 interfaces opcionales (VLAN, VoIP, CATV, puertos, topología, bulk, catálogo, diagnósticos avanzados)
└── Huawei/
    ├── HuaweiDriver.php           (1057 líneas) — implementa OltDriverInterface completo
    ├── HuaweiTransport.php        — sesión Telnet (puerto 23) hacia la OLT real
    ├── HuaweiCommandBuilder.php   — construye comandos VRP (Huawei CLI)
    ├── Parsers/*.php (7 parsers)  — AutofindParser, BoardParser, OntInfoParser, OntListParser,
    │                                OpticalBatchParser, OpticalInfoParser, ProfileListParser, VersionParser
    ├── ReadOnlyGuard.php           — whitelist de comandos de solo-lectura (guardrail duro)
    └── ProvisionPreCheckService.php — valida antes de provisionar (compara contra SmartOLT como referencia)
```

`App\Models\Olt` ya tiene las constantes `DRIVER_SMARTOLT`/`DRIVER_HUAWEI` y una columna
`motor_modo` (enum `smartolt`|`propio`, hoy **puramente informativa** — badge en UI, no cambia
comportamiento; ver `MULTIOLT_SAAS_DISENO.md` §8.5).

**Cobertura de pruebas:** 159 tests unitarios en `tests/Unit/OltDriver/` (10 archivos) contra
fixtures/fakes — **ninguno toca red real**. No se ejecutaron en esta auditoría (correr `php artisan
test` sin `.env.testing` dedicado arriesgaría un `migrate:fresh --seed` contra la BD `megaisp` viva,
prohibido por convención del proyecto). Recomendación en la sección 3.

### 1.2 Qué tan lejos llegó la paridad de escritura Huawei

| Operación | Prioridad (frecuencia) | Estado a hoy |
|---|---|---|
| Reboot ONT | Alta (diaria) | Implementado, **dry-run** (W1) |
| Activar/Desactivar ONT | Alta (diaria) | Implementado, **dry-run** (W1) |
| Autorizar ONT nuevo | Media (semanal) | Implementado, **dry-run** (W1) |
| Desautorizar ONT | Media (semanal) | Implementado, **dry-run** (W1) |
| Escritura real ejecutada (`ontDesc`/`descOnt`, cosmética y reversible) | — | **W3: primer y único write real ejecutado**, supervisado, contra la OLT de referencia |
| Cambio de perfil de velocidad | Media (semanal) | **Pendiente (B5)** — no implementado |
| Service-ports / VLANs | Baja (eventual) | **Pendiente (futuro)** — no implementado |

### 1.3 Estado real de las 3 OLTs en producción-dev

```
id=2  HUAWEI MA5608T     driver=smartolt
id=3  MEGANETWORKX7      driver=smartolt
id=4  MX7 Zumpango        driver=smartolt
```

**Ninguna OLT real tiene el driver Huawei activo.** Todo el trabajo de escritura Huawei vive detrás
de un flip manual documentado (`OltDriverManager.php` líneas 16-39) que nadie ha ejecutado en
producción. El sistema sigue operando 100% vía SmartOLT hoy — cero riesgo de regresión por esta
auditoría o por el trabajo MultiOLT ya hecho.

### 1.4 La iniciativa se detuvo hace casi 4 semanas, sin cierre documentado

Último commit con prefijo `MultiOLT`/`multiolt`/`olt/huawei`: **2026-06-16 18:27** (`c34dbd37`,
"W3: fix lookup SN en interface-context"). Cero actividad desde entonces. `docs/bitacora-sesiones.md`
no tiene ninguna entrada que mencione OLT/Huawei/MultiOLT — no hay registro de si Irving pausó el
trabajo a propósito, si quedó esperando una decisión, o si simplemente se perdió de vista entre
otras prioridades. Tampoco existe un roadmap item dedicado a esas fases (B4+/B5/W4) — el trabajo
vive solo en los commits y los 4 docs de `docs/MULTIOLT_*`.

### 1.5 Seguridad — ya se corrigieron 3 huecos relacionados (independiente de este item)

El **2026-07-08**, items separados de la Hoja de Ruta (#281, #285, #287 — todos `completado`)
cerraron: escrituras a ONU (password web/CATV) que quedaban gateadas bajo el permiso de *lectura*
`olt_view`, rutas OLT/ONU con mapeos de permiso muertos en `route_permission.php`, y la coexistencia
de dos mecanismos de autorización sin re-chequeo cruzado en los controladores OLT.

**Sigue pendiente** (documentado en `CLAUDE.md`, sección "Fase 3a-bis", sin item propio en la Hoja
de Ruta hasta ahora): `olt_view`/`onu_add`/`onu_edit` **no** pasan por `config('route_permission')`
como el resto del sistema — tienen su propio verificador de permisos **directos** dentro de
`OLTsController`/`OLTsOnuController`, sin honrar el flip directos∪rol que ya se aplicó al resto del
sistema (Fase 3a, `708bcba0`). Es el verificador real del "caso Alondra" (provisión de ONTs).

### 1.6 SaaS multi-tenant y ZTE — ya diseñados en papel, ambos bloqueados por decisión de Irving

- **Multi-tenant:** `MULTIOLT_SAAS_DISENO.md` ya comparó Camino A (`tenant_id` compartido, bloqueado
  por la deuda GR-6 de modelos fuera del módulo) vs Camino B (instancia Laravel separada por ISP,
  recomendado). Sin decisión de Irving registrada.
- **ZTE:** diseño de stub (`Olt::DRIVER_ZTE` + `NullZteDriver` que lanza excepción) ya está
  especificado, pero **deliberadamente no implementado** — no hay ninguna OLT ZTE accesible para
  validar contra hardware real, y los protocolos varían demasiado por modelo/firmware como para
  escribir un driver a ciegas. Correcto dejarlo así hasta que exista una OLT ZTE o un ISP cliente
  con ZTE dispuesto a validar.

---

## 2. Tabla de hallazgos

| # | Hallazgo | Severidad | Acción propuesta |
|---|---|---|---|
| H1 | El item #57 pide un trabajo que ya se hizo en un 80-90% (auditoría + diseño de drivers + implementación Huawei de lectura completa) | Informativo | Cerrar #57 referenciando el trabajo existente + este doc, en vez de re-auditar desde cero |
| H2 | La iniciativa MultiOLT se detuvo sin cierre ni roadmap item que la rastree — riesgo de que se pierda el contexto (~4 semanas ya) | Media | Crear items concretos de continuación (sección 3) para que quede en el radar del circuito |
| H3 | Decisión de negocio pendiente: ¿retomar la paridad de escritura Huawei (perfil de velocidad, service-ports/VLANs) o congelarla en el punto actual? | Alta (decisión C) | Escalar a Irving — ver item propuesto C |
| H4 | Decisión de arquitectura pendiente: Camino A vs B para SaaS multi-tenant | Alta (decisión C) | Escalar a Irving — ver item propuesto D |
| H5 | Verificador de permisos propio de OLT (`olt_view`/`onu_add`/`onu_edit`) no honra el flip directos∪rol ya aplicado al resto del sistema (Fase 3a-bis) | Media (seguridad, acotada) | Item concreto nivel B — ver item propuesto B |
| H6 | 159 tests unitarios de `OltDriver` no se han vuelto a correr en ~4 semanas de cambios en el resto del repo; no hay evidencia de que sigan verdes | Baja | Item de higiene nivel A — ver item propuesto A |
| H7 | ZTE: diseño correcto de "no construir sin hardware". Sin acción pendiente hasta que exista una OLT ZTE o cliente piloto | Ninguna | Ninguna acción ahora — queda documentado para cuando aplique |

---

## 3. Plan de trabajo — items concretos creados en la Hoja de Ruta

Se crearon 4 items nuevos, derivados de esta auditoría, cada uno con el nivel de riesgo mínimo que
le corresponde según la frontera dura (dinero/seguridad/prod/negocio):

| Item | Título | Nivel | Depende de |
|---|---|---|---|
| **#413** | Ejecutar suite de tests `OltDriver`/Huawei en entorno aislado (higiene, sin red real) | A | Ninguno |
| **#414** | Alinear verificador de permisos propio de OLT al flip directos∪rol (Fase 3a-bis, caso Alondra) | B | Ninguno |
| **#415** | Decisión: retomar o congelar la paridad de escritura Huawei (perfil de velocidad, service-ports/VLANs) | C | Ninguno — decisión de Irving |
| **#416** | Decisión: arquitectura SaaS multi-tenant — Camino A vs Camino B | C | Ninguno — decisión de Irving |

---

## 4. Qué NO se hizo en esta auditoría (a propósito)

- No se tocó ninguna OLT real, ni por lectura ni por escritura.
- No se cambió el `driver` de ninguna fila de `olts`.
- No se corrieron los 159 tests (para no arriesgar `migrate:fresh` contra la BD viva sin
  `.env.testing` dedicado — queda como item A propuesto, con esa salvedad explícita).
- No se implementó el stub ZTE ni el `NullZteDriver` (correcto esperar decisión/hardware).
- No se resolvió H3 ni H4 — son decisiones de negocio/arquitectura exclusivas de Irving.

---

_Documento generado por el Circuito CC como entregable del item #57. Fuentes: lectura directa del
código (`app/Services/OltDriver/`, `app/Models/Olt.php`), `git log`, los 4 docs `MULTIOLT_*`
existentes, `docs/bitacora-sesiones.md`, y consulta directa a la tabla `roadmap_items`. Sin
secretos, sin credenciales, sin comandos ejecutados contra hardware real._

---

## 5. Re-verificación 2026-10-22 (vuelta del circuito, solo lectura)

Estado contra la BD y el código de dev, sin tocar datos:

- **Drivers:** `OltDriverManager` resuelve `smartolt` (`SmartOltDriver`), `huawei` (`HuaweiDriver`, 1367 líneas,
  implementa topología y diagnóstico avanzado) y `zte` (`NullZteDriver`, stub que lanza excepción). V-SOL: sin driver.
- **BD dev:** `olts`=3 (todas con `driver=smartolt` y `motor_modo=smartolt`; el flip a Huawei sigue sin hacerse),
  `olt_onus`=3176 (3124 ligadas a cliente, 52 sin), `olt_cards`=20, `olt_pon_ports`=160, `olt_zones`=518,
  `olt_odbs`=671, `olt_type_onus`=47, `olt_vlans`=37, `olt_speed_profiles`=31, `olt_smartolt_config`=0.
- **Crons activos** (`Kernel.php`): `smartolt:sync-inventory` 05:00, `smartolt:sync-clients-with-ont` 05:30,
  `smartolt:sync-critical` cada 10 min, `gestionred:sync-huawei` cada 10 min.
- **Integraciones:** Mapa de red (`OltRedMap.vue`, `/olts/mapa-red`), ficha de Clientes (`olt_onus.client_id`,
  `InformationClientCrud.vue`), discrepancias SN (`DiscrepanciasSnReport.vue`).
- **Conclusión:** la auditoría de la sección 1 sigue vigente y sin regresión; el plan de la sección 3 no cambia.
  Lo pendiente (ZTE con hardware piloto, V-SOL, decisión SaaS Camino A/B) sigue siendo decisión de Irving.
