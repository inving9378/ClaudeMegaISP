# 2026-10-22 — Configuración › Finanzas › «Envíos automáticos»

## 2026-10-22 — Pantalla para la pausa general de avisos de cobro

**Dónde vive la pausa:** `billing_config.envio_pausado` (una sola fila). La puso en `true` la migración
`2026_06_04_960012_add_envio_pausado_to_billing_config`. Solo la lee `billing:send-pending-notifications`
(cada 15 min), que manda prefacturas, recibos y tickets de `billing_notifications`. Detrás hay una
segunda compuerta en el `.env`: `BILLING_REACTIVACION_ETAPA` (default `cerrado`, no sale nada) y sus
límites (`ReactivacionEnvios`). `app:send-all-emails-command` (colas viejas) está comentado en el
Kernel: no envía.

**Qué hace la pantalla** (`/configuracion/envios-automaticos`, entrada en el panel de Configuración,
categoría Finanzas):
- Estado Pausados/Activos, quién y cuándo (columnas nuevas `envio_pausado_cambiado_por/_at`; el
  modelo las llena también si el cambio llega por otra vía). La etapa del `.env` a la vista.
- Cola por tipo: en espera, con más de 48 h, con correo, la más vieja.
- `BILLING_PROFORMA_ANTICIPADA`, días de anticipo generales (`billing_config.envio_prefactura_dias`),
  clientes con valor propio y si la creación automática de proformas está activa.
- Reactivar exige elegir: descartar lo de más de 48 h (recomendada; cancela sin borrar y deja
  `storage/app/billing/manifiestos/reactivacion-*.json`, que se revierte con
  `php artisan billing:cancelar-colas --revertir=<archivo> --ejecutar`), enviar todo, o lotes por
  hora (`envio_lote_por_hora`/`envio_lote_hasta`; el envío respeta el tope y lo quita solo cuando ya
  no queda acumulado con correo).
- Solo `super-administrator` o `Super Administrador`; confirmación escribiendo PAUSAR/REACTIVAR;
  auditoría en `activity_log` con `log_name = facturacion-envios`.

**Verificado en dev:** 6 pruebas (`tests/Feature/Facturacion/EnviosAutomaticosTest.php`), más las de
evidencia y factura pagada (sin cambios). En navegador, con usuarios temporales ya borrados: la
pantalla carga en claro y oscuro, el botón de confirmar no se habilita sin la palabra, la entrada
del panel la ve super-administrador y no DESARROLLADOR. **No se pausó ni reactivó nada en dev**: la
cola trae unos 23 mil avisos viejos con correos reales.

Commits: `b10227ec`, `915e0738` (ajustes), `a0f4a208` (modo oscuro).

## 2026-10-22 — Tarjeta en PAGOS y «Depurar cola»

- La tarjeta estaba en el grupo Notificaciones y no se encontraba. Pasó al `module.json` de Finanzas
  (el panel respeta el orden de cada módulo) con `type: pagos`: queda justo después de «Finanzas —
  Facturación». Misma categoría y solo super-administrador. Commit `a1bfc656`.
- **Depurar cola:** cancela (no borra) los avisos con más de N horas (48 por defecto), de un tipo o de
  todos. Muestra antes el conteo exacto por tipo, pide escribir DEPURAR y, si al confirmar la cola ya
  no coincide con lo que se vio, no toca nada y devuelve el conteo nuevo. Deja el archivo que revierte
  `billing:cancelar-colas --revertir` (`storage/app/billing/manifiestos/depuracion-*.json`) y queda en
  `activity_log`. Funciona con los envíos pausados y no cambia la pausa. Commit `a6101329`.
- En dev no se depuró nada (las capturas cortan cualquier POST).
- Para la versión siguiente: #579 (ver detalle de la cola con filtros y selección) y #580 (los tickets
  de caja no deben quedar en cola sin canal: son el comprobante TKT- de cada pago; `send_ticket_email`
  está apagado desde siempre y WhatsApp solo se aprobó para la factura pagada).

## 2026-10-22 — V1.66 publicada (solo envíos)

- Por indicación de Irving, la V1.66 lleva solo la pantalla «Envíos automáticos», la tarjeta en
  Finanzas › Pagos y «Depurar cola». No lleva la Parte B del #571, ni el ajuste de Ignition/Debugbar,
  ni «Asignar trabajo» ni Talento: siguen en main para una versión posterior.
