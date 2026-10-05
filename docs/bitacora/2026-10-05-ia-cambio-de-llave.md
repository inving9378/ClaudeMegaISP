## 2026-10-05 — IA por módulo, lote 7: cambiar la llave funciona sin sorpresas

Rama `ia-cambio-de-llave` (worktree `/home/meganet/megaisp-wt-integrations`), commit `2d85704d`.
Continúa `docs/bitacora/2026-10-02-integration-hub-ia-por-modulo.md` (lotes 1–6, ya en la V1.52).

Pedido: dejar todo lo de IA listo para que cambiar una llave (o de proveedor) funcione bien, antes de
pasar a los módulos de servicios (Evolution → WhatsApp oficial, etc.). El Circuito CC no se tocó.

### Qué se veía roto → qué pasa ahora

- **Claude sin saldo salía "Válida" en Integraciones** (el validador aceptaba el error 400 como bueno)
  → la llave se prueba con el mismo camino que usan los módulos y el Hub dice el motivo real: llave
  inválida, sin saldo, límite de peticiones, proveedor caído o modelo no disponible.
- **Al cambiar la llave se quedaba el "Válida" de la llave anterior** → al guardar, rotar o cambiar la
  URL se borra el estado viejo y se prueba la llave nueva en ese momento; sale un aviso con el resultado.
- **Se podía borrar una integración que usaban módulos** (quedaban sin IA sin aviso) → no deja borrarla
  y dice quién la usa; desactivarla avisa qué módulos se quedan sin IA. Cada integración muestra cuántos
  módulos la usan.
- **No había forma de comprobar un módulo después de cambiarle la IA** → botón **Probar** en cada módulo
  de "Módulos IA" (consulta mínima con su integración y su modelo); el estado avisa si la integración
  asignada se borró o si su llave falla.
- **Ollama/DeepSeek no se podían dar de alta** (no había dónde poner la URL y exigía llave) → las
  integraciones de IA tienen "URL del servicio" y "modelo para probar"; las compatibles con OpenAI pueden
  no llevar llave.
- **La voz seguía amarrada a la llave de OpenAI por defecto** (y el bot de voz la leía una sola vez al
  arrancar: cambiarla exigía reiniciarlo) → cuatro módulos nuevos en "Módulos IA": oído y voz del bot
  «María», voz de cobranza y voz de los videos. La llave se lee en cada llamada.
- **SmartImport usaba "el primer proveedor activo"** para sugerir cómo resolver conflictos → tiene su
  propia asignación.
- **El chat `/ia` guardaba su propia copia de la llave** (cambiarla en Integraciones no le llegaba) →
  migración `2026_10_05_120000_vincular_proveedores_chat_ia_al_hub`: los proveedores del chat toman la
  llave del Hub. Solo se aplica cuando la copia era idéntica (en dev lo son las dos).

### Verificación (transacción + rollback, llaves reales)

- OpenAI válida → "La llave funciona (probada con gpt-4o-mini)". Anthropic → "sin saldo" (antes "Válida").
- Llave mala guardada → el Hub la marca inválida al instante; el módulo falla en la siguiente llamada sin
  reiniciar nada; "Probar" explica el motivo. Rotar a la buena → todo vuelve a funcionar.
- Editar solo el nombre no vuelve a probar la llave; cambiar la URL sí.
- Borrar integración en uso → 422 con la lista; desactivarla → aviso; integración borrada → mensaje claro.
- Voz: asignar Claude a un módulo de voz → rechazado ("no soporta voz"). Con OpenAI: texto a voz (bot y
  cobranza) genera audio, voz a texto transcribe "Hola, quiero pagar mi recibo de internet."; el uso
  queda registrado en el Hub.
- SmartImport sin IA → mensaje claro; con OpenAI → sugerencia real.
- Migración: los 2 proveedores del chat quedan vinculados y resuelven la misma llave del Hub.
- Frontend compila (`npm-build.sh`).

### Pendiente tras el merge

- `php artisan migrate` en dev (la migración del chat), `queue:restart` y build de producción del frontend.
- **Voz:** sin asignación, el bot de voz queda mudo/sordo al reiniciarse, y cobranza y videos sin voz.
  Asignar `voip.voz`, `voip.transcripcion`, `cobranza.voz`, `marketing.voz_videos` antes de reiniciar el bot.
- Producción: igual que el resto de Módulos IA, todo apagado hasta asignarlo allá.

## 2026-10-05 07:00 — Lote 7 integrado y voz asignada

- Merge a `main`: `f64f1594`. Migración del chat `/ia` aplicada: los 2 proveedores quedaron tomando la
  llave del Hub (sin copia propia). `queue:restart` hecho. Frontend de producción recompilado en
  `/var/www/megaisp`.
- Voz asignada a la integración OpenAI con los modelos de siempre (OK de Irving): `voip.voz` tts-1,
  `voip.transcripcion` whisper-1, `cobranza.voz` tts-1, `marketing.voz_videos` tts-1. Las cuatro pasan
  "Probar". Módulos con IA asignada en dev: 11 de 25.
- El proceso del bot de voz que sigue corriendo desde el 24-sep usa el código viejo; al reiniciarlo ya
  toma la voz de Módulos IA (asignada, así que no queda mudo).
