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

*Documento creado por Claude Sonnet 4.6 como resultado de la sesión autónoma MultiOLT 2026-06-13.
Basado en auditoría directa del codebase — no estimaciones.*
