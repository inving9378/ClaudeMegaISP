# MultiOLT — Diseño SaaS Multi-tenant + ZTE

> Documento de auditoría y diseño. **Cero código de implementación ni migraciones.**
> Generado: 2026-06-13. Para validar con Irving antes de ejecutar cualquier paso de Bloque C.

---

## 1. Contexto: Medussa no es un sistema de VMs

Antes de diseñar, un aclaración crítica que afecta la elección de arquitectura:

**Medussa** es el nombre de la plataforma MegaISP (este codebase). **No es** un
orquestador de VMs externo ni un sistema de aprovisionamiento automático de instancias
por ISP. Las referencias a "Medussa" en la documentación interna (`docs/patron-responsive-
medussa.md`, `docs/como-migrar-modulo-al-estandar.md`) se refieren al estándar de
arquitectura de módulos de este mismo sistema.

Por tanto, la opción "VM por ISP al estilo Medussa" se reinterpreta como:
**(b') instancia Laravel completa por ISP** — un deploy separado de este mismo código en
servidor distinto por cada cliente ISP. Esta es la opción real comparable.

---

## 2. Los dos caminos de aislamiento multi-tenant

### Camino A — `tenant_id` por fila en las 13 tablas OLT

Agregar `tenant_id` (o `isp_id`) a las 13 tablas del módulo GestionRed:
`olts`, `olt_onus`, `olt_odbs`, `olt_pon_ports`, `olt_cards`, `olt_speed_profiles`,
`olt_type_onus`, `olt_unconfigured_onus`, `olt_uplink_ports`, `olt_vlans`,
`olt_zones`, `olt_billings`, `olt_interruption_pons`.

También a la tabla de config: `olt_smartolt_config` (hoy una sola fila → multi-fila).

**Pros:**
- Un solo deploy, base de código compartida.
- Actualizaciones centralizadas: parchear una vez, todos los ISPs se benefician.
- Costo de infraestructura fijo (un servidor + una DB con particionamiento lógico).
- Más simple de monitorear (logs centralizados, backups unificados).

**Contras:**
- Riesgo real de data-leakage si un query olvida el filtro `tenant_id`. Un bug expone
  datos de todos los ISPs a uno. En contexto ISP esto es inaceptable (datos de clientes).
- Require reescribir o envolver **todos** los Eloquent queries del módulo GestionRed.
  Hoy ninguna tabla tiene `tenant_id` — la deuda de migración es grande.
- Los modelos están en `app/Models/` (no encapsulados en el módulo) — esto hace difícil
  forzar el scope de tenant de forma confiable (ver §4 sobre GR-6).
- Las credenciales SmartOLT (`olt_smartolt_config`) tendrían una fila por ISP, pero
  el singleton `app('SmartOlt')` en `AppServiceProvider` asume una sola configuración.
  Reescribirlo para multi-tenant rompe el flujo actual.

### Camino B — Instancia Laravel por ISP (deploy separado)

Cada ISP cliente obtiene su propia instancia del sistema (código idéntico, config distinta).
Aislamiento completo a nivel de proceso y base de datos.

**Pros:**
- **Aislamiento perfecto:** un bug en el ISP A nunca filtra datos del ISP B.
  Esto es un requisito implícito en LFPDPPP cuando se manejan datos de terceros.
- Cero refactor del módulo actual. Las 13 tablas OLT quedan como están.
- `olt_smartolt_config` sigue siendo una sola fila (el ISP tiene su propio dominio SmartOLT).
- El singleton `app('SmartOlt')` funciona sin cambios.
- Credenciales, permisos, roles: completamente aislados por DB.
- Cada ISP puede tener su propio dominio/subdominio (branding).

**Contras:**
- Costo de infraestructura escala linealmente con ISPs.
- Actualizaciones requieren desplegar a N instancias (mitigable con CI/CD centralizado).
- Monitoreo más complejo (N logs, N bases de datos).
- Requiere un panel de control "meta" para Meganet que agregue alertas de todos los ISPs
  (puede ser un dashboard separado que consulta cada instancia vía API).

---

## 3. Recomendación

**Camino B (instancia por ISP) es el camino correcto en este momento**, por dos razones:

1. **GR-6 bloquea el Camino A** (ver §4). Sin refactorizar los modelos fuera de `app/Models/`
   hacia el módulo, es imposible aplicar un global scope de tenant de forma confiable.
   El Camino A sin GR-6 es un imán de bugs de data-leakage.

2. **La infraestructura de credenciales ya asume un ISP por instalación** (`olt_smartolt_config`
   tiene una sola fila, el singleton de Guzzle lee esa fila). Cambiar esto al Camino A
   requeriría refactorizar AppServiceProvider, OLTsService y todos los consumers de
   `app('SmartOlt')` — equivale a un Bloque C.5 propio.

El Camino B (instancia por ISP) es además la elección que escala con menos riesgo legal
(LFPDPPP) y con menos deuda técnica acumulada.

**Cuándo reconsiderar el Camino A:** solo cuando GR-6 esté completo Y cuando el costo de
instancias supere el costo de ingeniería de multi-tenancy seguro. Ese punto probablemente
se alcanza alrededor de 10-15 ISPs activos.

---

## 4. Lo que ya está listo y lo que bloquea

### Lo que ya está listo para multi-ISP (Camino B)

| Artefacto | Estado | Nota |
|---|---|---|
| `olt_smartolt_config` (GR-5) | ✅ | Credenciales por-instalación, cifradas |
| `OltDriverManager` | ✅ | Resuelve driver por `olts.driver` |
| `OltDriverInterface` | ✅ | Contrato extensible (SmartOLT + Huawei) |
| `app('SmartOlt')` singleton | ✅ | Lee de `olt_smartolt_config` o .env |
| Scheduler crons OLT | ✅ | Sin dependencias cross-tenant |
| `OltAlertService` | ✅ | Flag `alertas_olt_activas` por-instalación |

### Lo que bloquea el Camino A (multi-tenant en un solo deploy)

| Bloqueador | Descripción |
|---|---|
| **GR-6 (modelos en `app/Models/`)** | Los 13+ modelos OLT están en `app/Models/`, no en el módulo. Aplicar un global scope de tenant requeriría mover todos los modelos al módulo y envolver su query builder. Sin GR-6, cualquier query sin `where('tenant_id', ...)` es un data-leak. |
| **Singleton `app('SmartOlt')`** | Asume una sola configuración SmartOLT. Multi-tenant requeriría un factory por tenant que resuelva el cliente Guzzle correcto en runtime. |
| **`olt_smartolt_config` de una sola fila** | Diseñada para `firstOrNew(['id'=>1])`. Necesitaría clave compuesta `(tenant_id, id)` y toda la lógica que la consume debe recibir el tenant. |
| **Sin columna `tenant_id` en 13 tablas** | Migración de esquema + backfill de datos existentes. Los datos actuales son de un ISP (Meganet propio), así que el backfill es `tenant_id=1` en todo, pero hay que hacerlo. |

---

## 5. Segundo driver: ZTE

### Lo que necesitaría un `ZteDriver`

```php
class ZteDriver implements OltDriverInterface
{
    // ── Autenticación ──────────────────────────────────────────────
    // ZTE usa CLI/Telnet o API REST dependiendo del modelo
    // C300/C600: REST API en algunos firmware; C220G-N: solo CLI.
    
    // ── Métodos mínimos del interface ─────────────────────────────
    public function getName(): string { return 'ZTE'; }
    public function getOlts(): array { ... }
    public function getOnusFromOlt(string $oltId): array { ... }
    public function getOnuSignal(string $sn): array { ... }
    public function enableDisableOnu(string $sn, bool $enable): array { ... }
    // ...resto de OltDriverInterface
}
```

El `OltDriverManager` ya está preparado para agregar ZTE:
```php
// Solo agregar una línea:
Olt::DRIVER_ZTE => $this->container->make(ZteDriver::class),
```

Y en `Olt::$drivers` agregar la constante `DRIVER_ZTE = 'zte'`.

### El problema real: no hay hardware ZTE para validar

Las 3 OLTs actuales en producción son todas Huawei (HUAWEI MA5608T, MEGANETWORKX7, MX7).
No hay ninguna ZTE en el inventario actual. Esto significa:

- Un `ZteDriver` implementado sin hardware real = código sin verificar.
- Los protocolos ZTE varían significativamente por modelo y versión de firmware.
- Mapear los comandos ZTE a `OltDriverInterface` sin una OLT de prueba produce bugs
  silenciosos que solo se detectan en producción del cliente ISP.

### Recomendación para ZTE

**No implementar ahora.** La estrategia correcta es:

1. **Solo el stub:** Agregar la constante `Olt::DRIVER_ZTE = 'zte'` y la entrada en
   `OltDriverManager` apuntando a un `NullZteDriver` que lanza `RuntimeException('ZTE driver no implementado')`. Esto reserva el espacio sin crear código muerto.

2. **Diferir la implementación** hasta tener:
   - Al menos una OLT ZTE (modelo específico) accesible para pruebas.
   - Acceso a la documentación CLI o API del modelo concreto.
   - Un ISP cliente que use ZTE y quiera comprometerse a validar.

3. **No asumir que ZTE REST = Huawei CLI.** Son protocolos distintos. El diseño de
   `OltDriverInterface` ya lo abstrae correctamente, pero la implementación concreta
   requiere acceso al hardware.

---

## 6. Plan por fases para Bloque C

> Estas fases son dependientes entre sí. La secuencia importa.

| Fase | Tarea | Depende de | Riesgo |
|---|---|---|---|
| **C0** | Definir modelo de negocio: ¿cuántos ISPs?, ¿precio por instancia? | Decisión Irving | Bajo |
| **C1** | CI/CD para deploy multi-instancia (Docker + script de bootstrap) | C0 | Bajo |
| **C1.5** | Panel meta-Meganet: dashboard que agrega estado de N instancias vía API | C1 | Medio |
| **C2** | GR-6: encapsular los 13+ modelos OLT en `app/Modules/Addons/GestionRed/Models/` | Ninguno (deuda técnica propia) | Medio |
| **C3** | Multi-tenant Camino A (si se opta por él): `tenant_id` en 13 tablas + global scopes | **GR-6 obligatorio** | Alto |
| **C4** | `olt_smartolt_config` multi-fila: factory por tenant en AppServiceProvider | GR-6 | Alto |
| **C5** | ZteDriver: stub + implementación real con hardware ZTE | OLT ZTE accesible | Bloqueado |

**Patrón recomendado:** audit → prototipo con datos reales de un ISP piloto → decisión.
No comprometer a Camino A o B sin haber desplegado al menos una instancia piloto (Camino B).

---

## 7. Decisiones que debe tomar Irving

1. **Camino A vs B:** ¿precio por ISP soporta instancias separadas, o necesitamos
   multi-tenant en un solo deploy para ser competitivos?
2. **GR-6:** ¿lo hacemos antes de Camino A? (Sin GR-6 el Camino A es peligroso.)
3. **ZTE:** ¿hay ISP cliente con ZTE que pueda prestar acceso a hardware para pruebas?
4. **Meta-dashboard:** ¿Meganet necesita ver el estado de todos los ISPs en un solo lugar?

---

---

## 8. Motor de escritura Huawei — Fuente de verdad, independencia y modo de operación

> Decisiones acordadas en sesión W2 (2026-06-16). Aplican al Bloque B a partir de B4-W1.

### 8.1 Fuente de verdad

La **OLT es la verdad física real**. El estado que vive en la OLT (ONTs provisionados, service-ports, VLANs, perfiles) es el estado autoritativo. SmartOLT es una capa de sincronización que *refleja* ese estado pero nunca lo origina. MegaISP posee los datos de negocio (cliente asociado, tarifa, historial, notas), la OLT posee los datos de red.

Consecuencia operativa: ante divergencia entre lo que SmartOLT reporta y lo que `display ont info` devuelve, el resultado del CLI de la OLT tiene precedencia. SmartOLT es una vista de lectura que puede quedar momentáneamente desincronizada — nunca un oráculo de writes.

### 8.2 Meta = independencia TOTAL (no parcial)

El objetivo a largo plazo es operar cada OLT Huawei **sin SmartOLT** como intermediario para ninguna operación CRUD. Esto incluye:
- Lectura de ONTs y señales (ya logrado en B2-B3).
- Provisión de nuevos ONTs (authorizeOnu — ciclo delete+add+service-port).
- Desprovisión (deauthorizeOnu).
- Cambio de perfil de velocidad (setOnuSpeedProfile — pendiente B5).
- Gestión de service-ports y VLANs (futuro).

El motor de escritura Huawei (`HuaweiDriver`) NO debe delegar a SmartOLT para ejecutar nada. Las API de SmartOLT pueden usarse como *lectura de referencia o validación*, pero la ejecución real viaja siempre por SSH/TELNET directamente a la OLT.

### 8.3 SmartOLT como maestro/validador (durante la transición)

Durante el período de coexistencia (antes de paridad demostrada), SmartOLT sirve como:

1. **Referencia de ground truth** para derivar los parámetros correctos de los comandos VRP. Los fixtures W0 (`docs/multiolt-w0-fixtures/`) capturan exactamente cómo SmartOLT provisiona el ONT de lab — mismos valores que el motor propio debe reproducir.
2. **Validador post-operación**: tras ejecutar una operación con el motor propio, se puede comparar el estado leído de la OLT contra lo que SmartOLT esperaría para detectar divergencias antes de que impacten al usuario final.
3. **Fallback de emergencia**: si el driver Huawei falla (sesión SSH caída, timeout), SmartOLT puede ejecutar la operación por su cuenta. Esta ruta de fallback solo se activa manualmente (nunca automática) y se registra en el log.

### 8.4 Secuencia de operaciones — diarias primero

El orden de implementación prioriza las operaciones de mayor frecuencia diaria:

| Prioridad | Operación | Frecuencia | Estado |
|---|---|---|---|
| 1 | Reboot ONT (diagnóstico) | Varias por día | B4-W1 dry-run ✅ |
| 2 | Activar / Desactivar ONT (corte/reconexión) | Diaria | B4-W1 dry-run ✅ |
| 3 | Autorizar ONT nuevo (alta cliente) | Semanal | B4-W1 dry-run ✅ |
| 4 | Desautorizar ONT (baja cliente) | Semanal | B4-W1 dry-run ✅ |
| 5 | Cambio de perfil de velocidad (reperfilado) | Semanal | Pendiente B5 |
| 6 | Gestión de service-ports y VLANs | Eventual | Futuro |

Las operaciones 1-4 están en dry-run desde W1. El primer write real (W3, supervisado) será el reboot, por ser el más seguro y reversible.

### 8.5 Check de modo y coexistencia

Cada OLT tiene un campo `motor_modo` (enum `'smartolt'|'propio'`, default `'smartolt'`) que indica qué motor ejecuta las operaciones write sobre esa OLT:

- **`smartolt`** (default): las operaciones write se delegan a la API SmartOLT. El motor Huawei propio puede leer pero no escribe.
- **`propio`**: las operaciones write van por `HuaweiDriver` directamente al CLI de la OLT. SmartOLT puede usarse para lectura de referencia.

**En W2 este campo es puramente informativo** (muestra un badge en la UI, no cambia ningún comportamiento). La transición funcional `smartolt → propio` ocurre en W3 y posteriores, OLT por OLT, solo después de validación con Irving presente.

Regla de coexistencia: nunca cambiar `motor_modo` de una OLT mientras haya operaciones en curso (provisiones, bajas). El cambio de modo es una operación administrativa que requiere ventana de mantenimiento y sync SmartOLT pausado.

---

## 9. Estado: RETOMA — paridad de escritura Huawei propia (2026-07-13)

> Decisión final de Irving, Hoja de Ruta item #415 (aprobado 2026-07-13 21:33, opción elegida:
> "Retomar la paridad de escritura del motor Huawei propio ahora"). Esta decisión **reemplaza**
> una aprobación previa del 2026-07-12 que había inclinado hacia congelar — esa rama nunca llegó
> a mergearse a `main`, así que este documento nunca tuvo un estado "congelado" vigente; el
> registro formal en esta sección 9 es directamente el de retomar. Reafirmada por Irving el
> 2026-07-14 (misma opción elegida) tras un ciclo del circuito donde la rama previa de este
> registro tampoco llegó a mergearse a `main`.

