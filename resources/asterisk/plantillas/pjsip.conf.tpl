; Configuración plana MÍNIMA de PJSIP: solo el transporte.
; Endpoints, AORs y auths viven en la base realtime, no aquí.
;
; NO se incluye ningún archivo del árbol de la aplicación web: la configuración
; que Asterisk lee no debe depender de dónde esté instalado MegaISP.

[{{TRANSPORTE}}]
type=transport
protocol=udp
bind={{BIND_SIP}}
{{#EXTERNAL_MEDIA}}external_media_address={{EXTERNAL_MEDIA}}{{/EXTERNAL_MEDIA}}
{{#EXTERNAL_SIGNALING}}external_signaling_address={{EXTERNAL_SIGNALING}}{{/EXTERNAL_SIGNALING}}
{{#LOCAL_NET}}local_net={{LOCAL_NET}}{{/LOCAL_NET}}

; MegaVoz Fase 2 — mini-teléfono WebRTC. La señalización viaja por el mismo
; servidor HTTP que ya usa ARI (http.conf, loopback:{{ARI_PORT}}); este objeto
; solo declara el transporte para que los endpoints WebRTC lo referencien —
; res_pjsip_transport_websocket registra la ruta /ws sola, sin bind propio.
; Lo que de verdad expone esto a internet es nginx, vía proxy wss:// -> /ws
; sobre el mismo dominio y certificado del sitio (sin puerto nuevo que abrir).
[transport-wss]
type=transport
protocol=wss
bind=0.0.0.0
