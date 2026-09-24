; MegaVoz Fase 3 — cola "Atención a Clientes".
;
; Configuración plana MÍNIMA: cada cola y sus miembros viven en realtime
; (tablas queues/queue_members, mapeadas en extconfig.conf) — este archivo
; solo trae el general que Asterisk necesita para arrancar el módulo.
[general]
persistentmembers = yes
autofill = yes
