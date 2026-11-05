# Usuario definitivo del monitoreo en los MikroTik (item #1006, épica #988)

**Lo ejecuta Irving a mano en cada router. El sistema NO toca routers ni credenciales.**
Este documento solo deja el script y el checklist listos. Decisiones de Irving (3-nov-2026):
grupo mínimo `read,api,test`; Allowed Address = solo IP del servidor (/32); piloto primero
y luego el resto con el mismo `.rsc`; el provisional se retira tras ~1 semana de monitoreo OK.

Placeholders (no escribir valores reales aquí, el repo es público):
`<IP_SERVIDOR_MONITOREO>` = IP del servidor MegaISP; `<PASSWORD>` = contraseña nueva
(solo en `.env`/credenciales cifradas del sistema).

## Script `.rsc` (revisar antes de pegar)

```
/user group add name=monitoreo policy=read,api,test comment="MegaISP monitoreo (item 1006)"
/user add name=megaisp-mon group=monitoreo password="<PASSWORD>" address=<IP_SERVIDOR_MONITOREO>/32 comment="MegaISP monitoreo definitivo"
```

Por qué solo `read,api,test`: leer interfaces (read), conectarse por API (api) y `/ping` (test).
El grupo por defecto `read` además permite reboot, password, sniff, sensitive, romon, rest-api,
telnet, ssh, winbox, web y local, que el monitoreo no necesita.

## Checklist

1. Elegir un router piloto; pegar el script.
2. Verificar: `/user print detail where name=megaisp-mon` → `address` NO vacío, grupo `monitoreo`.
3. Revisar también `/ip service print` (api) y el firewall: la restricción de origen debe estar
   además en el usuario (en la prueba del router piloto el campo salió vacío = cualquier origen).
4. Probar desde el servidor que el monitoreo lee interfaces y hace ping con el usuario nuevo;
   probar que desde otra IP el login falla.
5. Replicar al resto con el mismo script.
6. Tras ~1 semana con monitoreo OK en todos, deshabilitar el usuario provisional
   (`/user disable <provisional>`); borrarlo después si no hizo falta volver atrás.
7. Guardar la contraseña solo en `.env`/credenciales cifradas; nunca en docs ni commits.

Relacionado: #998 (API-SSL).
