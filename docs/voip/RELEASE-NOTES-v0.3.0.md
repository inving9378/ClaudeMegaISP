# VoIP v0.3.0

**Borrador de notas para la GitHub Release.** Irving copia y pega este contenido directo al
publicar la Release a mano (repositorio privado, notas en español). El circuito solo deja
el texto listo — no crea la Release ni hace push del tag.

---

## Qué trae esta versión

Esta versión instala y configura Asterisk (la central telefónica) automáticamente, sin
necesidad de tocar nada a mano en el servidor:

- Instala y compila la central telefónica, verificando que el instalador descargado sea el
  correcto antes de usarlo.
- Crea el plan de numeración: 9 rangos de extensiones y 8 perfiles distintos, para que cada
  departamento (técnicos de campo, mostrador, etc.) tenga el comportamiento que le
  corresponde.
- Da de alta las 30 extensiones de arranque, una por cada puesto, ya listas para usarse.
- Genera y guarda las claves de acceso de la central de forma automática y segura.
- Deja el servicio listo para recibir y hacer llamadas de inmediato — verificado con una
  llamada real, con audio en ambos sentidos.
- Se puede volver a correr sobre un servidor ya configurado sin romper nada ni duplicar
  extensiones.

## Documentación

El procedimiento completo (qué hace cada paso, cuánto tarda, qué se revisa al final y qué
hacer si algo falla) queda documentado en `docs/voip/RUNBOOK-fase3.md`, dentro del
repositorio.

## Alcance

Esta versión prepara el servidor de telefonía. No incluye la aplicación en producción —
ese paso lo hace Irving manualmente, siguiendo el runbook, cuando decida hacerlo.