**Qué se retoma:** el desarrollo de las operaciones write pendientes del `HuaweiDriver`, con
**prioridad explícita en los comandos de aprovisionamiento (alta / baja / suspensión de ONU)**
siguiendo la secuencia ya definida en la sección 8.4 (que ya tiene reboot/activar-desactivar/
autorizar/desautorizar ONT en dry-run desde W1, y el write real cosmético de W3). Después de
aprovisionamiento sigue B5 (cambio de perfil de velocidad) y, más adelante, la gestión de
service-ports/VLANs.

**Condición explícita de Irving (no negociable):** cualquier comando write nuevo se prueba
primero contra una **OLT de laboratorio** (no productiva) antes de tocar equipos en campo. La
regla de coexistencia de la sección 8.5 sigue intacta y no se relaja por esta decisión: ninguna
OLT real cambia `motor_modo` a `propio` sin ventana de mantenimiento + presencia de Irving.
Ninguna OLT de producción tiene hoy `motor_modo=propio` (las 3 siguen en `smartolt`); retomar el
desarrollo no cambia por sí solo el comportamiento en vivo.

**Alcance de este item (#415):** este item cierra la **decisión** (retomar, con la condición de
lab-testing previo). La implementación real de los comandos de aprovisionamiento — acceso a la
OLT de laboratorio, desarrollo del ciclo alta/baja/suspensión y su validación — es trabajo de
ingeniería sustancial que corresponde a sesión(es) dedicada(s) del circuito o de desarrollo
directo, no a este ciclo de decisión. Se ejecuta como próximo(s) item(s) de Hoja de Ruta cuando
haya acceso confirmado a una OLT de laboratorio, respetando nivel de riesgo C (toca red en vivo).

