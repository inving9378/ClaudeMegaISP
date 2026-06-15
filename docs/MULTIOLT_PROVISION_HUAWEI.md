# MultiOLT — Provisión Huawei MA5800-X7 (MEGANETWORKX7)

> **OLT de referencia:** `10.50.11.2` · VRP V100R018C00 SPH505 · OLT id=3 en BD  
> **Método:** telnet TCP/23 · usuario `smartoltusr` · vía `HuaweiTransport`  
> **Capturado:** 2026-06-15 (W0) · READ-ONLY contra la OLT real  
> **Regla de oro:** este documento es la **verdad de campo**. No inventar sintaxis.

---

## 1. Perfiles disponibles en la OLT

### Line Profiles (`display ont-lineprofile gpon all`)

| Profile-ID | Nombre | Usos activos |
|---|---|---|
| **0** | line-profile_default_0 | 0 |
| **1** | **SMARTOLT_FLEXIBLE_GPON** | **2206** ← el que usamos |
| 3 | Generic_2_V200 | 1 |
| 4 | Generic_1_V200 | 0 |
| 5 | Generic_3_V200 | 0 |
| 6–13 | Generic_*_V101/V200 | 0 |
| 8193–8195 | line-profile-extend-frame* | 0 |

**Perfil activo:** ID=1 (`SMARTOLT_FLEXIBLE_GPON`), estructura T-CONT/DBA (del `display ont info 2 0` del ONT 0/3/2:0):

```
T-CONT 0  → DBA Profile-ID: 2   (type 1, 1024 kbps fijo — management/OAM)
T-CONT 1  → DBA Profile-ID: 10  (type 4, 1048000 kbps max — datos)
  GEM Index 1 : ETH, mapping prioridad 0 y 7
T-CONT 2  → DBA Profile-ID: 10
  GEM Index 2 : ETH, mapping prioridad 2
T-CONT 3  → DBA Profile-ID: 10
  GEM Index 3 : ETH, mapping prioridad 5
```

> El `gemport 1` (GEM Index 1, T-CONT 1) es el que se usa para el service-port de datos.

### Service Profiles (`display ont-srvprofile gpon all`) — selección con usos > 0

| Profile-ID | Nombre | Usos |
|---|---|---|
| **1** | HG8245Q2 | 231 |
| **2** | **HG8145V5** | **711** ← el más usado |
| **4** | HG8245H | 41 |
| **5** | HG8245H5 | 54 |
| **8** | HG8546M | 150 |
| **9** | FG6123BTM | 248 |
| **26** | MT-41A5NET | 727 |
| **27** | MT-45V5NET | 17 |
| **39** | ET8145V5CUS | 24 |

El service profile controla los **puertos físicos del ONT** (ETH, POTS, CATV). Para el test ONT usar el perfil que corresponda al modelo físico.

### DBA Profiles

| ID | Tipo | Fix (kbps) | Assure | Max | Usos |
|---|---|---|---|---|---|
| 2 | 1 (Fixed) | 1024 | 0 | 0 | 12 |
| 10 | 4 (Best-effort) | 0 | 0 | 1048000 | 11 |
| 513 | 4 | 0 | 0 | 1024000 | 1 |
| 514 | 4 | 0 | 0 | 2239488 | 1 |
| 515 | 4 | 0 | 0 | 8957952 | 1 |

### VLANs de servicio activas

| VLAN | SERV-ports | Zona probable |
|---|---|---|
| 103 | 556 | Zona A |
| 104 | 1006 | Zona B |
| **105** | **315** | **Zona D1 (donde está el ONT de prueba)** |
| 106 | 186 | Zona C |
| 107 | 132 | Zona E |
| 200 | 10 | Especial/Mgmt? |
| 300 | 2188 | Zona mayor |

---

## 2. ONT de referencia (ya provisionado y en línea)

