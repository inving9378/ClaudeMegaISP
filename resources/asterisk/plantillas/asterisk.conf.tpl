[directories](!)
astetcdir => /etc/asterisk
astmoddir => {{LIBDIR}}/asterisk/modules
astvarlibdir => /var/lib/asterisk
astdbdir => /var/lib/asterisk
astkeydir => /var/lib/asterisk
astdatadir => /var/lib/asterisk
astagidir => /var/lib/asterisk/agi-bin
astspooldir => /var/spool/asterisk
astrundir => /var/run/asterisk
astlogdir => /var/log/asterisk
astsbindir => /usr/sbin

[options]
; Nunca root: usuario de sistema dedicado con nologin.
runuser = asterisk
rungroup = asterisk

; SIN esta línea Asterisk ignora los prompts en español y suenan en inglés,
; aunque los sonidos estén instalados. Es el detalle que más se nota en
; producción y el que más fácil se olvida.
defaultlanguage = {{IDIOMA}}

documentation_language = {{IDIOMA}}
