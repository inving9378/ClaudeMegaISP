<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}td{padding:4px 8px;border-bottom:1px solid #ccc}</style></head><body>
<h2>Constancia de evidencia de firma electrónica</h2>
<table>
<tr><td>Identidad declarada</td><td>{{ $ev->identidad_declarada }}</td></tr>
<tr><td>Correo</td><td>{{ $ev->correo }}</td></tr>
<tr><td>Método de autenticación</td><td>{{ $ev->metodo_autenticacion }}</td></tr>
<tr><td>Consentimiento</td><td>{{ $ev->consentimiento ? 'Sí' : 'No' }}</td></tr>
<tr><td>Fecha y zona horaria</td><td>{{ $ev->firmado_at }} ({{ $ev->zona_horaria }})</td></tr>
<tr><td>ID de transacción</td><td>{{ $ev->id_transaccion }}</td></tr>
<tr><td>SHA-256 documento original</td><td>{{ $ev->sha256_original }}</td></tr>
<tr><td>Versión</td><td>{{ $ev->version }}</td></tr>
</table>
<p>Esta constancia resume la evidencia registrada por el sistema; no constituye por sí misma una certificación ni conservación conforme a NOM-151-SCFI-2016.</p>
</body></html>
