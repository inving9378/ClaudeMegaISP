; AMI. Por omisión escucha SOLO en loopback: MegaISP corre en la misma máquina.
; Si la telefonía se separa a otra VM, hay que abrirlo y ajustar el permit —
; y ese es un parámetro que el provisionador debe preguntar, no asumir.
[general]
enabled = yes
port = {{AMI_PORT}}
bindaddr = {{AMI_BIND}}
displayconnects = no

[{{AMI_USER}}]
secret = {{AMI_SECRET}}
deny = 0.0.0.0/0.0.0.0
permit = {{AMI_PERMIT}}
read = system,call,log,verbose,agent,user,config,command,dtmf,reporting,cdr,dialplan
write = system,call,agent,user,config,command,reporting,originate
