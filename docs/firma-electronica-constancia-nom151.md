# Firma electrónica — constancia de evidencia (item #970)

## Qué hace
`FirmaConstanciaService::generar($evidencia, $pdfOriginalAbs)` genera un PDF de constancia con los datos de
`firma_evidencias` (identidad, correo, método, fecha/zona, id de transacción, sha256 original, versión), lo une
al documento con `pdfunite` y guarda el sha256 del PDF unido en `sha256_firmado` (write-once: la tabla es
inmutable; solo se escribe mientras la columna esté NULL). Archivos en `storage/app/private/firma-electronica/constancias/`.

## Activar un proveedor real
Implementar `Contracts/FirmaProviderInterface` (referencia: `Drivers/SandboxFirmaDriver`), enlazarlo en
`ModuleServiceProvider` y poner credenciales/secreto de webhook **solo en `.env`** (`<API_KEY>`, `<WEBHOOK_SECRET>`).
El webhook `/webhooks/firma-electronica` valida firma HMAC.

## Marco legal (orientativo, no es asesoría jurídica)
- **Código de Comercio (arts. 89–114):** reconoce el mensaje de datos y la firma electrónica; la firma electrónica
  simple/avanzada tiene valor probatorio según su fiabilidad (atribuibilidad al firmante, control exclusivo,
  detección de alteraciones).
- **NOM-151-SCFI-2016:** requisitos para la conservación de mensajes de datos (típicamente con constancia de un
  prestador acreditado). Esta implementación **no** la satisface por sí sola.

**Aviso:** el sistema reúne evidencia (hashes, fecha, identidad, método); **no afirma certificación ni conservación
NOM-151**. Para ello se requiere un PSC acreditado.

## Rutas/permisos
Permisos `firma-electronica.solicitudes.view|manage` ya existen. Aún **no hay UI admin**, por lo que no se
declaran rutas en `config/route_permission.php`; hacerlo cuando se agregue esa UI.
