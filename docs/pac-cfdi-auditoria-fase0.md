# Auditoría Fase 0 — Integración PAC para CFDI 4.0 (item #117)

Fecha: 2026-07-14. Alcance: SOLO LECTURA + diseño técnico, sin timbrar nada ni tocar
la integración real. Responde a las 5 preguntas de auditoría pedidas por Irving
(2026-07-14 08:43) y deja el diseño de la parte segura de Fase 1 (almacenamiento interno).

## 1) ¿Existe ya código de PAC/timbrado/Facturama?

Sí, el contrato y el stub ya están completos y en uso (commit previo, sin fecha en este
audit — ya vivían en `main` antes de esta sesión):

- `app/Services/Finance/Timbrado/TimbradoServiceInterface.php` — contrato:
  `emitirFactura(Payment $payment, ClientFiscalData $fiscalData): array` (retorna
  `uuid, xml_path, pdf_path, timbrado_at, folio_fiscal`), `cancelarFactura(uuid, motivo)`,
  `isDisponible()`, `nombreProveedor()`.
- `app/Services/Finance/Timbrado/NullTimbradoService.php` — implementación nula: todos los
  métodos lanzan `PacNoConfiguradoException` salvo `isDisponible()` (false).
- `app/Services/Finance/Timbrado/PacNoConfiguradoException.php`.
- `app/Providers/AppServiceProvider.php:29-33` — bind actual:
  `TimbradoServiceInterface::class → NullTimbradoService::class`. Sin adaptador real todavía.

No existe ningún cliente HTTP hacia Facturama ni ninguna otra Fintech/PAC. Cero llamadas
salientes hoy — confirmado por `grep -r "facturama.mx\|api.facturama"` sin resultados.

## 2) Campos fiscales en el modelo Cliente

Ya existen, completos, en una tabla dedicada (NO en `clients` directamente):
`app/Models/ClientFiscalData.php` + migración `2026_06_04_960010_create_client_fiscal_data_table.php`.

Tabla `client_fiscal_data` (1:1 con `clients.id`, soft deletes):

| Columna | Tipo | Nota |
|---|---|---|
| `client_id` | FK único | 1 fila por cliente |
| `facturacion_fiscal` | boolean | flag "este cliente sí quiere CFDI" |
| `razon_social` | string(250) | |
| `rfc` | string(13) | validado con regex SAT en `ClientFiscalData::validarRfc()` |
| `codigo_postal_fiscal` | string(5) | validado `validarCodigoPostal()` |
| `regimen_fiscal` | string(4) | clave c_RegimenFiscal — catálogo embebido (9 regímenes ISP-relevantes) |
| `uso_cfdi` | string(4) | clave c_UsoCFDI — catálogo embebido (11 usos) |
| `correo_fiscal` | string(200) | destino del envío de la factura |
| `constancia_path` | string(500) | PDF de constancia de situación fiscal |

**Lo que FALTA para poder timbrar de verdad:**
- Catálogos SAT completos (los embebidos son un subconjunto manual "para ISP"; Facturama
  valida contra el catálogo oficial completo — un régimen/uso fuera del subconjunto lo
  rechazaría aunque sea válido para el SAT). Evaluar en Fase 1 si basta el subconjunto o si
  se necesita el catálogo completo.
- Ningún dato del **emisor** (Meganet): razón social, RFC, régimen fiscal y CSD (Certificado
  de Sello Digital: `.cer`/`.key` + contraseña) del RFC único bajo el cual se timbrará
  (decisión ya tomada: "Camino 1: Facturama, RFC único bajo Meganet"). Esto es configuración
  de **empresa**, no de cliente — no hay tabla ni `.env` para ello todavía.
- Serie/folio interno si se quiere control propio (Facturama puede asignar folio, pero
  conviene una serie propia para trazabilidad, ej. `A-000123`).
