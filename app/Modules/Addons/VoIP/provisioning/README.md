# Provisionamiento de Asterisk

| Archivo | Qué es |
|---|---|
| `provisionar-asterisk.sh` | El que corre. Instala Asterisk en el servidor donde vive MegaISP |
| `referencia/etapa1-instalar-asterisk22.sh` | La receta original, **intacta y no ejecutable**. Ver su README |
| `config/requisitos-voip.php` | El manifiesto: qué versión, de dónde bajarla y con qué hash |

El principio: **la actualización lleva las instrucciones y un sitio de descarga lleva los
archivos**. El script no sabe qué instala ni de dónde: se lo dice el manifiesto, por entorno.

---

## ⚠️ El árbol de Alembic pertenece a la versión instalada

Tras compilar, el script copia `contrib/ast-db-manage` —que vive dentro de las fuentes— a:

```
/usr/share/megaisp-asterisk/alembic
/usr/share/megaisp-asterisk/VERSION-ASTERISK    ← versión de Asterisk instalada
/usr/share/megaisp-asterisk/VERSION-ESQUEMA     ← revisión de Alembic aplicada
```

Hace falta porque el árbol de fuentes **se borra al terminar** (pesa cientos de MB y no debe
quedar en el servidor de nadie), y `ast-db-manage` es lo único que genera el esquema de las
tablas `ps_*`. Sin esa copia, el provisionador se queda sin con qué crear la base realtime.

### Al actualizar Asterisk hay que REEMPLAZARLO, no dejar el viejo

**Las revisiones de esquema entre versiones no son intercambiables.** El árbol copiado
corresponde a la versión que se instaló, y sus migraciones asumen ese punto de partida. Dejar el
de una versión anterior significa que `alembic upgrade head` aplicaría el historial equivocado
sobre una base que ya no le corresponde — y eso no falla ruidosamente: converge a un esquema que
*parece* correcto y difiere en columnas concretas.

Es la misma familia del problema que el módulo ya tuvo una vez: tablas creadas con el esquema
mínimo de Asterisk 18 y remendadas a mano hacia 20.19.0, con la revisión en una tabla no
estándar. El resultado fue que no había camino de migración reproducible entre versiones.

**`VERSION-ESQUEMA` se actualiza junto con el árbol, en la misma operación.** No después, ni
"cuando se pueda": si el árbol es de una versión y el archivo dice otra, la matriz de
compatibilidad de MegaISP lee un dato falso y decide sobre él.

Ese archivo es justamente **lo que MegaISP consulta para su matriz de compatibilidad**: qué
revisión de esquema requiere el Asterisk que hay instalado en este servidor.

---

## El pre-flight de Alembic corre ANTES de compilar

Tener el árbol preservado no basta. Sin el comando `alembic` o sin el driver de Python,
`alembic upgrade head` no corre y el paso de la base realtime falla — **después de media hora de
`make`**, con ese tiempo ya gastado.

Por eso el script verifica la cadena completa en el pre-flight, cuando abortar todavía es barato:

1. **El comando existe** — `alembic` en el PATH, o `python3 -m alembic` de respaldo.
2. **El driver se importa** — `python3 -c "import pymysql"`.
3. **La conexión abre de verdad** — se conecta a la base y cierra. No basta con que el driver
   esté instalado: las credenciales también tienen que servir.

Las dependencias `python3`, `python3-alembic` y `python3-pymysql` se instalan con el resto.

## El `alembic.ini` no se versiona

Se **genera en tiempo de provisión** con las credenciales de este servidor, en
`/usr/share/megaisp-asterisk/alembic/config.ini`, con permisos `600` y propietario `root`.

Lleva la contraseña de la base realtime dentro de su `sqlalchemy.url`, así que **nunca va al
repo**. La contraseña se pasa por entorno y se escribe con `python3 -` leyendo de stdin, no como
argumento de línea de comandos, donde cualquiera con acceso al servidor la vería con un `ps`.

---

## Modo descubrimiento

La revisión de Alembic que corresponde a una versión de Asterisk solo se conoce ejecutándola,
pero el manifiesto tiene que declararla para poder validarla. Se resuelve con una bandera:

| `ASTERISK_MODO_DESCUBRIMIENTO` | `esquema_realtime` | Qué pasa |
|---|---|---|
| `1` | vacío | **Procede.** Ejecuta y reporta la revisión, para fijarla en el manifiesto |
| `0` | vacío | **Aborta.** No hay nada contra qué validar |
| `0` | fijado | **Procede** validando; aborta si no coincide |
| `1` | fijado | **Aborta.** Sería re-descubrir sin querer y perder la validación |

El modo **no se activa solo** al encontrar el campo vacío. En la instalación de un cliente el
manifiesto siempre viene completo, y un descubrimiento accidental allí aceptaría en silencio
cualquier esquema que saliera.

## El log sobrevive al fallo

`/var/log/megaisp/provision-asterisk-<fecha>.log`. El árbol de fuentes se borra siempre —también
cuando la compilación revienta, que es cuando más tienta dejarlo "para revisar"— pero el log pesa
poco y es lo único que explica qué falló. El mensaje de error lo nombra explícitamente.

## El servicio queda instalado y DESHABILITADO

Un Asterisk que empieza a escuchar antes de tener firewall es una invitación al fraude
telefónico. Quien lo arranca es el provisionador, después de escribir la configuración.
