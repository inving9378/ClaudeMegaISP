; MegaVoz Fase 3 — registro de llamadas. Habilita el motor de CDR de Asterisk;
; el backend que de verdad escribe (Adaptive ODBC, hacia voip_llamadas) se
; configura aparte en cdr_adaptive_odbc.conf.
;
; unanswered=yes a propósito: una llamada a la cola sin agente disponible
; ("sin_agente" en el dialplan) nunca se contesta, y es justo el caso que el
; registro tiene que poder mostrar — no solo las llamadas atendidas.
[general]
enable = yes
unanswered = yes