- Existe el `ClientFiscalDataController` (`app/Modules/Core/Clientes/Controllers/`) con
  `show`/`upsert` ya funcionando (permisos `facturacion.ver` / `facturacion.fiscal.editar`),
  validación de RFC/CP, y ya expone `pac_disponible`/`pac_proveedor` desde el
  `TimbradoServiceInterface` inyectado — es decir, la UI ya está preparada para reaccionar
  cuando haya un PAC real conectado, sin cambios adicionales en ese controller.

## 3) Manejo actual de secretos/credenciales

- **Login de usuarios (`users.password` / `client_main_information.password`)**: base64,
  documentado como legado inseguro específico de auth (ver memoria
  `project_auth_bcrypt_migration` — migración a bcrypt en curso vía `PasswordService`). Este
  patrón es **exclusivo de contraseñas de login**, no es el patrón general de secretos del
  proyecto.
- **API keys de proveedores externos (patrón real y vigente)**: viven en `.env`, se leen vía
  `config/services.php` con `env(...)` — ej. `CLAUDE_API_KEY`/`CLAUDE_MODEL` (líneas 46-48).
  No hay vault ni cifrado adicional; `.env` está gitignored y el proyecto ya tiene la regla
  "secretos SOLO en `.env`, nunca en docs" (ver CLAUDE.md, sección Convenciones).
- **Nada de esto sirve tal cual para el CSD de Facturama**: el CSD no es una sola API key de
  texto sino **2 archivos binarios** (`.cer` público + `.key` privado cifrado) + una
  contraseña. Guardarlos en `.env` no es viable (son binarios). Diseño propuesto para Fase 1:
  - API key/usuario de Facturama (sandbox y prod) → `.env` (`FACTURAMA_*`), igual que Claude.
  - Archivos `.cer`/`.key` del CSD → disco privado (`storage/app/private/...`, mismo patrón ya
    usado por comprobantes de pago en Conciliación WhatsApp — permisos restringidos,
    `Storage::disk('local')`), **nunca en git, nunca en `public/`**.
  - Contraseña del `.key` → `.env` (`FACTURAMA_CSD_PASSWORD`), no en BD ni en el archivo.
  - Nota: en la práctica con Facturama casi nunca se sube el CSD directo — Facturama ofrece
    "Timbrado sin CSD propio" (usa su propio certificado) o "Timbrado con CSD" (el cliente
    sube su `.cer`/`.key`). Para "RFC único bajo Meganet" probablemente baste el modo con CSD
    de Meganet cargado una sola vez en el dashboard de Facturama (no por código) — a
    confirmar en Fase 1 al dar de alta la cuenta.

## 4) ¿Existe modelo de factura/CFDI? ¿Relación con ClientInvoice y pagos?

**No existe ningún modelo/tabla de CFDI todavía.** Lo que sí existe:

- `App\Modules\Core\Clientes\Models\ClientInvoice` (alias legacy `App\Models\ClientInvoice`)
  — factura/proforma interna del sistema (estado, total, `is_proforma`, tipo servicio/recargo
  moroso/convenio). **Cero columnas fiscales** (sin `uuid`, `xml_path`, `sello`, etc.).
- `App\Models\Payment` (tabla `payments`) — pago del cliente, polimórfico
  (`paymentable_id/type`), sin columnas fiscales tampoco.
- El contrato `TimbradoServiceInterface::emitirFactura()` ya decidió (antes de esta sesión)
  que el CFDI se emite **a partir de un `Payment`**, no de un `ClientInvoice` — timbrar el
  pago recibido (comprobante de ingreso), no la proforma. Coherente con cómo trabajan la
  mayoría de los ISP mexicanos con Facturama (CFDI de ingreso al momento del pago).
