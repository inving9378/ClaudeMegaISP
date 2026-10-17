# Borrador de versión — Facturación, lote 3 (SIN número oficial, sin publicar)

> Borrador para Irving. No hay tag, push ni release. Se suma a los lotes 1 y 2.

- **Incremento desde:** `cf863d6b` (cierre del lote 2) hasta `d99d6060`.
- **Contenido:** FAC-07 (#514), FAC-08 (#515), FAC-09 (#516). **Nada se activa ni se ejecuta.**

## Para usuarios
Sin cambios visibles: son herramientas y compuertas para Irving (cancelar colas viejas, reactivar
envíos por etapas, documento por WhatsApp), todas apagadas.

## Commits
| Item | Commits | Merge |
|---|---|---|
| FAC-07 #514 | `d9f07554` `97a5dc92` `9fc251e2` | `deb88456` |
| FAC-08 #515 | `af8a3c36` `c6a623ab` + bitácora | `b8691d3f` |
| FAC-09 #516 | `4b3705b5` `fb3b7171` + prueba + bitácora | `d99d6060` |

## Comandos nuevos (todos seguros por defecto)
- `billing:cancelar-colas` — preparar manifiesto / dry-run / ejecutar con sha256 / revertir.
- `billing:reactivacion-estado` — estado de la etapa + SPF/DMARC (DKIM con `--selector`).

## Migraciones (aditivas)
1. `2026_10_16_220000_agrega_clave_a_billing_documento_evidencias` — columna `clave` nullable +
   índice (idempotencia por documento lógico).

## Configuración (todas apagadas por defecto; no definir en prod hasta decidir)
- `billing.reactivacion.*` — `BILLING_REACTIVACION_ETAPA` (default **cerrado**), `…_CLIENTES`,
  `…_COLONIAS`, `…_MUNICIPIOS`, `…_LIMITE_CORRIDA` (20), `…_LIMITE_DIARIO` (100), `…_MAX_ERRORES` (3),
  `…_WHATSAPP` (false).
- `billing.whatsapp_documento.*` — `BILLING_WHATSAPP_DOCUMENTO` (false), `…_LINEA`.
- ⚠️ **Efecto al desplegar:** con la etapa en «cerrado», aunque Irving quite la pausa no saldrá nada
  automático hasta fijar la etapa (piloto/zona/todos). Es intencional (segunda compuerta). El canal
  legacy `app:send-all-emails-command` (no programado; último envío 2025-05) queda bajo la misma
  compuerta.

## Validación hecha (dev)
- FAC-07: escenario completo con filas sintéticas en transacción revertida (manifiesto, dry-run,
  sha256, omisión de filas cambiadas, lotes, idempotencia, reversión acotada, otra base rechazada).
- FAC-08: unitarias 7/7; dry-run en dev con etapa piloto en el proceso; DNS real consultado.
- FAC-09: unitaria 3/3; flujo completo con Meta simulado (sin salida externa) en transacción revertida.
- ⚠️ Pruebas con BD pendientes de correr mientras `megaisp_test` siga a medio migrar (#529).

## Checklist en PROD (al desplegar)
- [ ] `php artisan migrate` (columna `clave`).
- [ ] `php artisan billing:reactivacion-estado` → etapa cerrado, pausa puesta.
- [ ] `grep -E "BILLING_REACTIVACION|BILLING_WHATSAPP_DOCUMENTO" .env` → nada.
- [ ] `php artisan billing:cancelar-colas --help` disponible (su ejecución es el procedimiento de #514).

## Rollback
Revertir los merges (no destructivo). La columna `clave` puede quedarse.

## Decisiones de Irving
#514 (fecha límite y ejecución), #515 (etapas, ids piloto, zona, DKIM), #517 (línea Meta,
plantillas, activación de WhatsApp), #529 (reconstruir `megaisp_test`).
