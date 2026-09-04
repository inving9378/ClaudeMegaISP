# Barrido de comandos en `app/Console/Commands/Scripts/` — rango alfabético S-U (item #838, sub-item de #790)

**Alcance de esta pasada:** los 13 comandos de `app/Console/Commands/Scripts/` cuyo nombre de
archivo cae en el rango S-U (`ScriptFixedFechaCorte` … `UpdateTaskStartAndFinishDate`), cerrando
el barrido de `Scripts/` que dejaron registrado los precedentes `#836` (rango A-D) y `#837` (rango
E-R) como "pendiente registrado como sub-item" — misma metodología de `#647` (módulo Circuito CC)
y `#789`/`#657` (`Active/`, `Olts/`).

**SOLO INVENTARIO** (mandato explícito del item, heredado de `#836`/`#837`): esta pasada NO corrió,
borró ni desconectó nada. Cada veredicto es lectura estática (+ un puñado de consultas de solo
lectura contra la BD de dev, cuando la clasificación dependía de si una condición seguía vigente)
para que Irving decida en una vuelta futura.

## Método (sitios consultados, para los 13 — mismos 8 de los precedentes `#836`/`#837`)

1. `crontab -l` del usuario `meganet` (13 líneas activas, todas `circuito:*`/`backup_db:process`
   vía `cron-wrap.sh`/`vigilia-wrap.sh` — cero líneas para cualquiera de los 13 comandos de este rango).
2. `app/Console/Kernel.php::schedule()` — schedule hard-coded (~45 líneas `$schedule->command(...)`/
   `->job(...)`/`->call(...)`) — cero coincidencias con los 13.
3. `command_configs` (schedule dinámico en BD, modelo `App\Models\CommandConfig`) — consultado
   directo por tinker con los 13 `signature`: **0 filas** (ninguno tiene fila de schedule dinámico).
4. `grep -rn "Artisan::call\|->call("` sobre `app/` y `routes/`, cruzado contra los 13 signatures —
   **0 coincidencias**. También se leyó completo `AdministracionController.php` (9 `Artisan::call`
   reales) — ninguno de los 13 aparece.
5. `grep` de cada `signature` y de cada nombre de clase sobre `app/`, `routes/`, `resources/js/`,
   `config/`, `deploy/`, `database/` — **0 coincidencias** fuera del propio archivo de cada comando
   (ni siquiera en comentario/docblock de otro archivo — a diferencia de `#837`, que tuvo 3 menciones
   documentales).
