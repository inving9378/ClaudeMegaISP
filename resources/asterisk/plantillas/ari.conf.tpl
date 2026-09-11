; ARI. Las credenciales las genera el provisionador y quedan registradas en la
; tabla de estado: quién y cuándo las generó. El valor no se escribe en el log.
[general]
enabled = yes
pretty = no

[{{ARI_USER}}]
type = user
read_only = no
password = {{ARI_PASSWORD}}
