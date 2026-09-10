# Receta de referencia — `etapa1-instalar-asterisk22.sh`

Este script es **la receta original** con la que se iba a instalar Asterisk 22.11.0 en el servidor
de producción de Meganet. **Nunca se ejecutó** —producción está limpia— pero contiene las decisiones
de compilación ya tomadas, y por eso se versiona: es la fuente de la que deriva el provisionador.

```
sha256  d1617dfb65e4a8b8b03d09d0e379a425c708291a35473b6222c1de8282455a2d
        9678 bytes · 227 líneas · verificado al recibirlo el 2026-09-10
```

## No se ejecuta. Se conserva tal cual.

**Este archivo no se modifica.** Es un documento histórico: la receta como fue escrita, con su
hash verificable. El que corre es `../provisionar-asterisk.sh`, derivado de él.

Conservarlo intacto permite responder más adelante *"¿de dónde salió esta decisión de
compilación?"* sin depender de la memoria de nadie. Si se le aplicaran los ajustes encima, el hash
dejaría de coincidir y se perdería la referencia.

## Qué decisiones aporta

- `--with-pjproject-bundled` y `--with-jansson-bundled`: PJSIP necesita una pjproject compilada
  específicamente para Asterisk; la del sistema no sirve.
- `BUILD_NATIVE` desactivado: es una VM QEMU, y compilar con instrucciones nativas del host puede
  reventar si la VM migra a otro nodo del clúster.
- Sonidos en **alaw** (ES + EN de respaldo + MOH), y descarte explícito de las 24 variantes
  restantes: en GSM habría que transcodificar cada prompt en tiempo real.
- Dependencias con lista explícita, **sin `install_prereq`**, que arrastra de más.
- Usuario de sistema `asterisk` con `nologin`, y `runuser`/`rungroup` fijados en `asterisk.conf`.
- Unit de systemd endurecida, instalada **y deliberadamente deshabilitada**: el servicio no debe
  escuchar antes de que existan firewall y fail2ban.
- Menuselect estricto sobre los módulos que el diseño del conmutador exige.

## Los seis ajustes que el provisionador le aplica

| # | Qué corrige | Por qué |
|---|---|---|
| 1 | Fija `defaultlanguage = es` en `asterisk.conf` | El script instala los prompts en español pero sin esa línea Asterisk los ignora y **suenan en inglés** |
| 2 | Borra `/usr/src/asterisk-*` al terminar | El original solo limpia **antes** de extraer: al acabar deja fuentes y compilador en el servidor destino |
| 3 | Deriva `--libdir` | Está fijo a `x86_64-linux-gnu`, y además repetido en cinco puntos más de la validación |
| 4 | **Aborta** si hay menos de 3 GB libres | El original solo imprime `df -h`: informa y sigue, aunque no quepa |
| 5 | Toma el sha256 del manifiesto | Está embebido como `SHA_ESPERADO`; en dos lugares acabaría divergiendo del manifiesto |
| 6 | Parametriza la cabecera | Trae hostname, la IP `192.168.105.108` y la referencia al snapshot de esa instalación concreta; el provisionador corre en cualquier servidor |

Además, el provisionador **descarga** el tarball (el original lo espera ya presente junto al script).
