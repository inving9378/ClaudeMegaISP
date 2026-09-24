## 2026-09-24 11:15 — MegaVoz: troncal real contra el Grandstream UCM (primera conexión a producción)

Irving dio los datos reales por WhatsApp (relayados por David) para reemplazar el troncal de
pruebas (Servnet, `id=2`) por la conexión real al UCM Grandstream de la oficina:

- IP del UCM: `192.168.105.9`
- Puerto: `5060`
- Extensión asignada a MegaVoz: `2005`
- DID real: `5512099363` (cuando alguien marca ahí, el UCM lo manda a la ext 2005)
- Contraseña: **no se escribe aquí** (regla del proyecto — secretos solo en BD cifrada/`.env`,
  nunca en docs). Guardada cifrada en `voip_troncales.secret` (`Crypt::encryptString`, mismo
  mutator que ya usan las demás troncales).

Confirmación explícita de Irving: "si tu logueas esa ext en megavoz entonces creas las
extensiones dentro de megavoz y ya les das el tratamiento que requieras ahi" — exactamente el
diseño ya construido en Fases 1-5 (MegaVoz se registra como una extensión más, y todo el
tratamiento — cola, golden rule, grabación, registro — vive del lado de MegaVoz).

### Qué se hizo

1. Troncal nueva (`id=3`, "Grandstream UCM - Ext 2005"): `tipo=registro`,
   `direccion=entrante`, `proposito=registro_ucm` (el valor que Fase 1 ya había dejado
   preparado en el enum desde el 23-sep, sin usar hasta hoy), `grupo_entrante_id=1`
   (Atención a Clientes), codecs `ulaw,alaw` (mismos que la de prueba).
2. Troncal de prueba (`id=2`, Servnet) **desactivada**, no borrada — queda de referencia.
3. Provisionada a Asterisk Realtime (`AsteriskProvisioningService::provisionar()`) +
   regenerado el dialplan dos veces (la primera corrió ANTES de que el endpoint existiera en
   `ps_endpoints`, así que `applyEndpointContexts()` no tuvo nada que actualizar todavía —
   se repitió después de provisionar y ahí sí quedó `context=inbound-trunk-3` en la fila real).
4. `pjsip reload` + `dialplan reload` para que Asterisk recogiera la config nueva.

### Verificado real, no solo "sin error"

- `pjsip show registrations` → **`trunk_reg_3/sip:192.168.105.9` — Registered** (exp. ~3586s).
  MegaVoz está genuinamente registrado como extensión 2005 en el UCM real de la oficina.
- `pjsip show endpoint trunk_3` → contexto `inbound-trunk-3`, identify por IP
  (`192.168.105.9/32`), auth/codecs correctos.
- Prueba de ruteo (`channel originate Local/s@inbound-trunk-3 ...`, sin tocar el UCM real):
  entra correctamente a `grupo-1` (Atención a Clientes) → `Queue(cola_1,...)` → intentó
  timbrar a 1001/1003 tal como debe. Confirma que si alguien marca al DID real
  `5512099363`, el flujo completo de MegaVoz (contestador de relleno → cola → agentes) se
  dispara correcto.
- Datos de prueba (filas de `voip_llamadas`/`voip_queue_log`, archivos de grabación)
  limpiados antes de cerrar.

### Pendiente / siguiente

- **No se hizo una llamada real de punta a punta desde un celular externo** — solo se
  verificó el ruteo interno del dialplan. Cuando alguien marque de verdad al
  `5512099363`, vale la pena confirmar una vez en vivo.
- Instrucción de Irving: cuántos canales simultáneos tiene con Servnet — dio "tengo muchos
  canales en servnet" (sin número exacto). Suficiente para no bloquear Fase 7; si en algún
  momento se necesita un tope exacto, preguntar de nuevo.
- Instrucción de Irving: **"cuando termines sigue con la fase 7"** — anuncios/avisos/corte
  por zona, reutilizando el motor del Blaster de cobranza.
