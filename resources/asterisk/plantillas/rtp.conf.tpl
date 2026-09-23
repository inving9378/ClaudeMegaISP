; Rango de puertos RTP. Tiene que coincidir con lo que abra el firewall:
; si el firewall abre menos, las llamadas conectan pero no hay audio — y eso se
; diagnostica mal, porque la señalización funciona.
[general]
rtpstart={{RTP_START}}
rtpend={{RTP_END}}
; MegaVoz Fase 2 (mini-teléfono WebRTC): sin ICE, la llamada conecta (la
; señalización SIP no lo necesita) pero el audio nunca se negocia — WebRTC
; exige ICE siempre, no es opcional del lado del navegador. Antes de la Fase 2
; nada de esto usaba WebRTC y quedaba en "no" a propósito.
icesupport=yes
; stunaddr en blanco dejaba en el log de arranque: "ERROR res_rtp_asterisk.c:
; Failed to setup recurring DNS resolution of stunaddr ''" — un STUN público
; real evita ese fallo de inicialización del lado de ICE.
stunaddr=stun.l.google.com:19302
