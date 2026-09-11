# Changelog — Módulo VoIP

Historial de versiones del módulo de telefonía (VoIP) de MegaISP, en lenguaje claro para
cualquiera que quiera saber "qué trae esta versión" sin necesitar conocimientos técnicos.

Este changelog es propio del módulo VoIP y usa su propio número de versión
(`v0.1.0`, `v0.2.0`, `v0.3.0`, …), independiente de las versiones generales de MegaISP
(`V1.x`) que se ven en `/releases`.

---

## v0.3.0 — 2026-09-11

**Esta versión instala y configura Asterisk (la central telefónica) automáticamente.**

Antes, poner a funcionar la telefonía en un servidor nuevo requería instalar y configurar
Asterisk a mano, paso por paso. Con esta versión, MegaISP lo hace solo:

- **Instala y compila Asterisk** en el servidor, sin intervención manual, verificando que
  el archivo descargado sea el correcto antes de instalarlo.
- **Crea el plan de numeración**: 9 rangos de extensiones y 8 perfiles distintos (por
  ejemplo, un perfil para técnicos de campo, otro para mostrador, etc.), para que cada
  departamento tenga extensiones con el comportamiento que le corresponde.
- **Da de alta 30 extensiones**, una por cada puesto de los distintos departamentos, ya
  listas para usarse — no hace falta crearlas una por una.
- **Genera las claves de acceso** de la central de forma automática y las guarda de forma
  segura, sin que nadie tenga que inventarlas ni copiarlas a mano.
- **Deja el servicio listo para recibir y hacer llamadas de inmediato**: al terminar, ya se
  puede marcar entre extensiones y se escucha con claridad en ambos sentidos (se probó con
  una llamada real, no solo revisando que "el servicio esté prendido").
- Si se vuelve a correr el proceso sobre un servidor que ya quedó configurado, **no rompe
  nada ni duplica extensiones** — reconoce lo que ya está hecho y solo completa lo que
  falte.

Se documentó también el procedimiento paso a paso (`docs/voip/RUNBOOK-fase3.md`) para que,
cuando se aplique esta versión en un servidor real, se sepa exactamente qué esperar: cuánto
tarda, qué se revisa al final y qué hacer si algo no sale como se espera.

**Nota:** esta versión queda preparada en el repositorio (tag local `v0.3.0`); publicarla
como versión oficial en GitHub es un paso aparte que hace Irving manualmente.
