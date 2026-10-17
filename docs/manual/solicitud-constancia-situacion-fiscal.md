# Pedir la Constancia de Situación Fiscal (CSF) — textos y checklist

> **Solo textos (FAC-02, item #507).** No se ha pedido ni recibido documentación de ningún cliente.
> Irving decide cuándo y por dónde usarlos. Hoy MegaISP **no emite CFDI** (eso es la épica FAC-15);
> los datos se guardan en la tarjeta **Datos fiscales** de la ficha del cliente y salen en la
> Prefactura/Factura sin valor fiscal.

## WhatsApp (cliente)

```
Hola, {nombre}. Para preparar tus facturas fiscales necesitamos tu Constancia de Situación Fiscal
(la descargas en sat.gob.mx o en la app SAT Móvil con tu RFC y contraseña).
Envíala a facturacion@meganett.com.mx con tu número de cliente {id} y dinos el uso del CFDI
(si no lo sabes, pregúntale a tu contador). Gracias. — Meganet
```

## Correo (cliente)

**Asunto:** Datos para tu factura fiscal — Meganet (cliente {id})

> Hola, {nombre}:
>
> Para emitir tus facturas fiscales (CFDI) necesitamos los datos tal como aparecen en tu
> **Constancia de Situación Fiscal** del SAT. Puedes descargarla en
> https://www.sat.gob.mx/aplicacion/login/53027/genera-tu-constancia-de-situacion-fiscal o en la app
> SAT Móvil.
>
> Respóndenos a **facturacion@meganett.com.mx** con:
> 1. Tu Constancia de Situación Fiscal (PDF), o estos datos exactos: RFC, nombre o razón social,
>    régimen fiscal y código postal de tu domicilio fiscal.
> 2. El **uso del CFDI** que le darás a la factura (tu contador te lo indica).
> 3. Tu número de cliente: **{id}**.
>
> Usaremos estos datos solo para tu facturación. Gracias,
> Meganet Telecomunicaciones

## Checklist al capturar (tarjeta «Datos fiscales»)

| Dato | Cómo debe quedar | Fuente |
|---|---|---|
| RFC | Igual a la CSF (13 caracteres persona física, 12 persona moral). | CSF |
| Nombre o razón social | **En MAYÚSCULAS, tal como aparece en la CSF**, sin abreviar ni cambiar acentos. ⚠️ Régimen societario («S.A. de C.V.»): las guías del SAT difieren; **pendiente de verificar** con la guía vigente o el contador antes de timbrar. | CSF (infografía SAT) |
| Régimen fiscal | La clave del régimen con el que está inscrito ante el RFC (catálogo c_RegimenFiscal). | CSF |
| C.P. del domicilio fiscal | El de **Datos de ubicación** de la CSF, no el del domicilio donde se instala el servicio. | CSF |
| Uso del CFDI | Lo **define el cliente**; debe ser una clave de c_UsoCFDI **permitida para su régimen**. No elegirlo por él. | Cliente / contador |
| Correo fiscal | Opcional. | Cliente |

Si un dato no coincide con la CSF, el PAC rechaza el timbrado; ante la duda, pedir la CSF en vez de
corregir a mano.

## Canal seguro (propuesta, no existe todavía como flujo)

La CSF trae datos personales (RFC, domicilio fiscal). Propuesta para que Irving decida:

1. **Recomendado:** que el cliente la mande a **facturacion@meganett.com.mx** (buzón ya configurado
   en `config('billing.correo_facturacion')`); quien la capture la registra en «Datos fiscales» y
   **no la guarda en celulares ni en chats personales**.
2. A futuro: subirla desde el **Portal Cliente** (hoy **no existe** esa opción; sería un desarrollo
   aparte con su aviso de privacidad).
3. En mostrador: capturar a la vista del cliente y devolverle el documento.

El aviso de privacidad aplicable lo define Irving (ver FAC-03 y FAC-14).

## Fuentes

- SAT — infografía «Factura electrónica CFDI versión 4.0. Como cliente, ¿qué datos necesito
  proporcionar?» (vigente desde 01/01/2022): RFC, nombre completo, C.P. del domicilio fiscal,
  régimen fiscal y uso del CFDI son obligatorios (art. 29-A CFF); nombre en mayúsculas como en la
  CSF; el uso debe corresponder al régimen; el régimen debe ser el registrado ante el RFC.
  https://www.sat.gob.mx/minisitio/Factura/documentos/Infografia_FacturaElectronica_Datos_Receptor.pdf
- SAT — Anexo 20, guía de llenado del CFDI 4.0 (catálogos c_RegimenFiscal y c_UsoCFDI; versión
  vigente publicada el 16/01/2026 según el portal, **no revisada aquí**):
  http://omawww.sat.gob.mx/tramitesyservicios/Paginas/anexo_20.htm
