; Rango de puertos RTP. Tiene que coincidir con lo que abra el firewall:
; si el firewall abre menos, las llamadas conectan pero no hay audio — y eso se
; diagnostica mal, porque la señalización funciona.
[general]
rtpstart={{RTP_START}}
rtpend={{RTP_END}}
icesupport=no
stunaddr=
