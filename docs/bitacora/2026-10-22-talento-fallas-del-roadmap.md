## 2026-10-22 — Talento: se corrigen las fallas que encontró la verificación del roadmap

Pedido: "corrige las fallas cada una en su rama", sobre las fallas 1 a 5 de
`2026-10-22-talento-quita-roadmap.md`. Cada rama es un solo commit, sin mezclar a main todavía;
las cinco mezclan limpio contra main y no se tocan entre sí. Pruebas en transacción contra la base
de dev (se deshacen) y con las cuentas demo.

| # | Rama | Qué arregla |
|---|---|---|
| 1 | `fix/talento-licencia-fondo` | Registrar una licencia por vencer daba 500 (`setting()` no existe). Ahora abre el fondo de renovación (800 / 100 por defecto, sin autorizar: no descuenta hasta que alguien lo autorice). El comando diario `talento:check-credential-expirations` tenía la misma llamada y se caía en la primera licencia por vencer. |
| 2 | `fix/talento-radio-checada` | El radio por defecto salía 0 por el orden de `(int)` y `??`. Corrección de lo reportado: los sitios no se afectaban (cada uno exige su radio); lo afectado era la entrada en el lugar de la orden de trabajo (siempre "fuera de rango") y la pantalla Sitios, que mostraba 0. |
| 3 | `fix/talento-cierre-encuestas` | `talento:cerrar-encuestas` (diario 06:30, con `--dry-run`) cierra las encuestas sin respuesta a los 7 días y valida la orden que las esperaba, como estaba diseñado (decisión confirmada hoy). Además la encuesta ya no re-valida: antes una tarea ya validada recibía la fecha de hoy y se pagaba otra vez en la semana en curso, y se validaban órdenes canceladas. |
| 4 | `fix/talento-enlace-administradores` | El distintivo "Talento" de Administración › Usuarios abre `/talento/colaborador/N`. |
| 5 | `fix/talento-ranking-ventas` | `GET /talento/api/ventas/ranking` y también `GET /talento/api/prospectos` piden `talento.employees.view`. Hallazgo al arreglarlo: la segunda devolvía a cualquier Vendedor los 2,211 prospectos de todos los vendedores con nombre, correo, teléfono y dirección. |

Antes de subir la 3 a producción: `php artisan talento:cerrar-encuestas --dry-run` en prod lista
las órdenes que validaría la primera corrida (en dev no hay encuestas). Caso que queda abierto:
una orden legacy validada por el administrador y después aceptada en campo conserva su primera
fecha de validación; si la liquidación de esa semana se calculó mientras la orden no estaba
validada, no se cuenta. Es raro (la validación del administrador pide la orden completada, antes
de aceptar) y se prefirió a pagar dos veces.

### Falla 6, confirmada y sin corregir

El alta de campo (`FieldFlowService::generateClientCredentials`) escribe la contraseña temporal en
`users.password` y en `client_additional_information.original_password`; el portal del cliente
compara contra `client_main_information.password`. La contraseña que el técnico le da al cliente
no le abre el portal, y además cambia la de su usuario espejo. Toca autenticación: queda para
decidir aparte.
