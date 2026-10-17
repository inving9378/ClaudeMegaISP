## 2026-10-16 20:35 — Reporte final: Facturación completa MegaISP (ejecución autónoma)

Todo en **dev** (`192.168.105.11`, BD `megaisp`), en el worktree propio `/var/www/megaisp-pf`, con
merges locales a `main` (último: ver §1). **Nada en producción:** sin push, tag, release, envíos a
clientes, cambios a `envio_pausado` ni liberación de colas.

### 1. FAC → items → estado → commits → merge

| FAC | Item | Estado | Commits principales | Merge |
|---|---|---|---|---|
| FAC-01 guía para mostrador | #506 | completado | `4a706d7c` | `052ad6d0` |
| FAC-02 pedir CSF | #507 | completado | `a3997f0a` | `ac9e462e` |
| FAC-03 aviso de privacidad | #508 | completado | `53b9825c` `cdbbdeb7` `4d775b27` | `34054ffd` |
| FAC-04 factura pagada reemplaza recibo | #509 | completado | `b9d3a5c5` `7c50b96a` `a40f4946` | `3917580d` |
| FAC-05 PDFs livianos + evidencia | #510 | desarrollo hecho; espera #511 | `9316cec9` `82520cff` `cb770c56` `0957425d` | `d63c6f31` |
| FAC-05b retención | #511 | requiere_irving | — (propuesta en el item) | — |
| FAC-06 proforma N días antes | #512 | desarrollo hecho; espera #513 | `831051f5` `fd4bb50f` `758f0d56` | `dbeff1da` |
| FAC-06b activar | #513 | requiere_irving | — | — |
| FAC-07 cancelar colas | #514 | script listo; requiere_irving | `d9f07554` `97a5dc92` `9fc251e2` | `deb88456` |
| FAC-08 reactivación gradual | #515 | compuerta lista; requiere_irving | `af8a3c36` `c6a623ab` | `b8691d3f` |
| FAC-09 documento por WhatsApp | #516 | desarrollo hecho; espera #517 | `4b3705b5` `fb3b7171` | `d99d6060` |
| FAC-09b activar WhatsApp | #517 | requiere_irving | — | — |
| FAC-10 portal | #518 | completado | `cf5a8c72` `d99947fd` | `0e21e569` |
| FAC-11 indicadores | #519 | completado | `0d49871b` `8300ec26` | `3b66004c` |
| FAC-12 folio amigable (doc) | #520 | requiere_irving | `ed0c027e` | `5be4e768` |
| FAC-13 CLABE virtual | #521 | desarrollo hecho; espera #522 | `90013ea6` `0f49a7aa` | `602f5223` |
| FAC-13b activar | #522 | requiere_irving | — | — |
| FAC-14 revisión legal (doc) | #523 | requiere_irving | `3676458a` | `9ac0e024` |
| FAC-15 CFDI 4.0 (doc) | #524 | requiere_irving | `3bd14228` | `9ac0e024` |

«Desarrollo hecho; espera #…»: el padre queda `aprobado_irving` y fuera del pool por el guard de
paraguas hasta que se decida su subitem de activación.

### 2. V1.61
**Comprobado en dev:** Prefactura v2, ícono único, columna Documento («Tipo de factura» oculta),
badges claro/oscuro, método de pago por nombre, prueba de ttyd 404/502, y la exportación de la
ficha con filtro fijo (Excel de 7689 = 2 filas, con «Tipo de factura»). #504 y #505 completados.
**Pendiente:** publicar (no autorizado); preflight `arbol_limpio` bloqueado por 4 bitácoras sin
commitear de otras sesiones; producción no verificada. Notas y checklist actualizados en
`docs/bitacora/2026-10-16-release-v161-preparacion.md` (sección 19:12).

### 3. Borradores de versión (sin número oficial)
`docs/bitacora/2026-10-16-borrador-version-facturacion-lote1.md` (interceptación, FAC-01..03),
`…-lote2.md` (FAC-04..06), `…-lote3.md` (FAC-07..09), `…-lote4.md` (FAC-10..15).

### 4. Decisiones `requiere_irving` (opciones, recomendación, impacto)
| Item | Decisión | Recomendación | Impacto |
|---|---|---|---|
| #511 | retención/borrado de PDFs | conservar evidencias el plazo que diga el contador; borrar tickets no enviables y PDFs de trabajo con copia en evidencias a 90 días | disco; las evidencias no se pueden regenerar |
| #513 | N días y regla | N = 21 (mediana actual), días naturales | fecha de las proformas; ⚠️ `delay_days` vale 1 en dev |
| #514 | fecha límite y ejecución | `--hasta` = día del despliegue de FAC-04, antes de FAC-08 | cancela (no borra) colas viejas |
| #515 | etapas, piloto, zona, DKIM | piloto con equipo interno y límites bajos; DKIM verificado antes | primeros envíos reales |
| #517 | línea Meta y plantillas | empezar por la factura pagada | costo por conversación de Meta |
| #520 | folio amigable | opción 1 «MN-2026-001234» | migración + backfill |
| #522 | activar CLABE virtual | tras certificación y validar CLABE de prod | dato de pago al cliente |
| #523 | contratar revisión legal | prioridad A1–A2, C7–C9, B4–B6 | nombres y textos de documentos |
| #524 | PAC, CSD, PUE/PPD, global | según contador | timbrado fiscal |
| #525 | menú Facturas | apuntar a `/finanzas/invoices` | navegación |
| #526 | exportación sin límite | tope de filas + exportación en cola | estabilidad |
| #527 | marcar pagada en canceladas | ocultar y validar en backend | dinero |
| #528 | badge Estado en oscuro | misma exclusión que Documento | visual |
| #529 | reconstruir `megaisp_test` | `migrate:fresh --seed` sobre `megaisp_test` cuando nadie corra pruebas | pruebas con BD |
| #530 | permisos de estadísticas del Dashboard | mapear cada ruta a su permiso | amplía acceso a quien ya tiene el permiso |
| #502 | aplicar la interceptación al `.env` de dev | activarla con destino de pruebas | dev deja de poder mandar a clientes |

