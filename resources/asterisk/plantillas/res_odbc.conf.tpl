; Conexión ODBC a la base realtime. La contraseña la inyecta el provisionador
; desde el .env de MegaISP; este archivo termina con permisos restringidos.
[{{DB_NAME}}]
enabled => yes
dsn => {{ODBC_DSN}}
username => {{DB_USER}}
password => {{DB_PASSWORD}}
pre-connect => yes
