# 2026-11-24 — Campañas/Blaster: elegir voz y escuchar la nota

## Pedido
Irving: en el alta de campaña elegir el tono de voz y poder escucharlo, como con la IA; lo que se reproduce es la
nota, con opciones para ajustar la voz.

## Lo que se hizo
- La nota es el mensaje que escucha el cliente, en todos los tipos. Se quitó «Mensaje a leer» del alta.
  - Cobranza: la nota arranca con el mensaje de siempre y acepta `{nombre}`, `{saldo}`, `{fecha}` y
    `{numero_cliente}`; vacía = el mensaje de siempre.
  - Aviso/anuncio/corte: la nota es obligatoria y se lee tal cual (un solo audio para toda la campaña).
- Voz por campaña (`cobranza_campanas.voz`, `voz_velocidad`, `voz_tono`; migración `2026_11_24_120000`):
  - Voz: las 6 de OpenAI que usa María (Nova por defecto).
  - Velocidad: 0.5× a 1.5× (0.95× por defecto, la de antes).
  - Tono (Amable, Formal, Firme, Alegre, Tranquilo): solo con el modelo `gpt-4o-mini-tts`; con `tts-1` queda
    deshabilitado y lo dice.
- «Escuchar» (`POST /cobranza/campanas/escuchar`, máx. 20 por minuto) genera la nota con la voz elegida; en cobranza
  con un cliente de ejemplo. Cada muestra se registra como uso en Módulos IA.
- Las campañas creadas antes (sin voz) siguen leyendo su mensaje de antes.

## Test
Migración aplicada. En test no hay voz asignada a «Voz de las llamadas de Campañas/Blaster», así que el alta muestra el
aviso y «Escuchar» queda deshabilitado; los endpoints responden y validan (pruebas en `VozCampanasTest`).

## En prod
Con la versión. Para escuchar y para que las campañas hablen hace falta asignar esa voz en Integraciones → Módulos IA
(para el tono, con `gpt-4o-mini-tts`).