- Rama `release/V1.66-22.10.2026` = tag V1.65 + cherry-pick de `b10227ec`, `c4868cd5`, `f6293900`,
  `8ece9f4e`, `2687c081` (11 archivos, 1 migración aditiva). En la rama, `EnviosAutomaticos::ROLES`
  queda con la lista literal de roles; el helper `SuperAdministrador` llega con la Parte B.
- Verificado con el código de la rama (vendor copiado con enlaces duros, no por symlink, para que
  cargue la rama y no main): `EnviosAutomaticosTest` 8/8, `CancelarColasCobroTest` 5/5,
  `ProformaAnticipadaComandoTest` 8/8; `npm run prod` compila; `/reload_db` sigue fuera.
- Tag `V1.66-22.10.2026` (`051085ba`), releases #113, GitHub release 421195943, deployment_logs #90.
  Solo se subió la rama y el tag; `origin/main` no se tocó y el respaldo de main sigue sin subir.
- Juntada con main en `87b0c15b`: conflictos en `EnviosAutomaticos.php` y `resources/js/app.js`
  resueltos con lo de main; el árbol de main no cambió.
- Al actualizar prod no cambia el estado de los envíos: si están pausados, siguen pausados.
- #580 decidido por Irving: el ticket TKT- es solo para imprimir en caja y no se encola; cuando esté
  pagado se podrá reenviar a mano desde la ficha del cliente. Sale con #579 en la versión siguiente.
- Las capturas de dev estaban en `public/reportes/` y se veían desde dev.meganett.com.mx (HTTP 200).
  Todo `public/reportes/` se movió a `storage/app/reportes/retirado-de-public-2026-10-22/` (700) y
  ahora da 404. No se vuelve a publicar ahí hasta que se aplique el candado de nginx de dev.

## 2026-10-22 — #579 «Ver detalle de la cola» y #580 tickets fuera de la cola (en main dev, sin versión)

- **#579:** enlace «Ver detalle de la cola» en la tarjeta «En cola». Lista de `billing_notifications`,
  50 por página, con tipo, cliente, monto (factura o pago), canal (correo, WhatsApp de respaldo, solo
  caja, sin canal), creado y estado. Filtros: tipo, estado (por defecto «en espera»), antigüedad (más
  de N horas o entre fechas) y cliente (número o nombre). Selección por fila, página o «todos los del
  filtro» para cancelar (en espera o error) o reactivar (cancelado o error; nunca tickets ni lo
  enviado). Conteo exacto antes, palabra CANCELAR/REACTIVAR, conteo vigente al confirmar, archivo
  reversible y `activity_log`. Reactivar no manda nada: sale con el envío automático, la pausa y la
  etapa. Servicio `ColaAvisos`; el cambio reversible quedó en `EnviosAutomaticos::cambiarEstado()`.
- **#580 (decisión de Irving):** el ticket TKT- es solo para caja. `generarTicketYRecibo` ya no crea
  la fila del ticket (solo el recibo). En los pagos de la ficha, el ícono del ticket abre
  `/cliente/billing/payment/ticket/{id}`: el ticket en papel de 80 mm para imprimir y «Enviar por
  correo» a mano (no depende de la pausa; queda en el historial del cliente, `activity_log` y como
  evidencia). Ver: `client_payroll_payment_view_client`; enviar: `client_payroll_payment_add_client`.
- Pruebas: `ColaAvisosTest` (6), `TicketCajaTest` (3), la del ticket en `FacturaPagadaReemplazaReciboTest`.
- Capturas claro/oscuro en PDF adjunto a los items #579 y #580 (no en public/).

## 2026-10-22 — Depuración en dev sin archivo para revertir; arreglo

- A las 15:18 el usuario `admin` (id 1) usó «Depurar cola» (48 h, todos) en dev: 23,638 avisos
  pasaron a «cancelado» con la nota «Descartada al depurar la cola…». La auditoría apunta a
  `storage/app/billing/manifiestos/depuracion-20261022-151841.json`, **que nunca se guardó**: la
  carpeta la había creado la terminal (0700, usuario meganet) y el servidor web no podía escribir;
  `Storage::put()` devolvió false sin error y la cancelación se confirmó igual.
- Se pueden reconstruir si hace falta: eran «en espera» y son las filas con esa nota y
  `updated_at` entre 15:18:38 y 15:18:41. No se restauraron (falta decisión).
