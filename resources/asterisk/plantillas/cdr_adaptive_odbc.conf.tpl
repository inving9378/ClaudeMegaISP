; MegaVoz Fase 3 — mapea el motor de CDR a voip_llamadas por la misma conexión
; realtime que ya usan ps_endpoints/queues/etc (ver res_odbc.conf, extconfig.conf).
;
; La sección es un identificador propio de ESTE archivo, no el nombre de la
; clase ODBC — cdr_adaptive_odbc exige el parámetro `connection` explícito
; (que sí apunta a la clase de res_odbc.conf); sin él falla en silencio con
; «No connection parameter found… Skipping.» y ningún backend queda
; registrado de verdad, aunque `cdr show status` siga listando "Adaptive
; ODBC" (el módulo se carga bien, solo la tabla no).
;
; Los nombres de columna de voip_llamadas coinciden EXACTO con los campos que
; Asterisk ya conoce (uniqueid, linkedid, disposition, billsec…) y con la
; variable de CDR propia que fija el dialplan (`grabacion`, vía
; Set(CDR(grabacion)=…) en DialplanGeneratorService::buildColaExten) —
; cdr_adaptive_odbc empareja por nombre exacto de columna, así que no hace
; falta ninguna línea `alias`.
[voip_llamadas]
connection = {{DB_NAME}}
table = voip_llamadas
