; ═══════════════════════════════════════════════════════════════════════════
; Plan de marcado — generado por el provisionador de MegaISP
; ═══════════════════════════════════════════════════════════════════════════
;
; Sin este archivo las extensiones se registran y NO pueden llamarse entre sí.
; Cada extensión se siembra con `contexto = from-internal` (§8 del item), y ese
; contexto tiene que existir en algún lado: no lo trae Asterisk, y el generador
; de grupos solo produce los contextos de grupos, troncales y el restringido.
;
; Lo que va aquí es el mínimo: extensión a extensión. Los grupos de timbrado, el
; ruteo entrante de cada troncal y el contexto restringido los genera MegaISP en
; {{GENERADOS_DIR}} cada vez que cambian, y entran por el #include del final.

[general]
static = yes
; No se permite que Asterisk reescriba este archivo desde el CLI: lo genera el
; provisionador, y un `dialplan save` lo dejaría fuera de sincronía con MegaISP
; sin que quede rastro de quién lo cambió.
writeprotect = yes
clearglobalvars = no

[globals]

; ─── Llamada entre extensiones ─────────────────────────────────────────────
;
; El endpoint de PJSIP se llama igual que el número de la extensión, así que el
; destino sale del propio ${EXTEN} y este contexto no necesita regenerarse cada
; vez que se da de alta una extensión nueva.
[from-internal]

; Internos de 4 dígitos — el plan de numeración de MegaISP vive en 1000-1999.
exten = _[1-9]XXX,1,NoOp(Interno: ${CALLERID(num)} -> ${EXTEN})
 same = n,Set(CDR(userfield)=interno)
 same = n,Dial(PJSIP/${EXTEN},30)
 same = n,Hangup()

; Internos de 3 dígitos — instalaciones con plan corto.
exten = _[1-9]XX,1,NoOp(Interno: ${CALLERID(num)} -> ${EXTEN})
 same = n,Set(CDR(userfield)=interno)
 same = n,Dial(PJSIP/${EXTEN},30)
 same = n,Hangup()

; ─── Servicios del sistema ─────────────────────────────────────────────────
;
; Viven en 1900-1999, el rango que el plan de numeración marca como protegido
; justamente para esto: ahí no se dan de alta extensiones de persona, así que un
; servicio no le puede quitar el número a nadie.

; Eco: devuelve lo que entra. Es la forma de comprobar que el audio va y viene
; con UN solo teléfono, sin depender de que haya alguien del otro lado.
exten = 1999,1,NoOp(Prueba de eco desde ${CALLERID(num)})
 same = n,Answer()
 same = n,Playback(demo-echotest)
 same = n,Echo()
 same = n,Hangup()

; Dice el número desde el que se llama. Sirve para confirmar que el teléfono
; quedó registrado con la extensión que se creía.
exten = 1998,1,NoOp(Quién soy: ${CALLERID(num)})
 same = n,Answer()
 same = n,SayDigits(${CALLERID(num)})
 same = n,Hangup()

exten = i,1,Hangup()
exten = t,1,Hangup()

; ─── Lo que genera MegaISP ─────────────────────────────────────────────────
;
; Grupos de timbrado, ruteo entrante por troncal y contexto restringido. El
; archivo lo crea el provisionador —vacío si aún no hay nada que generar— para
; que este #include nunca apunte a algo que no existe.
#include "{{GENERADOS_DIR}}/megaisp_dialplan.conf"
