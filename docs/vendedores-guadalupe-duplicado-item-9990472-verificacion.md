# Item #9990472 — Vendedor "Guadalupe" duplicado (sellers 12 y 48) — verificación

**Veredicto: NO es un duplicado. Son dos personas reales distintas que casualmente comparten el
nombre de pila "Guadalupe". No se tocó ningún dato de vendedor/usuario.**

## Investigación (solo lectura)

| | `sellers.id=12` | `sellers.id=48` |
|---|---|---|
| `user_id` | 9 | 4758 |
| Nombre completo | GUADALUPE HERNANDEZ JIMENEZ | GUADALUPE PADILLA MONTES DE OCA |
| Email | GUADALUPE.HERNANDEZ@MEGANETT.COM.MX (corporativo) | GPADMON2682@GMAIL.COM (personal) |
| `login_user` | GUADALUPE | Guadalupe02 |
| RFC | HEJG601212 | PAMG821126MDFDND02 |
| Teléfono | 5521785848 | 5628415500 |
| Domicilio | AV HDA LA PURISIMA MZ8, Ex Hda Santa Inés | Cond. Áloe, Arbolada los Sauces II, Zumpango |
| `users.estado` | activo | **inactivo** (`active=0`) |
| `users.created_at` | 2024-04-08 | 2026-04-28 |
| `sellers.created_at` | 2024-05-21 | 2026-04-28 |
| `seller_status` (ambos `status_id=1`) | Activo | Activo (aunque el usuario ya está inactivo — mismatch menor, no tocado) |
| Clientes (`client_main_information.seller_id`) | **474** | **0** |
| Rol Spatie | Vendedor | Vendedor |
| `talento_colaboradores` | — | id=27, depto "Ventas", alta 2026-06-08 |

Nombre, apellidos, RFC, email, teléfono y domicilio son **completamente distintos** — no hay forma de
que sea la misma persona con un alta duplicada. El registro en `talento_colaboradores` (departamento
Ventas, alta 2026-06-08) confirma que Guadalupe Padilla fue un alta real de personal, no una prueba o
un typo: simplemente nunca se le asignaron clientes antes de que su cuenta quedara inactiva.

## Por qué no se ejecuta la "consolidación"

El item nació de una detección automática por **coincidencia de nombre de pila** ("Guadalupe") y el
`comentarios_claude` que quedó escrito antes de esta investigación asumía que era la misma persona
duplicada ("¿Cuál se conserva y cuál se fusiona?"). La aprobación de Irving (`aprobado_irving`,
2026-09-08) respondía a esa pregunta, no a los datos reales — que solo esta investigación deja
claros. Fusionar dos personas distintas (reasignar los 474 clientes/comisiones de Hernández a una
cuenta de Padilla, o borrar el registro de vendedor de una empleada real) sería un daño de datos real
sobre dos cuentas legítimas, exactamente lo que el propio `prompt` del item pide evitar ("NO ejecutar
borrado/desactivación sin decisión explícita de Irving"). La decisión correcta, según el propio
`prompt` del item, es la opción **(b): son personas distintas → no tocar, sólo distinguirlas mejor en
el listado**.

## La opción (b) ya está cubierta, sin necesitar cambio de código

- El listado `/vendedores` (`VendedorListar.vue`) **ya** muestra por default (`visible:true`) las
  columnas "Nombre", "Apellido paterno" y "Apellido materno" por separado — con esas columnas
  visibles, "GUADALUPE HERNANDEZ JIMENEZ" y "GUADALUPE PADILLA MONTES DE OCA" ya se distinguen sin
  ambigüedad. La confusión solo ocurriría si un usuario ocultó esas columnas manualmente
  (`/setting-table`, preferencia por-usuario).
- El detalle del vendedor (`/vendedores/{id}/seguimiento-vendedor/{user_id}`) fue reforzado el mismo
  día por el item hermano **#9990601** (commit ya en `main`, `SellerController::edit()` en
  `app/Modules/Addons/Vendedores/Controllers/Vendors/SellerController.php`), que agregó
  `seller_nombre` (nombre completo) + `seller_activo`/`seller_estado` a la vista — citando
  textualmente este mismo caso Guadalupe Hernández vs. Guadalupe Padilla como el incidente que lo
  motivó.

Con ambas piezas ya en su lugar, no queda trabajo de UI pendiente para este item.

## Conclusión

Sin cambio de código de negocio ni de datos. Cerrado documentando el hallazgo: no hay duplicado que
consolidar; la mejora de distinción en listado/detalle ya existe (columnas por default + item
#9990601 del mismo día).
