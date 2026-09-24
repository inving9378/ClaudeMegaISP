; MegaVoz Fase 3 — mapea el motor de CDR a voip_llamadas por la misma conexión
; realtime que ya usan ps_endpoints/queues/etc (ver res_odbc.conf, extconfig.conf).
;
; Los nombres de columna de voip_llamadas coinciden EXACTO con los campos que
; Asterisk ya conoce (uniqueid, linkedid, disposition, billsec…) y con la
; variable de CDR propia que fija el dialplan (`grabacion`, vía
; Set(CDR(grabacion)=…) en DialplanGeneratorService::buildColaExten) —
; cdr_adaptive_odbc empareja por nombre exacto de columna, así que no hace
; falta ninguna línea `alias`.
[{{DB_NAME}}]
table = voip_llamadas
