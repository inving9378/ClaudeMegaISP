# Plantillas de configuración de Asterisk

El provisionador las rellena con los valores del servidor y las escribe en `/etc/asterisk/`.

Los marcadores son `{{NOMBRE}}`. Todo lo que varía entre instalaciones está ahí: nada de IPs,
rutas ni credenciales fijas en el texto.

## Nunca se sobrescribe un archivo modificado

Si el destino ya existe **y difiere**, el provisionador escribe su propuesta como `.nuevo` al lado
y avisa. No pisa.

Un provisionador que sobrescribe en silencio la configuración de una central en producción se
lleva por delante ajustes que alguien hizo a mano por una razón — y de los que no queda rastro.

## Qué NO está aquí

**Endpoints, AORs y auths.** Esos viven en la base realtime (`ps_*`), no en archivos. Lo que estas
plantillas configuran es el *cableado*: transporte, ODBC, sorcery y el mapeo de tablas.

**`alembic.ini`.** Se genera aparte, en `/usr/share/megaisp-asterisk/alembic/config.ini`, con
permisos `600`: lleva la contraseña de la base realtime en su cadena de conexión.