**F/S/P:** 0/3/2 · **ont-id:** 0 · **SN:** HWTCFEFCC9A2  
Capturado con `display ont info 2 0` (interface gpon 0/3) + `display service-port port 0/3/2 ont 0` (config-view).

```
Control flag  : active
Run state     : online
Config state  : normal
Authentic type: SN-auth
Line profile  : ID=1 (SMARTOLT_FLEXIBLE_GPON)
Service profile: ID=2 (HG8145V5)
Description   : PRUEBA_zone_D1Z10_authd_20260610
```

```
Service port INDEX : 1012
VLAN (uplink)      : 105
PORT TYPE          : gpon
F/S/P              : 0/3/2
VPI (ONT-ID)       : 0
VCI (GEM idx)      : 1
FLOW TYPE          : vlan
FLOW PARA (user-vlan): 200
STATE              : up
```

---

## 3. Secuencia exacta de provisión (DERIVADA del ONT de referencia)

> ⚠️ **DRY-RUN FIRST.** Esta secuencia NO se envía hasta la Fase W3.  
> ⚠️ El `save` es el punto de no retorno — paso separado y último.

### Paso 1 — Entrar a interface gpon {frame}/{slot}

```
enable
config
interface gpon {frame}/{slot}
```

Ejemplo para F/S/P 0/3/X (slot 3): `interface gpon 0/3`

**VRP quirk:** nuestro driver envía `interface gpon {frame}/{slot}` via `enterGponInterface(frame, slot)` — funciona confirmado. NO usar la sintaxis completa `0/3/2` aquí, solo `frame/slot`.

### Paso 2 — Alta del ONT por SN (`ont add`)

```
ont add {port} {ont-id} sn-auth {SN} omci ont-lineprofile-id {line-id} ont-srvprofile-id {srv-id} desc {descripcion}
```

**Parámetros para el ONT de prueba (a definir con Irving en el punto 5):**
```
ont add {PORT} {ONT_ID} sn-auth {SN_PRUEBA} omci ont-lineprofile-id 1 ont-srvprofile-id {SRV_PERFIL} desc TEST_MULTIOLT_W3_20260615
```

Donde:
- `{PORT}` = puerto dentro del slot (0-7 en H902GPHF, 0-15 en H901GPHF)
- `{ONT_ID}` = id libre en ese puerto (0-127; el primero libre suele ser el siguiente al máximo registrado)
- `{SN_PRUEBA}` = SN del ONT físico de laboratorio (en formato decoded 12-char, ej. HWTCXXXXXXXX)
- `{SRV_PERFIL}` = ID del service profile que coincide con el modelo del ONT de prueba

**Rollback (paso 2):**
```
ont delete {port} {ont-id}
```

### Paso 3 — Volver a config-view

```
quit
```

(Desde interface gpon → config view. `return` iría a user-view.)

### Paso 4 — Agregar service-port

```
service-port vlan {VLAN} gpon {frame}/{slot}/{port} ont {ont-id} gemport 1 multi-service user-vlan {USER_VLAN}
```

Derivado del ONT de referencia (VLAN 105, user-vlan 200, gemport 1):
```
service-port vlan {VLAN_ZONA} gpon 0/{slot}/{port} ont {ont-id} gemport 1 multi-service user-vlan {USER_VLAN}
```

> **Aclaración FLOW PARA=200 / user-vlan 200:** el CPE envía tráfico tagueado con VLAN 200; la OLT hace translate→VLAN uplink (105/103/300/etc). Si el CPE envía sin tag, usar `user-vlan untagged` (y el FLOW TYPE sería `untag` en el display).

**Rollback (paso 4):**
```
undo service-port {index}
```
donde `{index}` es el INDEX asignado por la OLT (se lee con `display service-port port {f}/{s}/{p} ont {id}` ANTES del delete).

### Paso 5 — Verificar antes de `save`

```
display ont info {port} {ont-id}         ← desde interface gpon context
display service-port port {f}/{s}/{p} ont {id}  ← desde config-view
```

