; Mapeo de las tablas de PJSIP a la base realtime.
; El esquema de estas tablas lo genera Alembic, NUNCA una migración de MegaISP.
[settings]
ps_endpoints => odbc,{{DB_NAME}},ps_endpoints
ps_auths => odbc,{{DB_NAME}},ps_auths
ps_aors => odbc,{{DB_NAME}},ps_aors
ps_endpoint_id_ips => odbc,{{DB_NAME}},ps_endpoint_id_ips
ps_registrations => odbc,{{DB_NAME}},ps_registrations
ps_contacts => odbc,{{DB_NAME}},ps_contacts
ps_domain_aliases => odbc,{{DB_NAME}},ps_domain_aliases
