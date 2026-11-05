## 2026-10-29 — Asistencia: si el GPS falla, se escribe dónde está

Pedido de David: «si el GPS falla que la puedan poner a mano».

Antes:
- **App:** si el GPS fallaba, ya pedía escribir la dirección, pero la búsqueda (Google Geocoding) no
  respondía en dev: la llave de `.env` no tiene esa función activada (REQUEST_DENIED). Sin
  resultados, no había forma de registrar la entrada.
- **Web (Mi día):** si el navegador no daba la ubicación (sin permiso, sin GPS, o entrando por la IP
  sin https), solo decía «Necesitamos tu ubicación» y no dejaba registrar.

Qué se hizo (rama `feat/asistencia-ubicacion-a-mano`):
- `GeocodingService::buscar()`: si Google no da resultados (sin llave, función no activada, error o
  nada encontrado), busca en OpenStreetMap (Nominatim, sin llave), el mismo buscador de direcciones
  que usan los mapas del sistema. La app no necesita versión nueva.
- Web: si no hay ubicación, aparece «¿Dónde estás? Escribe la dirección o el lugar», se busca
  (`GET /talento/portal/geocodificar`, la misma búsqueda que la app) y se elige de la lista; la
  entrada se manda con `location_source = manual_address`.
- `AttendanceService::checkIn()` guarda de dónde salió la ubicación (`check_in_location_source`,
  como ya lo hacía la app). Se revisa igual que con GPS: si cae fuera de los lugares de trabajo,
  queda marcada para revisión.
- Se ve que fue escrita: «Ubicación escrita» en la tarjeta de hoy y en la lista de asistencia del
  administrador; en el detalle, «Ubicación escrita, sin GPS».

Probado:
- Búsqueda en el servidor: «Santa Ana Nextlalpan» → 5 resultados; una dirección de la CDMX → 5;
  texto sin sentido → 0.
- Vista previa como Mostrador, sin permiso de ubicación: sale el aviso y el cuadro, 5 direcciones,
  al elegir se registra la entrada (a ~500 m de la bodega: marcada para revisión) con «Ubicación
  escrita»; el administrador la ve en la lista y en el detalle.
- Con GPS (permiso dado, en la bodega): igual que antes, «BODEGA SANTA ANA», sin alerta, sin cuadro.
- Las entradas de prueba se borraron.

### «Flagged» → «Para revisión» (pedido de David)
En Talento › Asistencia (lista del administrador) quedaban textos en inglés: el contador de arriba
(«N flagged»), la opción del filtro de estado, la casilla «Solo flagged», la columna «Flag» y la
etiqueta de la fila. Ahora dicen «para revisión» / «Para revisión» / «Solo para revisión» /
«Revisión». Solo texto; el filtro manda el mismo valor (`flagged`).