### 5. Acciones productivas preparadas (orden por dependencias)
1. Desplegar la versión (lotes 1–4 + V1.61) → `migrate` (3 migraciones de V1.61 + `200000`, `200100`,
   `210000`, `220000`), `npm run prod`, `queue:restart`; checklists de cada borrador.
2. Verificaciones de solo lectura en prod: `prefactura:actualizar-plantilla --dry-run`,
   `billing:send-pending-notifications --dry-run`, consulta de prefacturas de facturas pagadas
   (#509), `billing:pdfs-inventario`, `invoice:proformas-simular --dias=21`, `billing:reactivacion-estado`.
3. Capturar URL del aviso de privacidad (#508).
4. FAC-07: preparar manifiestos, aprobar sha256, ejecutar (#514).
5. FAC-06b: fijar `delay_days` y, si se aprueba, `BILLING_PROFORMA_ANTICIPADA=true` (#513).
6. FAC-08: piloto → zona → todos, quitando la pausa al iniciar el piloto (#515).
7. FAC-09b / FAC-13b cuando haya línea Meta aprobada y OpenPay certificado.

### 6. Textos
- **FAC-01** — guía `docs/manual/guia-documentos-facturacion.md`; WhatsApp (6 líneas) en la misma guía y
  en #506.
- **FAC-02** — WhatsApp, correo y checklist en `docs/manual/solicitud-constancia-situacion-fiscal.md`.
- **FAC-09** — plantillas propuestas `prefactura_documento` y `factura_pagada_documento` en
  `docs/bitacora/2026-10-16-item-516.md` (y `config/whatsapp_templates.php`).
- **FAC-14** — `docs/manual/revision-legal-documentos-cobro.md`.

### 7. Pantallas y muestras para revisar
- LAN (sin login): `http://192.168.105.11/reportes/facturacion/` — `fac03_form_light.png`/`_dark.png`,
  `fac03-pie-aviso.png`, `fac10_portal_claro.png`/`_oscuro.png` (datos sintéticos),
  `fac11_dashboard_light.png`/`_dark.png`, `muestra-L1-01.pdf` (sintética).
- En el sistema: Configuración › Información de la empresa (campo nuevo); Dashboard (bloque
  Facturación); Portal › Facturas (sección nueva, entrar con una cuenta de prueba).
- Muestra de correo `FAC-MUESTRA-L1-01` (1 de 5 del lote 1): aceptada por el SMTP de dev el 16-oct
  19:18; confirmar que llegó al buzón de Irving.

### 8. Bloqueos, limitaciones y pruebas no realizadas
- **Incidente #529:** una corrida de pruebas incluyó por error una prueba ajena con RefreshDatabase y
  el timeout dejó `megaisp_test` a medio migrar. Las pruebas con BD de FAC-07 y FAC-10
  (`CancelarColasCobroTest`, `PortalDocumentoVigenteTest`) **no se han corrido**; sus escenarios se
  validaron en dev en transacciones revertidas. Las de FAC-04/05/06 sí corrieron antes del incidente.
- No probado: «Enviar» manual real con evidencia; dos barridos de envío en paralelo; un envío real por
  etapa; sesión real del portal en navegador; usuario no administrador viendo el bloque del Dashboard;
  la forma exacta del header documento en plantillas de Meta (pendiente de confirmar contra su
  referencia).
- Workers de cola compartidos **no reiniciados** (regla): hasta su reinicio no tienen los listeners ni
  observers nuevos.
- Hallazgos de datos en dev: 5,879 de 7,314 prefacturas en espera son de facturas ya pagadas; la única
  CLABE virtual no pasa el dígito de control; `delay_days` de proforma = 1.

### 9. Aislamiento, interceptación y fixtures propios
- Interceptación de correo en código (`mail.interceptar`, apagada por defecto, destino único, fallo
  cerrado); usada solo en procesos aislados. **No aplicada al `.env` compartido** (pendiente de #502).
- Muestras: 1 intento (lote 1), a Irving, sintética.
- Fixtures: todos dentro de transacciones revertidas (0 filas residuales verificadas en dev y en
  `megaisp_test`); archivos temporales de manifiestos/evidencias borrados; la tabla temporal de
  pruebas desaparece con la conexión.
- Usuario temporal `prueba_prefactura_480` (id 5032): **desactivado y sin rol** (no se borró para no
  romper su referencia en `activity_log`); su sesión se eliminó.
- `megaisp_test`: ver #529.
