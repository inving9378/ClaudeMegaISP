# Runbook — Provisión de VoIP (Asterisk) al desplegar una versión

VoIP Fase 3b. Documenta qué hace la actualización de MegaISP cuando llega a un servidor
**sin Asterisk instalado** (alta de un cliente nuevo, o reinstalación de cero), y cómo
verificarlo. Ubicación decidida por Irving (pregunta q5 del item padre #9990825):
`docs/voip/`.

**Fuente de este runbook:** los pasos, el orden y los 16 criterios de aceptación de abajo
son los REALES, verificados en dev el 2026-09-10 (item #9990718 — 12 defectos encontrados
y corregidos hasta que la provisión pasó dos veces seguidas + una llamada real con audio
en ambos sentidos). Detalle completo de cada defecto en
`docs/bitacora/2026-09-10-item-9990718.md`; este documento no repite esa investigación,
la resume como procedimiento operativo.

**Alcance de este runbook:** cómo se provisiona Asterisk en un servidor de MegaISP
(dev o, en el futuro, uno de producción). **No** incluye pushear el tag ni publicar la
GitHub Release de esta versión — eso es un paso manual de Irving, fuera del circuito
(preguntas q1-q3 del item padre #9990825). Este documento es lo que Irving usa DESPUÉS,
al aplicar la versión en un servidor real; el circuito nunca toca producción.

---

## 1. Qué hace la actualización

Al llegar a un servidor sin Asterisk, el módulo VoIP de MegaISP no asume que alguien lo
instaló a mano: la propia actualización trae las instrucciones (`config/requisitos-voip.php`,
el manifiesto) y las ejecuta con el comando `voip:provisionar`. El principio es
**la actualización lleva las instrucciones y un sitio de descarga lleva los archivos**:
el manifiesto dice qué versión de Asterisk, de dónde bajarla y con qué hash; el
provisionador descarga, compila, instala, configura, siembra el plan de numeración y
arranca la central — sin intervención manual.

Si Asterisk ya está instalado y provisionado, correr el comando de nuevo es **idempotente**:
detecta lo que ya está hecho (por versión de cada paso, no por adivinar del texto de salida)
y solo reintenta lo que falta o falló.

---

## 2. Pre-checks (antes de correr nada)

1. **Espacio en disco** — el provisionador lo comprueba solo y ABORTA si falta
   (`espacio_minimo_mb` en el manifiesto, hoy 3072 MB), pero conviene confirmarlo antes de
   arrancar una compilación de ~20-90 minutos.
2. **Conectividad de salida** al host de artefactos (`MEGAISP_ARTEFACTOS_URL`, por defecto
   `https://megaisp.com.mx/artefactos/asterisk`, fuera de la infraestructura propia a
   propósito: una caída de Proxmox no debe impedir provisionar).
3. **Privilegios de `sudo -n`** para los comandos que corren como root sin pedir contraseña:
   compilar/instalar (`provisionar-asterisk.sh`), `systemctl` de la unit `asterisk`, y
   `asterisk -rx` (CLI de control). El sudoers de dev ya los autoriza; en un servidor nuevo
   hay que replicarlo.
   - ⚠️ **Nota operativa:** el wildcard de sudoers para `voip:provisionar` exige **al menos
     una bandera** — `voip:provisionar` a secas pide contraseña interactiva. Para correrlo
     por sudo a mano, pasar alguna bandera (`--no-interaction` sirve). No afecta al camino
     real del deploy (ya corre con privilegios, sin pasar por `sudo`), solo a quien lo
     pruebe manualmente.
4. **`esquema_realtime` en el manifiesto** (`config/requisitos-voip.php`) — debe traer ya
   fijada la revisión de Alembic (`2285f2ace275` a la fecha de este runbook). Con el campo
   vacío y sin `--descubrimiento`, el provisionador ABORTA antes de tocar la base — a
   propósito, para no aceptar en silencio un esquema distinto en la instalación de un
   cliente real. `--descubrimiento` es solo para descubrir una revisión nueva tras subir de
   versión de Asterisk, y con `esquema_realtime` ya fijado esa bandera **aborta en el paso
   uno** (evita re-descubrir sin querer y perder la validación).
5. **Warm-up de MegaISP ya aplicado** (ver §5) — el manifiesto y la config de VoIP se leen
   con `config()`, así que si el `.env` cambió, correr `config:clear` antes de provisionar.

---

## 3. Pasos, en el orden REAL (15 pasos — el orden es el contrato)

El orden documentado aquí es el que corrige los 12 defectos de la bitácora — **no es el
orden ingenuo** (que falló 12 veces). Referencia de código:
`App\Modules\Addons\VoIP\Services\ProvisionadorAsterisk::PASOS`.

| # | Paso | Qué hace |
|---|------|----------|
| 1 | `verificar` | Contrato del manifiesto + estado del servidor. Si falta un valor se sabe aquí — diferencia entre un fallo de un segundo y uno de media hora |
| 2 | `descargar` | Baja el tarball de Asterisk desde `MEGAISP_ARTEFACTOS_URL` |
| 3 | `verificar_hash` | **sha256 con `curl -fsS`, ANTES de instalar.** Se compara contra el hash del manifiesto (viaja por git), nunca contra un `.sha256` bajado del mismo sitio que el tarball — dos canales independientes, o la verificación no protege de nada |
| 4 | `dependencias` | Pre-flight de la cadena de Alembic (comando `alembic` en PATH o `python3 -m alembic`, driver `pymysql` importable, conexión a la base abre de verdad) — corre ANTES de compilar, cuando abortar es barato, no después de media hora de `make` |
| 5 | `compilar` (fase_compilar) | **menuselect ESTRICTO por categoría real del archivo `menuselect.makeopts`, no por `MENUSELECT_MODULES=`.** `--enable X` de menuselect devuelve 0 aunque X no se pueda construir realmente — hay que leer las categorías reales, o una dependencia ausente (p. ej. `unixodbc-dev`) da media hora de compilación "exitosa" sin `res_odbc` |
| 6 | `instalar` | Instala binarios y paquetes de sonidos (`core-es-alaw`, `core-en-alaw`, `moh-opsound-alaw`) |
| 7 | `preservar_alembic` | Copia `contrib/ast-db-manage` (vive DENTRO del tarball) a `/usr/share/megaisp-asterisk/alembic` antes de que se borren las fuentes — es lo único que genera el esquema de las tablas `ps_*` |
| 8 | `crear_base` | Base de datos realtime. **ANTES de `generar_config`**, porque esa fase escribe el DSN de unixODBC y lo comprueba con `isql` abriendo la conexión de verdad — no se puede comprobar contra una base que aún no existe |
| 9 | `generar_config` | `asterisk.conf`, DSN de unixODBC, unit de systemd (con `RuntimeDirectory=asterisk` — sin eso, systemd dice `active (running)` y no hay socket de control) y `alembic.ini` |
| 10 | `migrar_esquema` | `alembic upgrade head` hasta la revisión `esquema_realtime` del manifiesto. La tabla de revisión real es **`alembic_version_config`**, no `alembic_version` a secas — Asterisk publica CUATRO árboles que comparten base y cada uno tiene su propia tabla `alembic_version_<árbol>` |
| 11 | `credenciales` | Genera y persiste AMI (`AMI_SECRET`) y ARI (`ASTERISK_ARI_PASS`) — al `.env` (vía `EscritorEnv`, atómico) y a la config de MegaISP. **ANTES de `config`**, nunca al revés: `manager.conf`/`ari.conf` llevan el secreto DENTRO, y renderizarlos antes de generarlo los deja con el hueco vacío |
| 12 | `config` | Renderiza la configuración de MegaISP desde las plantillas (`GeneradorConfigAsterisk`) hacia `generados_dir` (por defecto `/etc/asterisk/megaisp.d`, **fuera** del árbol web a propósito — Asterisk corre como root y no debe leer de un directorio escribible por `www-data`). Respeta archivos modificados a mano (huella tipo conffile) y los propone al lado como `.nuevo` en vez de pisarlos |
| 13 | `siembra` | Plan de numeración (9 rangos, 8 perfiles) y extensiones de arranque (30). Publica a **ambos lados**: `voip_extensiones` (MegaISP) Y a las tablas realtime `ps_aors`/`ps_auths`/`ps_endpoints`/`ps_endpoint_id_ips`/`ps_registrations` (Asterisk) — sembrar solo en MegaISP deja extensiones que la pantalla muestra y el teléfono no puede registrar |
| 14 | `arrancar` | `systemctl enable` + `restart asterisk`, y espera a que la CLI **responda** (`asterisk -rx "core show version"`), no un número fijo de segundos — el primer arranque de una instalación nueva hace más trabajo y `systemctl is-active` no distingue "vive" de "tiene socket de control" |
| 15 | `validar` | Validación final contra la central real (ver criterios en §4) |

**Cuánto tarda:** en la VM de dev, la compilación real tomó 5-10 minutos. El rango
esperado según hardware/red es de **5 a 90 minutos** — no hay un número fijo confiable
(descarga + compilación son las fases más variables).

**Reintentar tras un fallo** (retoma la misma corrida, sin repetir lo ya hecho):
```
php artisan voip:provisionar
```
**Empezar de cero** en vez de retomar:
```
php artisan voip:provisionar --nueva
```
**Ver el estado de una corrida por UUID** (sin provisionar):
```
php artisan voip:provisionar --estado=<uuid>
```

---

## 4. Qué se verifica al final — 16 criterios de aceptación

Resultado real verificado en dev el 2026-09-10 (detalle completo en la bitácora citada):

| # | Criterio | Cómo se comprueba |
|---|----------|--------------------|
| 1 | Desinstalado por completo, la actualización deja Asterisk instalado, corriendo y con base realtime | `provisioning/desinstalar-asterisk.sh` seguido de `voip:provisionar` |
| 2 | Cero intervención manual en el servidor | Todo por `sudo` acotado al comando y a los scripts del módulo |
| 3 | sha256 verificado antes de instalar | Paso `verificar_hash`, `curl -fsS` |
| 4 | Credenciales AMI/ARI generadas solas, en `manager.conf`, `ari.conf` y `.env`, con registro de origen | Paso `credenciales` antes de `config` (ver §3, paso 11) |
| 5 | El módulo lee estado real | `pjsip show endpoints` contra la central, no una lista escrita a mano |
| 6 | 9 rangos y 8 perfiles creados, seeder idempotente | `PlanNumeracionSeeder`, segunda corrida sin duplicar |
| 7 | 30 extensiones activas, provisionadas, contraseña distinta cada una | `ExtensionesArranqueSeeder` + `AsteriskProvisioningService` |
| 8 | Una extensión en 1200 hereda `tecnico_campo` | Verificado en el perfil sembrado |
| 9 | Alta en 1900–1999 rechazada con mensaje claro | Rango reservado, validación explícita |
| 10 | Segunda corrida del seeder no duplica ni pisa | `0 escritos · N sin cambio` en la segunda corrida |
| 11 | **Llamada entre dos extensiones sembradas con audio en ambos sentidos** | `provisioning/verificar-llamada.sh` (ver §6) |
| 12 | Segunda corrida del provisionador no rompe nada | `V2_EXIT=0`, pasos caros omitidos (no recompila) |
| 13 | No quedan fuentes | Las borra `preservar_alembic`. ⚠️ El compilador (toolchain) sí queda instalado — inherente a compilar en destino |
| 14 | Ningún archivo existente sobrescrito en silencio | Huella tipo conffile; propone `.nuevo` en vez de pisar |
| 15 | URL de descarga en configuración | `config('requisitos-voip.asterisk.origen')`, sobrescribible por `.env` |
| 16 | Suite verde contra `megaisp_test` | Fuera de alcance de este runbook (item aparte) |

El paso `validar` en código comprueba, contra la central real (no contra archivos):
CLI responde (`core show version`), ODBC conectado (`odbc show all`), extensiones
publicadas al realtime (`pjsip show endpoints`, comparado contra lo sembrado en MegaISP),
contexto `from-internal` presente en el dialplan, e idioma por omisión aplicado
(`core show settings`).

---

## 5. Warm-up de MegaISP (antes y después de provisionar)

Igual que cualquier deploy de MegaISP — **NUNCA `config:cache`** con esto recién tocado:

```bash
php artisan config:clear
php artisan route:clear
php artisan queue:restart
```

Si el `.env` cambió (por ejemplo `MEGAISP_ARTEFACTOS_URL`, `MEGAISP_ASTERISK_GENERADOS_DIR`
o `MEGAISP_ASTERISK_SOPORTE_DIR`), correr `config:clear` antes de `voip:provisionar` para
que el manifiesto lea los valores nuevos.

---

## 6. Verificación de endpoints VoIP (tras el deploy)

1. **UI de MegaISP** — `/voip/extensiones` (listado con estado en vivo) y
   `/voip/troncales` (si hay troncal configurada). Confirmar que las extensiones sembradas
   aparecen como activas.
2. **Desde la central real** (no desde MegaISP — es la comprobación que cierra el
   criterio #5):
   ```bash
   sudo -n /usr/sbin/asterisk -rx "pjsip show endpoints"
   ```
   Debe listar las extensiones sembradas (30 en la corrida de referencia).
3. **Llamada de prueba real, con audio en ambos sentidos:**
   ```bash
   provisioning/verificar-llamada.sh
   ```
   Levanta dos softphones con credenciales de dos extensiones sembradas —leídas de la base
   realtime, no de una lista escrita en el script—, marca de una a otra, y comprueba que
   **cada lado recibió el tono del otro** (440 Hz quien llama, 880 Hz quien contesta — dos
   tonos distintos para no confundir audio cruzado con un eco local). Exit 0 = ambos
   sentidos con audio.
4. **Segunda corrida idempotente** (opcional, para confirmar que no rompe nada):
   ```bash
   php artisan voip:provisionar --no-interaction
   ```
   Debe terminar en verde, con los pasos caros (`descargar`/`compilar`/`instalar`/
   `preservar_alembic`) omitidos y sin duplicar extensiones.

---

## 7. Rollback

El provisionador de Asterisk es un paso **aditivo e idempotente** sobre el servidor VoIP;
no modifica nada de MegaISP que otro módulo dependa. El rollback de una versión de MegaISP
que incluyó cambios de VoIP es el mismo de cualquier release:

1. **Revertir el tag** — volver al checkout de la versión anterior
   (`git checkout tags/<version-anterior>`), igual que cualquier rollback de deploy de
   MegaISP.
2. **`down()` de las migraciones aditivas del módulo** — todas las migraciones de VoIP de
   esta fase (`voip_provision_estado`, `voip_perfiles_extension`,
   `voip_rangos_numeracion`, la columna `rango_id`/`perfil_id` en `voip_extensiones`) tienen
   `down()` que revierte limpio (`dropIfExists` / drop de columna con guard
   `Schema::hasTable`). Correr `php artisan migrate:rollback` normal, NUNCA
   `migrate:fresh`.
3. **Asterisk en sí NO se desinstala en un rollback normal** — el provisionador es
   aditivo y la central sigue funcionando con la config que ya tenía; solo se revierte
   el código/esquema de MegaISP que lo orquesta. Si hiciera falta dejar el servidor VoIP
   limpio de cero (solo para pruebas, **DESTRUCTIVO**):
   ```bash
   php artisan voip:provisionar --desinstalar --confirmar=SI-BORRAR-ASTERISK
   ```
   Borra binario, `/etc/asterisk`, `/var/lib/asterisk`, el árbol de soporte, el usuario
   de sistema, la unit de systemd y vacía la base realtime. **Nunca correr esto en un
   servidor con extensiones reales en uso** — solo para volver a probar la provisión
   de cero (criterio #1).

---

## 8. Troubleshooting — si reaparece una variante de los 12 defectos ya corregidos

Si algún síntoma de abajo reaparece, la causa más probable es una variante del mismo
patrón ya documentado en `docs/bitacora/2026-09-10-item-9990718.md` — revisar ahí antes
de investigar desde cero:

| Síntoma | Dónde mirar primero |
|---|---|
| La central figura `active (running)` pero `asterisk -rx` no responde | `RuntimeDirectory=asterisk` en la unit de systemd |
| `alembic upgrade head` corre "bien" pero el paso `migrar_esquema` falla al leer la revisión | Está leyendo `alembic_version_<árbol>`, no `alembic_version` — confirmar que lee `alembic_version_config` |
| Compiló "bien" pero falta un módulo (p. ej. `res_odbc`) | menuselect por categoría real del `menuselect.makeopts`, no por `MENUSELECT_MODULES=` — revisar si la dependencia de compilación estaba instalada |
| 30 extensiones en MegaISP, 0 en `pjsip show endpoints` | Paso `siembra` — confirmar que publicó a `ps_endpoints`/`ps_auths`/`ps_aors`, no solo a `voip_extensiones` |
| Extensiones registran pero no pueden llamarse entre sí | Falta el contexto `from-internal` en `extensions.conf` generado |
| Llamada se establece pero sin audio en un sentido | Revisar `pjsip.conf`: nombre del `transport` debe coincidir entre la plantilla y lo que publican las extensiones/troncales (`transporte` del manifiesto, hoy `transport-udp`) |
| `manager.conf`/`ari.conf` con secreto vacío | Orden de pasos: `credenciales` debe correr ANTES que `config` (§3, pasos 11-12) |
| Config generada no se aplicó, sigue con los ejemplos de `make samples` | El detector de "archivo modificado a mano" — revisar huella tipo conffile en `GeneradorConfigAsterisk`, buscar archivos `.nuevo` al lado del destino |
| DSN de unixODBC no abre | Orden de pasos: `crear_base` debe correr ANTES que `generar_config` (§3, paso 8) |
