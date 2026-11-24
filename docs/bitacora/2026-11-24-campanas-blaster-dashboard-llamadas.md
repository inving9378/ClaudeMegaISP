# 2026-11-24 — Campañas/Blaster: Dashboard de llamadas y grabación de todas las llamadas

## Pedido
Irving: un dashboard donde se vean todas las llamadas que se hicieron, grabarlas y poder escucharlas. En VoIP de
prod las llamadas no salen para reproducirlas, como si no se grabaran. Pestaña nueva en Campañas/Blaster, criticada
y mejorada con impeccable. Eligió ver **todas las llamadas de la central**, no solo las del módulo.

## Por qué en prod no había grabaciones
`MixMonitor` solo estaba en el contexto de la **cola** (`es_cola`). Con un grupo de timbrado normal (Dial), en las
salientes del personal, las internas y las campañas no se grababa nada. En test sí había grabaciones porque su grupo
es cola; ahí mismo una saliente contestada de 5:42 min del 14-nov no tiene grabación.

## Lo que se hizo
- Plan de marcado (`DialplanGeneratorService::lineasGrabar`): se graba en la entrada de cada troncal, grupos Dial,
  cola, salida a la calle, contexto restringido y la troncal de Campañas/Blaster (lo que entra y el blaster).
  `GRABANDO` evita grabar dos veces la misma llamada. Mismo archivo de siempre: `<UNIQUEID>.wav` en
  `voip.grabaciones.dir`, ligado por `CDR(grabacion)`. Si la saliente no la contestan, queda sin grabación.
- `extensions.conf.tpl`: las internas también se graban.
- Historial (`HistorialLlamadasService`): las llamadas que origina una campaña (`cob-<id>-<hora>`) son «Campaña», con
  número y campaña desde `cobranza_llamadas`; la troncal del módulo cuenta como troncal. `/voip/grabaciones` acepta
  esos id.
- Campañas/Blaster → **Dashboard** (`/cobranza/dashboard`, permiso `cobranza.view`; escuchar pide
  `voip.grabaciones.escuchar`):
  - Tipo de llamada con su conteo (Todas, Entrantes, Salientes, Campañas, Internas).
  - Resumen: llamadas, contestadas, **perdidas** (entraron y nadie contestó; clic = verlas), tiempo hablado y grabadas.
  - Gráfica por día (contestadas / perdidas / sin respuesta); clic en una barra filtra el historial a ese día.
  - Historial con búsqueda, estado y «Solo con grabación»; el botón de escuchar va al inicio de la fila y un
    micrófono tachado marca las contestadas que no se grabaron.
  - Reproductor fijo abajo: anterior/siguiente (pasa de página), velocidad 1×/1,5×/2×, descarga, atajos
    (espacio, ← →, Esc). Lo que se está viendo queda en la dirección de la página.
- Crítica con impeccable (dos revisores: diseño y navegador/accesibilidad), 25/40 antes de corregir. Se corrigió:
  reproductor que no se veía (el layout recorta lo «sticky»), foco invisible (el tema quita el outline), contraste
  del azul, ejes y buscador, y en modo oscuro el reset de `dark_mode.scss` que pintaba de blanco todo `<span>`
  (ahora excluye `.cd`).

## Test
Plan de marcado cargado con «Sincronizar con Asterisk». Llamada de prueba sin timbrarle a nadie
(`Local/s@cobranza-blaster`, reproduce `beep`): quedó grabada, ligada a su fila y se descarga por
`/voip/grabaciones/<id>` (200, audio/wav). Internas: Irving regeneró `extensions.conf` como root (salió como `.nuevo`
por una huella vieja; respaldo en `/etc/asterisk/extensions.conf.antes-grabacion`), `dialplan reload` y una interna de
prueba a una extensión sin teléfono pasó por la grabación y quedó sin grabación por no contestarse.

## En prod
Con la versión y después «Instalar dependencias» (regenera `extensions.conf` y el plan de marcado). Desde ahí se graba
todo; las llamadas anteriores siguen sin grabación. Retención: la de siempre (`megavoz:purgar-grabaciones`, 90 días).
