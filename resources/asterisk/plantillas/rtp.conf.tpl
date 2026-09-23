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
stunaddr=
