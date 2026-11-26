# 2026-11-26 — Grabaciones de llamadas en MP3 (75% menos de espacio)

## Pedido
Irving: «compactar los audios de tal forma que se escuche a la perfección pero no ocupe tanto espacio».
Sus decisiones:
1. Se puede reemplazar el WAV por el MP3, porque es la misma grabación, solo más chica.
2. La purga de 90 días (`megavoz:purgar-grabaciones`) se queda.
3. El formato es MP3.

## Medición
Se midieron 7.5 min de voz en español, en el mismo formato que graba Asterisk (WAV de 8 kHz, 16 bits, mono).

| Formato | KB por minuto | Ahorro |
|---|---|---|
| WAV actual | 938 | — |
| FLAC (sin pérdida) | 463 | 51% |
| **MP3 32 kbps** | **235** | **75%** |
| Opus 24 kbps | 178 | 81% |
| GSM | 95 | descartado (suena a teléfono viejo) |

- La línea solo trae audio de teléfono (8 kHz), y el WAV lo guarda en 16 bits por muestra, el doble de lo que trae la línea.
- Se eligió MP3 porque lo abre cualquiera: el navegador, iPhone, WhatsApp y Windows.
- Va a **16 kHz y no a 8**: a 8 kHz el MP3 es la variante «MPEG 2.5», que no todos los reproductores abren. A 16 kHz ocupa lo mismo, porque el tamaño lo pone el bitrate.

## Lo que cambió
- **`CompresorGrabaciones`** (servicio nuevo) convierte una grabación sin perderla en el camino:
  1. hace el MP3 aparte (`.mp3.part`);
  2. revisa que sea MP3 y que dure lo mismo que el WAV (±1 s);
  3. le copia los permisos y la **fecha** del WAV, para que la purga cuente desde la llamada;
  4. lo deja en su lugar y apunta la llamada (`voip_llamadas.grabacion`);
  5. solo entonces quita el WAV.

  Si algo falla antes del paso 5, el WAV se queda como estaba. Corre con `nice` e `ionice` para no
  quitarle CPU ni disco a las llamadas.
- **`megavoz:comprimir-grabaciones`** (comando nuevo):
  - corre cada 5 min, en segundo plano;
  - toma hasta 200 grabaciones por vuelta, de la más nueva a la más vieja, durante máximo 4 min;
  - solo toca un WAV que lleve 2 min sin cambiar y cuya llamada ya esté en `voip_llamadas`;
  - si una falla, no la reintenta en 24 h;
  - sin `--force` solo dice qué haría;
  - sin ffmpeg no hace nada y lo avisa una vez al día.
- **Purga de 90 días:** ahora también ve los `.mp3`.
- **Escuchar y descargar:** salen como `audio/mpeg` y `.mp3` cuando la grabación ya está convertida;
  las que siguen en WAV, igual que antes.
- Asterisk sigue grabando en WAV durante la llamada: no se tocó el dialplan ni hay que reiniciar
  nada.
- No requiere «Instalar dependencias»: usa ffmpeg, que ya instala el paso 55.

## Cómo se probó
- `tests/Unit/VoIP/CompresorGrabacionesTest.php`: 5 pruebas y 30 comprobaciones, con ffmpeg real.
  - Convierte un WAV a MP3 de 16 kHz, mono, 32 kbps, en menos del 30% del tamaño, y apunta la llamada.
  - Conserva la fecha y los permisos del WAV.
  - Si no se puede apuntar la llamada, el WAV queda intacto y sin restos.
  - Un WAV dañado tampoco deja restos.
  - Solo limpia temporales viejos.
- **El comando, con la base de dev dentro de una transacción que se deshizo.** Se usaron tonos
  sintéticos con el nombre de llamadas reales.
  - La simulación eligió 2 de 4 audios: dejó fuera el recién cerrado y el que no tiene llamada.
  - Con `--force` los convirtió (0.6 MB a 0.2 MB) y apuntó sus llamadas al `.mp3`, con la fecha
    original.
  - La segunda vuelta no encontró nada que convertir.
  - La purga simulada ya listó los `.mp3`.
  - Al deshacer, la base volvió a `.wav`.
- **Escuchar y descargar:** se llamó al controlador en el mismo proceso. El WAV sale como
  `audio/wav` `llamada-….wav` y el MP3 como `audio/mpeg` `llamada-….mp3`.

## Pendiente
- En dev no corre `schedule:run`, y la carpeta de grabaciones es del grupo `asterisk`. Para oír una
  llamada real convertida en dev hay que correr el comando como `www-data`:
  `sudo -u www-data php artisan megavoz:comprimir-grabaciones --force`.
- Publicarlo en una versión, cuando Irving lo pida. En prod basta «Buscar actualizaciones»: las
  grabaciones existentes se van convirtiendo solas, 200 cada 5 min.