Confirmar: `Control flag: active`, `Run state: online` (puede tardar 30-120s), service-port `STATE: up`.

### Paso 6 — `save` (PUNTO DE NO RETORNO — paso separado, solo con confirmación explícita)

```
return
save
```

Respuesta esperada: `The current configuration will be written to the device. Are you sure? (y/n)[n]:y`

> La VRP pide confirmación. Nuestro `TelnetSession.readUntil()` ya maneja `{ <cr>||<K> }` automáticamente. El prompt de confirmación de `save` usa la forma `(y/n)` — necesitamos verificar que nuestro auto-responder también cubra esta variante. Si no, hay que enviar `y\r\n` explícitamente.

**NO HAY rollback de `save`:** una vez guardado, la config sobrevive a reinicios. Por eso se separa.

---

## 4. Secuencia completa (vista limpia, para referencia)

```bash
# Contexto inicial: user-view (MA5800-X7>)
enable                                    # → enable-view (MA5800-X7#)
config                                    # → config-view (MA5800-X7(config)#)

# ── Alta ONT ─────────────────────────────────────────────────────
interface gpon {frame}/{slot}             # → interface-view
ont add {port} {ont-id} sn-auth {SN} omci ont-lineprofile-id 1 ont-srvprofile-id {srv-id} desc {desc}
quit                                      # → config-view

# ── Service port ─────────────────────────────────────────────────
service-port vlan {VLAN} gpon {f}/{s}/{p} ont {ont-id} gemport 1 multi-service user-vlan {USER_VLAN}

# ── Verificar ────────────────────────────────────────────────────
interface gpon {frame}/{slot}
display ont info {port} {ont-id}
quit
display service-port port {f}/{s}/{p} ont {ont-id}

# ── Save (solo tras verificación y confirmación de Irving) ───────
return                                    # → user-view
save                                      # → confirmar con y
```

---

## 5. Secuencia de rollback completa

```bash
enable
config

# 1. Leer el service-port index PRIMERO (no borrarlo a ciegas)
display service-port port {f}/{s}/{p} ont {ont-id}
# → apuntar el INDEX del renglón

# 2. Borrar service-port
undo service-port {INDEX}

# 3. Borrar ONT
interface gpon {frame}/{slot}
ont delete {port} {ont-id}
quit

# 4. Verificar que no quedan rastros
display ont info {port} {ont-id}
# → debe decir "% The ont does not exist" o similar

# 5. Save (necesario para que el rollback sea persistente)
return
save
```

---

## 6. ⚠️ Riesgos y precauciones VRP documentados

### Space-eater bug
VRP V100R018 consume el espacio antes de ciertos parámetros en user-view:
- Afectado: `display ont info {F/S/P} all` en user-view → `{F/S/P}all`
- **No afectado:** comandos en config/interface-view, `display service-port port {f}/{s}/{p} ont {id}`, ni `ont add`

Para los comandos de escritura (`ont add`, `service-port`) enviados vía `writeSlow()` (20ms/byte desde interface/config-view), el bug NO aplica según los tests de B1c-B3d.

### Comandos de detalle con sintaxis incompatible
Los siguientes comandos fallan con `% Unknown command` en esta OLT y NO deben usarse:
- `display ont-lineprofile gpon {id}` → solo funciona `display ont-lineprofile gpon all`
- `display ont-srvprofile gpon {id}` → idem
- `display current-configuration interface gpon 0/3` (en user-view) → syntax error
- `display ont configuration {port} {ont-id}` (en interface-view) → unknown command

### `save` y confirmación
El comando `save` emite `(y/n)[n]:` — distinto de `{ <cr>||<K> }` que el TelnetSession ya maneja. En W1 habrá que añadir el auto-respond para `(y/n)` O separar el `save` como paso manual fuera del pipe automático.

### Reconexión y flapping de ruta
La ruta hacia 10.50.11.x ha flapeado antes (Destination Host Unreachable transitorio desde 192.168.104.1). El driver ya tiene back-off en `open()`, pero si la ruta cae durante una escritura, el estado del ONT en la OLT podría quedar a medio provisionar. El `abort-on-error` de W1 es crítico por esto.

