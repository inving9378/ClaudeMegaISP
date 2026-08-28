# Item #626 — Portal Cliente: CFDI PDF/XML — verificación (RESUELTO, decisión de PAC pendiente de Irving)

## Pedido original

El checklist `docs/megafamilia-checklist-clasificado-2026-07-15.md` (punto 1.4/Sección 4) pedía
exponer en el Portal Cliente: `GET /facturas/{id}/pdf`, `GET /facturas/{id}/xml`,
`POST /facturas/request`, `PUT /account/fiscal`. Verificado por grep: hoy no existe ningún
controller de `PortalCliente` con esos métodos.

## Decisión previa de Irving (q1 del brief)

Irving aprobó la opción "reutilizar el servicio de timbrado existente en Medussa (mismo
PAC/proveedor ya configurado)". Esa decisión asume que Medussa **ya tiene un PAC funcional
timbrando CFDI** — esa premisa es falsa.

## Verificado (grep, 2026-08-28, re-confirmado por 9na vez independiente)

- `app/Providers/AppServiceProvider.php:38-39` — el binding activo de
  `TimbradoServiceInterface` es `NullTimbradoService`.
- `app/Services/Finance/Timbrado/NullTimbradoService.php` — **todos** sus métodos (excepto
  `isDisponible()`) lanzan `PacNoConfiguradoException`.
- `app/Services/Finance/Timbrado/PacNoConfiguradoException.php` — mensaje explícito: "implementando
  TimbradoServiceInterface antes de emitir facturas fiscales".
- No hay PAC configurado en ningún `.env`/config del sistema.
- El Portal Cliente ya muestra "próximamente, contacta soporte" para CFDI (no hay expectativa rota
  de cara al cliente).

Este mismo hallazgo fue confirmado de forma independiente **9 veces** por wt-4, wt-2, wt-3, wt-6 y
wt-1 (x2) entre 2026-08-28 15:13 y 16:59, todas contra el mismo fingerprint. El propio supervisor
(Thomas) respondió "DETENTE" a la última escalación, dejando registradas dos opciones:

1. **Cerrar como RESUELTO — premisa incorrecta**, documentar el hallazgo, decisión de PAC pendiente
   de Irving. *(reversible, recomendada)*
2. Construir scaffolding sin PAC real (endpoint que siempre devuelve vacío/404). *(no reversible,
   no recomendada — crea una superficie cliente-facing sobre un timbrado que no existe)*

## Por qué no se re-escala una décima vez

Entre la escalación de Thomas y esta vuelta, un bug conocido de re-aprobación automática
("jarvis-ya-decidido") desbloqueó el item porque el brief tenía todas las preguntas respondidas,
sin verificar que la implementación seguía bloqueada — esto generó un ciclo de
escalar→desbloquear→reclamar→re-verificar→escalar. Repetir la consulta habría alimentado el mismo
ciclo sin aportar información nueva: el hallazgo ya está confirmado y la opción reversible y
recomendada ya está identificada, así que se ejecuta esa en vez de consultar de nuevo.

## Resolución

**Cerrado como RESUELTO — premisa incorrecta.** No se construye ningún endpoint de CFDI hoy: hacerlo
sin un PAC real crearía una funcionalidad fiscal-facing sin proveedor de timbrado detrás (riesgo de
doble-timbrado o de servir CFDI inexistentes), y el propio spec del item ya asume un PAC que no
existe. La decisión real y pendiente —**qué proveedor de timbrado (PAC) contratar**— es de Irving:
implica dinero (contrato con el proveedor) y una elección de arquitectura fiscal que el circuito no
puede tomar por su cuenta. Queda anotado en `CLAUDE.md` → sección "Portal Cliente — estado" →
"Portal: CFDI timbrado" como pendiente, tal como ya estaba antes de este item.

**Sin cambio de código funcional** (solo este documento de verificación).
