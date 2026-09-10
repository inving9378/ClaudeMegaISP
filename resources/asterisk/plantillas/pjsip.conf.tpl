; Configuración plana MÍNIMA de PJSIP: solo el transporte.
; Endpoints, AORs y auths viven en la base realtime, no aquí.
;
; NO se incluye ningún archivo del árbol de la aplicación web: la configuración
; que Asterisk lee no debe depender de dónde esté instalado MegaISP.

[transport-udp]
type=transport
protocol=udp
bind={{BIND_SIP}}
{{#EXTERNAL_MEDIA}}external_media_address={{EXTERNAL_MEDIA}}{{/EXTERNAL_MEDIA}}
{{#EXTERNAL_SIGNALING}}external_signaling_address={{EXTERNAL_SIGNALING}}{{/EXTERNAL_SIGNALING}}
{{#LOCAL_NET}}local_net={{LOCAL_NET}}{{/LOCAL_NET}}