---

## 7. Pre-checks antes de `ont add` (a implementar en W2)

1. **ONT no está ya provisionado:** `display ont info by-sn {SN}` no debe devolver resultado
2. **Puerto/slot existe:** `display board 0` muestra el slot como activo
3. **ONT en autofind:** `display ont autofind all` devuelve el SN (optativo — puede provisionarse sin estar en autofind si conocemos el SN)
4. **Profiles existen:** IDs 1 y {srv-id} aparecen en `display ont-lineprofile gpon all` y `display ont-srvprofile gpon all`
5. **VLAN existe:** aparece en `display vlan all`
6. **ont-id libre:** `display ont info {port} all` no incluye el ont-id elegido para ese puerto

---

## 8. ONU PRUEBA — a definir con Irving ⏳

> **PARADA.** Los siguientes datos deben ser confirmados por Irving antes de codificar W1.

| Campo | Valor | Estado |
|---|---|---|
| SN del ONT físico de lab | `???` | **Pendiente Irving** |
| F/S/P (frame/slot/port) | `0/?/?` | **Pendiente Irving** |
| ont-id a asignar | `???` | **Pendiente Irving** |
| Service profile (modelo ONT) | ver tabla perfiles arriba | **Pendiente Irving** |
| VLAN de servicio (uplink) | `???` (105? 300? dedicado?) | **Pendiente Irving** |
| User-VLAN (CPE → OLT) | `???` (200? untagged?) | **Pendiente Irving** |

**Recomendación:** usar una VLAN dedicada para pruebas (ej. VLAN 200 ya existe con solo 10 SERV-ports) para no interferir con clientes reales.

---

## 9. Auditoría de métodos — estado actual del driver

### Métodos de escritura en `OltDriverInterface` (ya declarados)

| Método | Estado en HuaweiDriver | Notas W1 |
|---|---|---|
| `authorizeOnu(array $data)` | `throws WriteNotEnabledException` | implementar: ont add + service-port |
| `deauthorizeOnu(string $onuId)` | `throws WriteNotEnabledException` | implementar: undo service-port + ont delete |
| `setOnuEnabled(string $onuId, bool $enabled)` | `throws WriteNotEnabledException` | ont deactivate/activate |
| `rebootOnu(string $onuId)` | `throws WriteNotEnabledException` | ont reset |
| `setOnuSpeedProfile(string $onuId, array $data)` | `throws WriteNotEnabledException` | cambio line-profile (complejo; postergar a W4+) |

### Métodos adicionales necesarios (NO en interfaz aún)

| Método propuesto | Fase | Propósito |
|---|---|---|
| `buildProvisionPreview(array $data): array` | **W1** | dry-run: arma y devuelve strings de comandos SIN enviarlos |
| `saveOltConfig(): array` | **W3** | guarda a flash (separado, solo tras confirmación) |
| `getServicePortsForOnt(string $onuId): array` | **W2** | pre-check y para construir rollback |

### Cambios requeridos en `ReadOnlyGuard`

El `assertWriteTargetAllowed(string $sn): never` actual siempre lanza. Para W1:
- Añadir flag `$writeEnabled = false` + `$writeToken = null` + `$allowedSns = [SN_PRUEBA]`
- `enableWrite(string $token): void` — habilita solo si token coincide con una constante de entorno
- `assertWriteTargetAllowed(string $sn): void` — pasa solo si `writeEnabled AND sn ∈ allowedSns`
- Añadir ALLOWED-list separada para **comandos de escritura** (ont add, service-port, undo, ont delete, save)
- Todo cambio de escritura se loguea en nivel `notice` con sn + usuario + timestamp

---

*Documento generado en Fase W0 · 2026-06-15 · READ-ONLY · Siguiente: confirmación ONU PRUEBA por Irving → Fase W1*
