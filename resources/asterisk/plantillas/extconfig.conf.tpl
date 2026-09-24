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

; MegaVoz Fase 3 — cola "Atención a Clientes" (app_queue). Sin estas 3 líneas
; las tablas queues/queue_members/queue_rules existen con el esquema correcto
; pero Asterisk nunca las lee — mismo gap que tenían las registraciones de
; troncal esta mañana (sorcery.conf no gestiona colas, solo PJSIP; para
; app_queue el único mecanismo es realtime vía extconfig, igual que aquí).
queues => odbc,{{DB_NAME}},queues
queue_members => odbc,{{DB_NAME}},queue_members
queue_rules => odbc,{{DB_NAME}},queue_rules