- **Diseño Fase 1 (parte segura, sin PAC real)**: tabla nueva `client_cfdi_invoices`,
  1 fila por CFDI emitido, FK a `payments.id` (1:1, un pago = un CFDI) y a `client_id` +
  `client_fiscal_data_id` (snapshot de los datos fiscales al momento de timbrar — si el
  cliente cambia su RFC después, el CFDI histórico no debe mutar). Incluye el estado del
  ciclo de vida (`pendiente → timbrada → cancelada` / `error`) para poder reintentar sin
  perder rastro. Ver migración `2026_07_14_190000_create_client_cfdi_invoices_table.php`
  creada en esta misma sesión (Fase 1 parcial, ver sección "Qué se ejecutó").

## 5) Config/env para PAC — separación sandbox vs producción

No existe ningún env var de PAC hoy (`grep FACTURAMA\|PAC_\|TIMBRADO` en `.env*` y
`config/*.php` → sin resultados). Propuesta para cuando se dé de alta la cuenta Facturama
(Fase 1, requiere que Irving cree la cuenta sandbox — no lo puede hacer el circuito):

```
FACTURAMA_SANDBOX=true            # true=sandbox (apisandbox.facturama.mx), false=prod
FACTURAMA_API_USER=
FACTURAMA_API_PASSWORD=
FACTURAMA_CSD_PASSWORD=           # si se sube CSD propio vía API en vez del dashboard
```

Patrón calcado del checklist de deploy ya existente (`OPENPAY_SANDBOX`,
`DOMICILIACION_COBRO_LIVE_ENABLED`): **kill-switch explícito por env, default seguro, nunca
`true`/producción sin decisión explícita de Irving en el `.env` real**. El binding de
`TimbradoServiceInterface` en `AppServiceProvider` debe seguir apuntando a
`NullTimbradoService` hasta que exista un adaptador real y probado — cambiarlo es Fase 2,
no esta sesión.

## Qué se ejecutó en esta pasada (item #117, Fase 0 + parte segura de Fase 1)

1. Esta auditoría (solo lectura, sin tocar la integración).
2. La parte de Fase 1 explícitamente pre-aprobada y sin riesgo — **"crear únicamente la base
   interna para almacenar CFDI"**: migración + modelo `ClientCfdiInvoice` (tabla vacía, sin
   wiring a ningún servicio, sin llamadas externas, sin credenciales). Detalle abajo.

**Lo que NO se hizo (fuera de alcance de esta pasada, requiere a Irving):**
- "Preparar Facturama en sandbox" (la otra mitad de Fase 1) — requiere que Irving dé de alta
  la cuenta sandbox en facturama.mx (usuario/password de API) y decida el modo de CSD
  (propio subido vs certificado de Facturama). Sin esas credenciales no hay adaptador real
  que escribir con confianza — un adaptador escrito "a ciegas" contra la doc pública sin
  poder probarlo contra el sandbox real es justo el tipo de riesgo fiscal/dinero que este
  circuito debe evitar construir sin verificación.
- Emisor (datos fiscales de Meganet — RFC único, razón social, régimen, CSD): tabla/config
  nueva, pendiente de Fase 1 también, bloqueada por la misma razón (requiere que Irving
  aporte los datos reales del RFC bajo el que se va a timbrar).
- Fase 2 (activación real, cambiar el binding de `NullTimbradoService`): sigue bloqueada
  hasta aprobación explícita nueva, tal como ya se acordó.

## Siguiente paso propuesto

Cuando Irving tenga la cuenta sandbox de Facturama (usuario/password de API) y decida cómo
maneja el CSD del RFC único de Meganet, un próximo item de Fase 1 puede:
1. Añadir `FACTURAMA_*` a `.env` (Irving, manual — el circuito no toca `.env`).
2. Escribir `FacturamaTimbradoService implements TimbradoServiceInterface`, sin cambiar el
   binding de `AppServiceProvider` hasta que esté probado contra el sandbox real.
3. Probar `emitirFactura()`/`cancelarFactura()` contra sandbox con un pago de prueba.
4. Recién ahí, Fase 2 (activación) con aprobación explícita nueva de Irving.