- Arreglo (`27771e2f`, también en la rama de la V1.67): `App\Support\ArchivoObligatorio` — sin
  archivo para revertir o respaldo, no se cambia nada (depurar, reactivar descartando, detalle de la
  cola, correcciones, `billing:cancelar-colas`, `facturacion:reacomodar-pagos`). Una corrección que
  falla por el respaldo queda pendiente, no «ya no aplica».
- Dev: `storage/app/billing/manifiestos` y `storage/app/respaldos{,/facturacion}` quedaron
  `2770 meganet:www-data`.
- Prod tiene la V1.66 con el hueco: antes de usar «Depurar cola» allá, revisar que
  `storage/app/billing/manifiestos` exista y sea escribible por www-data (o esperar la V1.67).

## 2026-10-22 — Etapa de reactivación desde la pantalla (entra a la V1.67)

- Prioridad de Irving: reactivar las prefacturas en prod cuanto antes sin tocar el `.env`.
- `billing_config` += `reactivacion_nivel` (cerrado | baja | normal), `reactivacion_limite_corrida`,
  `reactivacion_limite_diario`, `reactivacion_cambiada_por/at` (migración `2026_10_22_170000`).
  `ReactivacionEnvios::configVigente()` pone lo guardado encima de `billing.reactivacion`; sin nivel
  guardado manda el `.env` como antes (actualizar no cambia nada).
- Cerrado = no sale nada. Baja = todos los clientes con tope (20 por corrida y 100 por día de
  inicio, editables hasta 200/2000). Normal = sin tope de etapa (queda `--limit=100` por corrida
  cada 15 min y la parada por errores); solo se llega desde baja.
- Pantalla: distintivo de la etapa, quién y cuándo la cambió, «Cambiar etapa…» con la palabra ETAPA;
  auditoría «Etapa de reactivación cambiada». Pruebas: 3 nuevas en `EnviosAutomaticosTest` (12).
- Plan de Irving en prod: aplicar V1.67 → revisar «Correcciones pendientes» → depurar la cola →
  abrir la etapa baja → reactivar.

Respuestas dadas para autorizar la V1.67 (datos de dev al 14-oct):
- La prefactura muestra solo su mes (total de esa factura o lo que falta si hay abono), no la
  deuda acumulada; al enviar se revalida (pagada o cancelada = no sale). Un cliente con varios meses
  abiertos puede tener una prefactura por mes en cola.
- Activos con proformas abiertas: 990; con 2+ meses: 71 (3+: 3). Bloqueados: 577; con 2+ meses: 76.
- Respaldo de MySQL al actualizar: primer paso de `remote:deploy` (`backup_db:process`, crítico) en
  `/var/backups/mysql/` del mismo servidor; no hay copia fuera.

## 2026-10-22 — Un solo interruptor (reemplaza Pausar/Reactivar y el control de etapa)

- Pedido de Irving: un interruptor con el estilo de los demás (Bootstrap `form-switch`) que maneje
  juntas la pausa y la etapa. Encendido = `envio_pausado=0` y ritmo «prueba» (20 por corrida, 100 al
  día) o «normal»; apagado = `envio_pausado=1`. No existe «encendido pero cerrado».
- Encender: el interruptor no cambia hasta confirmar; diálogo con lo acumulado (descartar lo de más
  de 48 h por defecto, enviar todo, por lotes), el ritmo y REACTIVAR. Una sola transacción: si el
  archivo para revertir no se guarda, sigue apagado. Cancelar = sigue apagado.
- Apagar: confirmación simple, inmediato; el ritmo queda guardado. «Cambiar ritmo…» solo encendido.
- Rutas `encender`, `apagar`, `ritmo` (se quitaron `pausar`, `reactivar` y `etapa`). Auditoría:
  «Envíos automáticos encendidos/apagados», «Ritmo de envío cambiado».
- La etapa vive en `billing_config.reactivacion_nivel` (cerrado | prueba | normal). El `.env`
  (BILLING_REACTIVACION_ETAPA) solo da el valor inicial en la migración `2026_10_22_170000`:
  «todos» → prueba; cerrado/piloto/zona → cerrado y pausa puesta. Apagado también cierra las otras
  colas de correo (`billing:send-all-emails`).
- Pruebas: `EnviosAutomaticosTest` 13 (encender, cancelar, cambiar ritmo, apagar, sin archivo no
  enciende, sin estado encendido-cerrado, el .env solo inicial). Capturas en PDF adjunto a #571.
- En prod, antes de aplicar: revisar `grep BILLING_REACTIVACION .env`. Si dice `todos`, arranca en
  Prueba; si los envíos estaban sin pausa con piloto/zona, quedan apagados.
