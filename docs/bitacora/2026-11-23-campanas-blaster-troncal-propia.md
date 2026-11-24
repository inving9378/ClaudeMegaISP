# 2026-11-23 — Campañas/Blaster sale y entra por su propia troncal

## Pedido
Irving: las llamadas entrantes y salientes del módulo van por la troncal que se configura en el módulo
(Campañas/Blaster → Config. VoIP). Nada de elegir troncal por campaña.

## Lo que se hizo
- Salientes: se quitó «Troncal de salida» del alta de campaña. `BlastCampanaJob` y
  `AmiConnectionService::originate` siempre marcan por `PJSIP/<voip.trunk_endpoint_id>` (la de Config. VoIP).
  Si esa troncal no está aplicada (`voip_configuracion.estado` ≠ activa), la campaña espera sin gastar intentos.
  `cobranza_campanas.troncal_id` se queda en la tabla, pero ya no se usa.
- Entrantes: Config. VoIP tiene «Las llamadas que entran por esta troncal timbran en» (grupo de timbrado; vacío =
  primer grupo activo), en `voip_configuracion.grupo_entrante_id` (migración `2026_11_23_140000`).
- Plan de marcado: `DialplanGeneratorService` ahora genera el contexto de entrada de la troncal (`from-servnet`) y el
  del blaster (`cobranza-blaster`). Antes el del blaster era un stub para copiar a mano y ninguno existía en Asterisk.
  Se regenera al «Guardar y aplicar» de Config. VoIP y en «Instalar dependencias» (`voip:sincronizar`).
- VoIP → Troncales: ya no se ofrecen los propósitos «Saliente: Cobranza/Avisos/Anuncios/Corte»; solo aparecen si la
  troncal ya lo tenía.
- El sincronizador ya no reporta la troncal del módulo como extensión sobrante.

## Test
Migración aplicada; con «Sincronizar con Asterisk» quedaron cargados `from-servnet` (→ grupo-1 Atención a Clientes) y
`cobranza-blaster`. La troncal del módulo en test sigue sin aplicar (Servnet es de prod), así que no se hizo una
llamada real.

## En prod
Con la versión y después «Instalar dependencias». Para marcar, falta que Irving capture la troncal del módulo en
Config. VoIP (con el número de CallerID) y presione «Guardar y aplicar».