---

*Documento creado por Claude Sonnet 4.6 como resultado de la sesión autónoma MultiOLT 2026-06-13.
Sección 8 agregada en sesión W2 (2026-06-16) — decisiones acordadas Irving + asistente.*
Sección 9 agregada por el circuito CC (item #415) — 2026-07-12: primer intento de registro
(congelar), rama nunca mergeada. 2026-07-13: registro final — decisión de Irving de retomar.*

---

## 10. Decisión de arquitectura SaaS multi-tenant OLT (2026-07-15)

> Decisión de Irving, Hoja de Ruta item #416 (aprobado 2026-07-15 09:04). Opción elegida:
> **"Híbrido — BD por tenant pero mismo proceso Laravel"** (ni Camino A puro ni Camino B puro
> de las secciones 2-3 de este documento).

**Qué se decidió:** en vez de agregar `tenant_id` a las 13 tablas OLT (Camino A) o desplegar
una instancia Laravel completa por ISP (Camino B), el modelo elegido es **multi-DB con switch
de conexión por request**: mismo codebase y mismo proceso Laravel, pero cada ISP tiene su
propia base de datos/esquema, resuelta en runtime (por subdominio o identificador de tenant)
antes de cualquier query.

**Por qué (trade-offs frente a A/B puros):**
- Aislamiento de datos entre ISPs tan fuerte como el Camino B (bases de datos separadas —
  no hay riesgo de `tenant_id` olvidado en un query, que era el riesgo central del Camino A).
- Un solo deploy/codebase como el Camino A — los fixes y features nuevas se escriben una vez;
  se corren en loop sobre N bases de datos en vez de N despliegues de servidor completos.
- Costo de arranque menor que el Camino B puro para los primeros 2-3 ISPs (no requiere
  orquestación de N servidores/contenedores desde el día uno).
- **No requiere GR-6 como pre-requisito bloqueante**: los modelos pueden seguir en
  `app/Models/` porque el aislamiento no depende de un global scope de Eloquent (que es lo
  frágil), sino de que la conexión de base de datos ya esté resuelta al tenant correcto antes
  de que el modelo ejecute cualquier query. GR-6 (encapsular los modelos OLT en el módulo)
  sigue siendo deuda técnica válida, pero deja de ser bloqueante para este camino.

**Alcance de este item (#416):** este item cierra la **decisión de arquitectura**, no la
implementación. Construir el mecanismo real (resolución de tenant por subdominio, switch de
conexión de base de datos antes de cada request, manejo de colas/jobs multi-tenant, comando de
migración en loop sobre N bases de datos, y el manejo especial de `olt_smartolt_config`/el
singleton `app('SmartOlt')` para que resuelva la configuración del tenant activo) es trabajo de
ingeniería sustancial y corresponde a item(s) de Hoja de Ruta dedicados, cuando exista al menos
un ISP piloto real para validar contra datos y tráfico reales — no a este ciclo de decisión. La
secuencia recomendada (auditoría → prototipo con un ISP piloto → implementación) de la sección 6
sigue vigente; solo cambia la casilla de "Camino A/B" por "Híbrido" en la fase **C3**.

*Sección 10 agregada por el circuito CC (item #416) — 2026-07-15: registro de la decisión de
Irving (Híbrido), sin código de implementación.*

---

## 11. Decisión: priorizar ZTE sobre V-SOL + vía de hardware piloto (2026-08-28)

> Decisión de Irving, Hoja de Ruta item #283 (aprobado 2026-08-28). Opciones elegidas:
> **(q1)** priorizar **ZTE** primero (driver + provisioning + telemetría), V-SOL después ·
> **(q2)** conseguir el hardware piloto **comprando/prestando una OLT chica del vendor
> priorizado** para lab de dev (aislado de producción) · **(q3)** alcance mínimo de la primera
> entrega = **lectura + provisioning básico** (alta/baja de ONU, asignación de perfil de
> servicio).

**Qué se decidió:** entre ZTE y V-SOL (ninguno tiene código ni hardware disponible hoy — ver
§5 y la auditoría `docs/AUDITORIA_OLT_MULTIMARCA_2026-07-15.md`), ZTE queda primero en la cola.
V-SOL se retoma después, cuando ZTE tenga su driver validado contra hardware real. El acceso a
hardware NO se resuelve usando una OLT de producción de Meganet (opción descartada por riesgo a
clientes reales) ni pidiendo prestado a un ISP externo (opción descartada por dependencia de
terceros): el camino elegido es que Meganet **adquiera o consiga en préstamo una OLT ZTE chica
dedicada a lab de dev**, aislada de producción.

**Qué se ejecutó en este item (código, sin hardware):** el "Camino recomendado" de §5 punto 1
— el stub trivial y aditivo que reserva el espacio sin crear código sin verificar:
- `Olt::DRIVER_ZTE = 'zte'` (constante nueva, `app/Models/Olt.php`).
- `App\Services\OltDriver\NullZteDriver` — implementa `OltDriverInterface` completo; cada
  método lanza `RuntimeException('ZTE driver no implementado')` (mismo patrón que el `NullSession`
  ya existente para Huawei sin credenciales).
- `OltDriverManager::driverFor()` resuelve `Olt::DRIVER_ZTE => NullZteDriver` (una OLT con
  `driver='zte'` en BD ya resuelve al stub en vez de lanzar `UnknownOltDriverException`).
- `UnknownOltDriverException` actualizado para listar `'zte'` entre los valores válidos.

**Qué queda pendiente y por qué NO se hizo aquí:**
- **Adquirir/conseguir la OLT ZTE de lab (q2):** es una acción de Irving en el mundo físico
  (comprar o gestionar un préstamo), no ejecutable desde este circuito de código.
- **`ZteDriver` real** (lectura + provisioning básico, per q3): bloqueado hasta que exista esa
  OLT confirmada — implementarlo antes sería exactamente el riesgo que este mismo item señala
  ("código sin verificar" / "bugs silenciosos en producción de un ISP cliente").
- **Auditoría/diseño de V-SOL:** deprioritizada por la propia decisión de Irving (ZTE primero);
  registrada como sub-item de seguimiento para retomarla en su momento, no en esta vuelta.

*Sección 11 agregada por el circuito CC (item #283) — 2026-08-28: registro de la decisión de
Irving (ZTE primero, hardware vía compra/préstamo de lab) + stub ZTE aditivo.*
*Basado en auditoría directa del codebase — no estimaciones.*

---

## 12. Tercer driver: V-SOL (auditoría/diseño — retomar tras ZTE)

> Item roadmap #670, sub-item de seguimiento de #283 (Fase G de `AUDITORIA_OLT_MULTIMARCA_2026-07-15.md`
> §4). Ejercicio análogo al de §5 para ZTE, pero **sin crear código** — ver §12.4 sobre por qué.

### 12.1 Punto de partida: sigue en cero, sin cambios desde julio

Reconfirmado en esta vuelta (`grep -ril "vsol\|v-sol" app/ resources/ config/` → cero resultados,
igual que documentó `AUDITORIA_OLT_MULTIMARCA_2026-07-15.md` §1.3): no hay constante
`Olt::DRIVER_VSOL`, ni driver, ni stub, ni fixtures, ni documento de protocolo previo a este. Sigue
siendo la marca menos explorada de las tres del alcance original del item #57 ("Huawei + ZTE +
V-SOL") — más atrás que ZTE, que al menos ya tiene su stub (`NullZteDriver`, §11).

### 12.2 Lo que necesitaría un `VsolDriver`

```php
class VsolDriver implements OltDriverInterface
{
    // ── Autenticación ──────────────────────────────────────────────
    // V-SOL es un ODM chino (Shenzhen V-SOL) cuyas OLTs GPON/EPON se
    // configuran tradicionalmente por CLI vía Telnet/SSH, con una sintaxis
    // de comandos propia (no VRP de Huawei, no compatible con ella pese a
    // parecidos superficiales de OLTs "genéricas" chinas). Modelos de la
    // línea V16xx/V27xx exponen distinto set de comandos según firmware;
    // algunos modelos más nuevos añaden SNMP (MIB propietaria) para
    // monitoreo y, en ciertas líneas, un panel Web/HTTP — pero no hay una
    // API REST pública y documentada equivalente a SmartOLT.

    // ── Métodos mínimos del interface (OltDriverInterface, 15 métodos) ──
    public function getName(): string { return 'V-SOL'; }
    public function listOlts(): array { ... }
    public function listSpeedProfiles(): array { ... }
    public function getUnconfiguredOnus(?string $oltId = null): array { ... }
    public function getOnusByOlt(string $oltId): array { ... }
    public function getOnusSignals(?string $oltId = null): array { ... }
    public function getOnusStatus(?string $oltId = null): array { ... }
    public function findOnuBySn(string $sn): array { ... }
    public function getOnuDetails(string $onuId): array { ... }
    public function getOnuSignal(string $onuId): array { ... }
    public function getOnuStatus(string $onuId): array { ... }
    public function authorizeOnu(array $data): array { ... }
    public function deauthorizeOnu(string $onuId): array { ... }
    public function setOnuEnabled(string $onuId, bool $enabled): array { ... }
    public function rebootOnu(string $onuId): array { ... }
    public function setOnuSpeedProfile(string $onuId, array $data): array { ... }
    // ...mismo contrato que HuaweiDriver/NullZteDriver, sin métodos extra
    // conocidos (a reserva de lo que exija el modelo real una vez elegido).
}
```

El `OltDriverManager` ya está preparado para agregar V-SOL con el mismo patrón usado para ZTE
(§11): una constante `Olt::DRIVER_VSOL = 'vsol'` + un caso más en el `match()` de
`OltDriverManager::driverFor()` apuntando a un `NullVsolDriver` (o directo al `VsolDriver` real,
cuando exista). El contrato (`OltDriverInterface` + capacidades `Supports*` opcionales) no necesita
ningún cambio — ya está diseñado para ser agnóstico de marca (ver
`AUDITORIA_OLT_MULTIMARCA_2026-07-15.md` §2.1).

### 12.3 El problema real: cero hardware, y protocolo más fragmentado que ZTE

- **Cero OLTs V-SOL en el inventario** — las 3 OLTs reales de producción son Huawei; tampoco hay
  ninguna ZTE (§11) ni V-SOL.
- **Protocolo más fragmentado que ZTE.** ZTE al menos tiene dos familias de firmware conocidas
  (C300/C600 con algo de REST en firmware reciente, C220G-N solo CLI) documentadas en §5. V-SOL,
  al ser un ODM cuyo hardware se revende bajo múltiples marcas (rebranding común en el mercado de
  ISPs pequeños/medianos), tiene aún más variación de comando/firmware por modelo — sin acceso a
  la documentación CLI del modelo concreto que se vaya a usar, cualquier mapeo a
  `OltDriverInterface` sería pura especulación.
- **Sin siquiera un modelo candidato.** A diferencia de ZTE, donde el item #283 (Irving) ya fijó
  una vía concreta de adquisición de hardware de lab (compra/préstamo, decisión q2 de ese item),
  para V-SOL no hay todavía ni modelo elegido, ni ISP piloto identificado, ni compromiso de
  adquisición — el propio item #283 lo pospuso explícitamente ("V-SOL después", sin fecha).

### 12.4 Recomendación para V-SOL — y por qué esta vuelta NO crea el stub

**No implementar ahora ningún código** (ni el driver real ni siquiera el stub trivial
`NullVsolDriver`). Diferencia clave con lo que se hizo para ZTE en §11: ahí el stub se creó como
parte de una decisión de negocio ya tomada por Irving (item #283: ZTE primero, con compromiso de
conseguir hardware de lab). Para V-SOL **esa decisión de negocio todavía no existe** — el mismo
item #283 solo la difirió ("después"), sin ISP piloto ni vía de hardware confirmada. Adelantar el
enum/constante `Olt::DRIVER_VSOL` sin ese compromiso sería anticipar una decisión de priorización
que es de Irving, no del circuito (mismo criterio que ya aplicó
`AUDITORIA_OLT_MULTIMARCA_2026-07-15.md` §4, fila E: el stub de una marca nueva está condicionado
a que la fase de decisión de negocio la confirme primero).

La secuencia para retomar V-SOL, en orden:

1. **Decisión de negocio (pendiente, es de Irving):** ¿qué ISP piloto (interno o cliente) tiene o
   puede conseguir hardware V-SOL real, y de qué modelo concreto? Sin esto, el resto es teórico
   — mismo argumento que ya se aplicó a ZTE en §5 y en el item #283.
2. **Con modelo confirmado:** conseguir la documentación CLI/SNMP/API de ESE modelo específico
   (no genérica "V-SOL" — el protocolo varía demasiado entre modelos como para generalizar).
3. **Solo entonces, el stub trivial:** `Olt::DRIVER_VSOL = 'vsol'` + `NullVsolDriver` (lanza
   `RuntimeException('V-SOL driver no implementado')`) + entrada en `OltDriverManager` — mismo
   patrón mecánico ya usado para ZTE, trivial de ejecutar una vez que el paso 1 lo autorice.
4. **Implementación real** — solo contra hardware de laboratorio confirmado, nunca directo a
   producción de un ISP cliente (mismo riesgo de "bugs silenciosos" ya documentado para ZTE en §5
   y para Huawei en la condición #415).

*Sección 12 agregada por el circuito CC (item #670, worker wt-4) — 2026-08-28: auditoría/diseño
V-SOL análoga a §5, sin crear código (decisión de negocio de hardware/piloto V-SOL sigue
pendiente de Irving, a diferencia de ZTE).*
*Basado en auditoría directa del codebase (grep + lectura de `OltDriverInterface`/
`OltDriverManager`/`NullZteDriver`) — no estimaciones.*
