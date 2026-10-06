## 2026-10-06 — WhatsApp: en líneas oficiales el agente solo recibe por Meta (Evolution = solo para ver)

Rama `whatsapp-meta-solo-entrada`. Decisión de Irving (2026-10-06): "el agente solo recibe los mensajes desde
el canal oficial de Meta"; Evolution se conecta al mismo número (coexistencia, W Business) solo para ver.
Cierra el hueco 1 anotado en `docs/integraciones/mapa-servicios-externos-2026-10-05.md` (doble entrada).

### Qué se veía → qué pasa ahora

- **Con el mismo número en Meta y en Evolution, cada mensaje del cliente entraba dos veces** (por Meta y por
  Evolution, con IDs distintos) y el agente habría contestado dos veces → en una línea marcada como oficial
  de Meta, lo que manda Evolution se ignora: el mensaje entra solo por Meta y ahí lo atiende el agente.
  Evolution sigue conectado (su conexión y QR sí se atienden) para poder ver la línea.
- **El número de ventas entra hoy por el webhook de Marketing (Evolution)** → si ese número se da de alta como
  línea oficial de Meta, el agente de Marketing deja de contestar la copia de Evolution.
- Las líneas que son solo de Evolution siguen exactamente igual.
- El administrador de líneas lo explica en las líneas oficiales.

### Piezas

- `WhatsAppRouterService::route()` (entrada Evolution): ignora mensajes si la línea es `provider = meta`
  (`esSoloVista()`); conexión y QR siguen.
- `Marketing\Controllers\EvolutionWebhookController`: ignora `messages.upsert/update` si la instancia es una
  línea oficial.
- `WhatsAppInstanceManager.vue`: nota en líneas oficiales.

### Verificación (transacción + rollback, eventos y colas falsos)

Línea Evolution: guardado 1 / aviso al agente 1 (igual que hoy). Línea Meta: copia por Evolution 0/0; mensaje
por Meta 1/1; conexión de Evolution atendida. Marketing: sin línea oficial entra al agente (igual que hoy); con
línea oficial no entra. Hoy no hay líneas oficiales: sin cambio de comportamiento.

### Pendiente (de esta lista)

- Bloqueo previo de envíos fuera de la ventana de 24 h en líneas oficiales (hoy Meta los rechaza).