6. `resources/js/components/module/adminstration/show_scripts/ShowScripts.vue` (pantalla "Scripts
   Ejecutables", 5 botones reales) y `resources/js/components/module/setting/IndexSetting.vue`
   (pantalla "Configuración") — cero enlaces a los 13 de este rango.
7. `app/Modules/Core/Usuarios/routes.php` (grupo `/administracion`) + `AdministracionController`
   leído completo — ninguno de sus métodos invoca alguno de los 13.
8. Verificación adicional de este rango (no necesaria en `#836`, sí parcialmente en `#837`):
   **reflexión de las clases/métodos que cada comando invoca** (`InformationService`,
   `DurationContractRepository`, `ClientMainInformationRepository`, etc.) para confirmar que el
   código no está roto — reveló un hallazgo real (ver F2).

### Nota metodológica — verificaciones de datos en vivo (solo lectura)

Igual que `#837`, varios comandos de este rango solo se pueden clasificar correctamente sabiendo si
la condición que corrigen **sigue vigente hoy**. Se corrieron consultas de solo lectura (conteos,
sin escribir nada):

| Comando | Condición verificada | Resultado |
|---|---|---|
| `ScriptFixedFechaCorte` | Clientes activos con `fecha_corte IS NULL` | **6** clientes |
| `SetNewFechaCorteToClientsWhereHasPackageAdminstracion` | Clientes con paquete "administracion" y `fecha_corte` distinto de `2027-12-31 23:59:59` | **18** clientes |
| `UpdateClientsPrepaidDailyToPrepaidCustom` | Clientes con `type_of_billing_id = PREPAID_DAILY` | **2** clientes |
| `UpdateHtmlDocumentTemplates` | `document_templates.html` con la ruta vieja `/home/MEGANET/public` | **0** (ya migrado) |
| `UpdateHtmlDocumentTemplates` | `document_templates.html` con el placeholder `${data.image_src}` sin reemplazar | **1** plantilla |
| `UpdateIsPaymentTransactionClientsCommand` | `transactions` con `is_payment=false AND type=credit` | **0** filas (no-op si corriera hoy) |
| `UpdatePricePackageClients` | `ClientBundleService.price` ≠ `bundle.price` actual | **3** servicios |
| `UpdatePricePackageClients` | `ClientInternetService.price` (sin bundle) ≠ `internet.price` actual | **71** servicios |

---

## A. CONFIRMADOS — arranque real verificado (0)

Ninguno en este rango — los 13 comandos están completamente desconectados de cualquier disparador
automático o botón de UI.

## B. FALSO POSITIVO A EVITAR

Ninguno detectado en este rango.

## C. DUAL-PROPÓSITO

Ninguno detectado en este rango — mismo patrón que `#789`/`#836`/`#837`: en `Scripts/` cada comando
ES su propia lógica, sin un servicio de ciclo hermano que la duplique por fuera. (La media-conexión
de `UpdateINternetServicesToClients` con un panel de diagnóstico de solo-lectura no cuenta como dual
propósito real — ver F4, es un caso distinto: el DETECTOR vive conectado, el CORRECTOR no.)

## D. MANUAL POR DISEÑO — sin invocador automático, y no debería tenerlo (1)

| Comando | Por qué es manual a propósito |
|---|---|
| `app:update-files-client-change` (`UpdateFilesClientChange`) | Pese al nombre "Update", el `handle()` **no escribe nada**: recorre el `activity log` de cada cliente y solo hace `Log::info(...)` cuando detecta que el `id` cambió entre dos snapshots consecutivos. Es puramente diagnóstico/de solo-lectura, mismo patrón que `client:references` (`#837`, categoría D) — el nombre es engañoso pero el comportamiento real es inofensivo. |

## E. BACKFILLS DE UNA SOLA CORRIDA — dormidos a propósito, o pendientes de decidir su corrida (7)

| Comando | Qué hace | Evidencia de que ya cumplió o de que sigue pendiente |
|---|---|---|
| `app:script-fixed-fecha-corte` (`ScriptFixedFechaCorte`) | Para clientes activos con `fecha_corte` nula, busca en su `activity_log` el último valor que tuvo y lo restaura | **Verificado en dev: 6 clientes activos siguen con `fecha_corte` nula hoy.** Condición activa y sin resolver — no hay garantía de que los 6 tengan un rastro utilizable en el activity log (el comando simplemente no hace nada si no lo encuentra), pero la condición que motivó el script no está en cero. |
| `app:set-new-fecha-corte-to-clients-where-has-package-adminstracion` (`SetNewFechaCorteToClientsWhereHasPackageAdminstracion`) | Fuerza `fecha_corte`/`fecha_pago` a un valor fijo en 2027 (efectivamente "nunca vence") para clientes con paquete `administracion`, activa al cliente y libera su servicio de Mikrotik | **Verificado en dev: 18 clientes con ese paquete siguen sin la fecha 2027 aplicada.** Fecha hardcodeada (`2027-12-31`) — cuando se acerque esa fecha el propio script quedará obsoleto y habrá que repetir el patrón con una fecha nueva; no es un problema hoy. |
| `app:update-client-contract-term` (`UpdateClientContractTermCommand`) | Backfill de `duration_contract_id` según 3 rangos de `created_at` hardcodeados (`< 2023-07-01` → 6 meses, `< 2024-09-12` → 12 meses, resto → 18 meses) | Determinístico e idempotente (recorre TODOS los clientes cada vez, sin filtrar por si ya tienen el valor correcto) — recorrerlo de nuevo no rompe nada, pero tampoco aporta si ya corrió. Sin forma de distinguir "ya corrió" de "nunca corrió" sin un campo de auditoría propio. |
| `app:update-clients-prepaid-daily-to-prepaid-custom` (`UpdateClientsPrepaidDailyToPrepaidCustom`) | Migra clientes con facturación `PREPAID_DAILY` a `PREPAID_CUSTOM` (activa o bloquea según si pagaron en el último mes, resetea balance a 0, toca Mikrotik) | **Verificado en dev: 2 clientes siguen en `PREPAID_DAILY` hoy.** Si `PREPAID_DAILY` ya no es un tipo de facturación que se ofrezca a clientes nuevos (candidato a confirmar con Irving), estos 2 son los últimos rezagados de una migración de modelo de negocio inconclusa. |
| `app:update-html-document-templates` (`UpdateHtmlDocumentTemplates`) | Reemplaza la ruta vieja de imágenes `/home/MEGANET/public` por `public_path()` actual, y el placeholder `${data.image_src}` por el mismo `public_path()` | **Verificado en dev:** la ruta vieja ya no aparece en ninguna plantilla (0), pero **1 plantilla sigue con el placeholder `${data.image_src}` sin reemplazar**. ⚠️ **Hallazgo:** el segundo reemplazo sustituye el placeholder por `public_path()` — una ruta de **filesystem del servidor** (ej. `/var/www/megaisp/public`), no una URL (`url()`/`asset()`); si esto se usa dentro de un `<img src="...">` de una plantilla HTML servida al navegador, el resultado sería una imagen rota (el navegador no puede resolver una ruta de disco del servidor). No se verificó el contenido exacto de la plantilla pendiente para no forzar su lectura fuera de alcance (solo inventario), pero el propio código deja ver la confusión ruta-de-disco vs. URL. |
| `app:update-is-payment-activation-cost-client` (`UpdateIsPaymentActivationCostClientCommand`) | Backfill de `is_payment_activation_cost` en `client_main_information`: `true` fijo para `client.id <= 3137` (instalación gratis histórica), y para el resto compara el primer pago contra `amount_technician_and_why` | Umbral de id hardcodeado (`3137`), determinístico e idempotente — mismo patrón que `UpdateClientContractTermCommand`. Sin campo de auditoría para saber si ya corrió. |
| `app:update-task-start-and-finish-date` (`UpdateTaskStartAndFinishDate`) | Recorre **todas** las `Task` y normaliza `start_time` a `HH:00:00` y `end_time` a `HH:59:59`, preservando la hora pero forzando minutos/segundos | Limpieza de formato de un solo lote, sin condición de negocio que verificar (no hay "cuántas tareas están mal" — el comando reescribe TODAS incondicionalmente cada vez que corre, así que repetirlo es inofensivo pero inútil una vez aplicado). |

## F. HUÉRFANOS — sin arranque, con veredicto (5)

### F1. `app:suspend-clients-not-active` (`SuspendServicesClientsNotActive`) — HUÉRFANO, lote histórico con 109 IDs hardcodeados

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- El `handle()` no tiene ninguna condición ni query: es un arreglo literal de **109 client IDs**
  específicos (`550, 1002, 1310, …, 6442`) sobre los que despacha `MikrotikCreateAddressList`
  (bloqueo de red) y registra actividad "Suspendido el servicio de internet en mikrotik" — sin
  verificar el estado actual de esos clientes (podrían haber vuelto a pagar y estar activos hoy).
- **Veredicto: HUÉRFANO, snapshot de un lote de suspensión de una fecha específica ya ejecutado (o
  abandonado) en el pasado.** Mismo patrón que `SuspendServicesClientsNotActive`-tipo de `#836`/`#837`
  (scripts con IDs de una corrida puntual, no reutilizables sin re-auditar la lista). Re-ejecutarlo
  hoy suspendería indiscriminadamente a cualquiera de esos 109 clientes que ya esté al corriente —
  **riesgo real si alguien lo corre a ciegas pensando que es una herramienta genérica** (el nombre
  `app:suspend-clients-not-active` no deja ver que es una lista fija). Candidato a retirar o, como
  mínimo, renombrar/documentar que es un lote histórico cerrado.

### F2. `app:todos-clientes-con-fecha-ultimo-pago-menor-este-anno-pasan-a-inactivo` (`TodosClientesConFechaUltimoPagoMenorEsteAnnoPasanAInactivo`) — HUÉRFANO, **ROTO** (confirmado por reflexión)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- **Hallazgo (comando roto, no solo huérfano):** el `handle()` llama
  `$informationService->obtenerClientesConFechaDeUltimoPagoMenorAEsteAnno()` — **ese método NO
  existe** en `App\Services\InformationService` (confirmado con `ReflectionClass::hasMethod()` →
  `false`; tampoco existe con otro nombre parecido en toda la clase). Si este comando se ejecutara
  hoy, fallaría de inmediato con `Error: Call to undefined method`, **antes de tocar un solo
  cliente** — es inofensivo por accidente (crashea temprano), no por diseño.
- Hallazgo secundario: el archivo importa `use function PHPUnit\Framework\isNull;` — una función de
  testing, no del lenguaje — y la usa en `if(isNull($client->fecha_fin_periodo_gracia))`. `phpunit`
  suele ser dependencia `require-dev` (fuera del autoload de producción); de haber llegado a esa
  línea, muy probablemente habría fallado también ahí. Es moot porque el comando ya truena antes,
  pero confirma que este archivo nunca se probó tal cual está — el error del método inexistente
  hace pensar que el comando quedó a medio escribir/editar y nunca se volvió a tocar desde entonces.
- **Veredicto: HUÉRFANO Y ROTO.** No es candidato a "falta conectar" sin antes arreglarlo — conectarlo
  tal cual (cron o botón) solo produciría un error en cada corrida. Candidato a retirar, o a
  reescribir si la necesidad de negocio ("clientes con último pago antes de este año → inactivo")
  sigue vigente.

### F3. `app:update-is-payment-transaction-clients-command` (`UpdateIsPaymentTransactionClientsCommand`) — HUÉRFANO con hallazgo de lógica (no hace lo que dice)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Descripción propia: *"Todas las transacciones que sean tipo credito **y que no sean pago costo de
  instalacion** se deben marcar como is_payment = true"* — implica excluir explícitamente los pagos
  de costo de instalación.
- **Hallazgo (bug de lógica, mismo patrón que F6 de `#837`):** el código real solo filtra
  `where('is_payment', false)->where('type', 'credit')` — **no hay ningún filtro que excluya
  transacciones de costo de instalación** (ni por `category`, ni por ninguna otra columna). Si
  hubiera transacciones de costo de instalación con `is_payment=false` y `type=credit`, el comando
  las marcaría como `is_payment=true` + `category='Pago'` igual que cualquier otra — exactamente lo
  que su propia descripción dice que NO debe pasar.
- **Verificado en dev: 0 filas cumplen `is_payment=false AND type=credit` hoy** — el comando sería
  un no-op si se corriera ahora (no hay nada que romper con el bug tal cual está la BD hoy), pero
  eso no valida la lógica — mismo matiz que `RemoveReminderConfigurationCommand` en `#837`: si algún
  día vuelven a existir transacciones en ese estado (incluyendo alguna de costo de instalación), el
  bug se activaría en silencio.
- **Veredicto: HUÉRFANO, con bug confirmado por lectura de código.** No se toca aquí (solo
  inventario) — se documenta para que la corrección, si se decide, agregue el filtro de exclusión
  que la propia descripción promete.

### F4. `app:update-i-nternet-services-to-clients` (`UpdateINternetServicesToClients`) — candidato a FALTA CONECTAR (su mitad de DETECCIÓN ya está viva en un panel de solo lectura)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo **para el
  comando**. Pero el método que usa para encontrar candidatos,
  `InformationService::getClientesConServiciosDeInternetQuePertenecenAunPaqueteYelPaqueteNoExiste()`,
  **sí tiene un segundo consumidor**: aparece en el arreglo de reglas de
  `InformationService::getInformation()` (línea 63, bajo la etiqueta "Clientes con servicios de
  Internet pero paquete no existe") — un panel de diagnóstico de solo lectura que expone esta y
  otras ~15 condiciones de salud de datos.
- Es decir: la parte de **detección** de esta condición SÍ está conectada y visible (aunque sea
  solo-lectura, en un panel que alguien tiene que mirar); la parte de **corrección** (este comando,
  que desasocia el servicio del bundle inexistente y bloquea al cliente si no ha pagado en 4 meses)
  no tiene ningún disparador. Mismo patrón conceptual que F3 de `#647` (`circuito:coherencia-pool`,
  comando diseñado para colgarse de un cron y nunca se conectó) pero aquí es un detector-sin-su-
  corrector, no un comando huérfano completo.
- **Veredicto: candidato a FALTA CONECTAR** (si la corrección debe aplicarse automáticamente cuando
  el panel detecta el caso) **o a quedarse manual pero documentado como "la acción que corresponde a
  esta fila del panel de diagnóstico"** — hoy nada en la UI del panel apunta a este comando, así que
  quien lo mira no tiene forma de saber que existe una herramienta lista para corregirlo. Irving
  decide.

### F5. `app:update-price-package-clients` (`UpdatePricePackageClients`) — AMBIGUO, toca dinero (precios de servicios)

- **Sitios buscados:** los 7 estándar — cero resultados fuera de su propio archivo.
- Sincroniza el `price` de cada servicio contratado (`ClientBundleService`, `ClientInternetService`,
  `ClientCustomService`, `ClientVozService`) con el precio **actual** de su plan/paquete, cuando
  difieren — un patrón de reconciliación, no de backfill de una sola vez (el precio del plan puede
  cambiar en cualquier momento después).
- **Verificado en dev: 3 `ClientBundleService` + 71 `ClientInternetService` tienen HOY un precio
  distinto al de su plan actual** (no se contaron `ClientCustomService`/`ClientVozService` para no
  extender la pasada más de lo necesario — el patrón de drift ya queda demostrado con los dos
  primeros). Es una condición **activa y con dinero de por medio**: si el precio cobrado a un
  cliente debe reflejar siempre el precio vigente del plan, estos 74+ servicios están cobrando
  (o facturando) un monto desalineado del catálogo actual.
- **Veredicto: AMBIGUO.** Mismo patrón que F4 de `#837` (`GeneraGeneralAccountingIncomeCommand`,
  también sin cron y con dinero de por medio) — si el precio debe autosanarse cuando cambia el plan,
  este comando debería estar en cron (como `embajadores:rebuild-kpis`); si el precio de un servicio
  ya contratado debe quedar "congelado" al momento de la contratación salvo renovación explícita
  (práctica común en ISPs — no repricear retroactivamente a clientes existentes), entonces los 74
  casos detectados NO son un problema y este comando nunca debería correr sin criterio caso-por-caso.
  No se puede distinguir sin que Irving confirme la regla de negocio — **frontera de dinero real**,
  no se toca aquí.

---

## Resumen

| Categoría | Cantidad | Comandos |
|---|---|---|
| A. Confirmados (arranque real) | 0 | ninguno en este rango |
| B. Falso positivo descartado | 0 | ninguno en este rango |
| C. Dual-propósito | 0 | ninguno en este rango |
| D. Manual por diseño (intencional, correcto así) | 1 | `app:update-files-client-change` (solo lee/loguea, no escribe nada pese al nombre) |
| E. Backfill de una sola corrida (dormido a propósito o pendiente de decidir) | 7 | `app:script-fixed-fecha-corte` (6 clientes pendientes), `app:set-new-fecha-corte-to-clients-where-has-package-adminstracion` (18 pendientes), `app:update-client-contract-term`, `app:update-clients-prepaid-daily-to-prepaid-custom` (2 pendientes), `app:update-html-document-templates` (⚠️ hallazgo: reemplaza placeholder por ruta de filesystem, no URL — 1 plantilla pendiente), `app:update-is-payment-activation-cost-client`, `app:update-task-start-and-finish-date` |
| F. Huérfanos con veredicto | 5 | `app:suspend-clients-not-active` (huérfano, 109 IDs hardcodeados de un lote histórico), `app:todos-clientes-con-fecha-ultimo-pago-menor-este-anno-pasan-a-inactivo` (**HUÉRFANO Y ROTO** — llama a un método inexistente, confirmado por reflexión), `app:update-is-payment-transaction-clients-command` (huérfano, bug de lógica: no excluye costo de instalación pese a decirlo en su descripción), `app:update-i-nternet-services-to-clients` (candidato a falta conectar — su detector YA está vivo en un panel de diagnóstico, su corrector no), `app:update-price-package-clients` (ambiguo, toca dinero — 74+ servicios con precio desalineado del plan actual hoy) |
| **Total comandos del rango S-U en `Scripts/`** | **13** | — |

**Hallazgo más severo de esta pasada:** `TodosClientesConFechaUltimoPagoMenorEsteAnnoPasanAInactivo`
(F2) no es solo un huérfano — está **roto**: llama a un método que no existe en `InformationService`.
Es el primer caso, en las 3 pasadas de `Scripts/` (`#836`/`#837`/`#838`), de un comando que crashearía
de inmediato si alguien lo corriera, en vez de ejecutar una lógica indeseada o quedarse en silencio.

FUERA DE ALCANCE DE ESTA PASADA (mismo mandato que los precedentes): no se corrió, borró, corrigió
ni desconectó nada — ni siquiera el método faltante de F2 ni el filtro faltante de F3. Los 5
huérfanos con veredicto, la ambigüedad de precios (dinero real) y la decisión de qué hacer con los
backfills aún pendientes (6+18+2 clientes, 1 plantilla) quedan para que Irving decida item por item
en una vuelta futura.

## Cierre del barrido de `app/Console/Commands/Scripts/`

Con este rango (S-U) se completa la cobertura alfabética completa del directorio `Scripts/` iniciada
por `#836` (A-D, 20 comandos) y continuada por `#837` (E-R, 18 comandos): **51 comandos inventariados
en total** (20 + 18 + 13), que coincide exacto con el conteo real de archivos del directorio
(`ls app/Console/Commands/Scripts/ | wc -l` → 51) — no queda ningún archivo sin clasificar en
`Scripts/`.

**Nota de alcance — NO se verificó lo mismo para `Active/`/`Olts/`:** `Olts/` (`#657`) sí cuadra
exacto (9 comandos documentados = 9 archivos reales). `Active/` (`#657`) documenta 56 comandos pero
el directorio tiene **60** archivos hoy (`ls app/Console/Commands/Active/ | wc -l`) — los 60 SÍ son
todos clases `extends Command` reales (verificado), así que hay **4 sin explicación visible**: o el
directorio creció después de esa pasada, o esa pasada excluyó algunos por un criterio no documentado
en su propio texto. Fuera de alcance de este item (que es específicamente `Scripts/` S-U) — se deja
anotado para que una vuelta futura decida si vale la pena una mini-pasada de reconciliación de
`Active/` en vez de asumir cobertura completa.
