## 2026-10-23 — El Usuario WEB de una ficha no puede ser el usuario de una cuenta ajena

Seguimiento del pendiente 16 (ver `2026-10-23-ficha-cliente-cuenta-ligada.md`).

### Qué pasaba

La app MegaFamilia, el Marketplace del portal y la copia de la contraseña al guardar la ficha
encuentran la cuenta del cliente por su Usuario WEB, sin revisar de quién es esa cuenta. Y nada
impedía que el Usuario WEB de una ficha fuera el usuario de entrada de una cuenta de personal o de
otro cliente.

### Qué cambia (rama `fix/usuario-web-no-de-personal`)

- `User::esCuentaDelCliente($clientId)`: cuenta de cliente (rol client o sin rol, nunca de
  personal ni la del sistema) con ese número; si no tiene número, solo con rol client (una sin rol
  y sin número es personal sin rol todavía). `User::scopeCuentasDeCliente()` para las consultas;
  el observer de la ficha ya lo usa.
- Regla `App\Rules\UsuarioWebLibre` en el alta (`ClientCreateRequest`) y en la edición
  (`ClientInformationRequest`): «Ese Usuario WEB ya lo usa otra cuenta; elige otro.» debajo del
  campo. En la edición solo se revisa si el Usuario WEB cambia.
- Último candado en el modelo de la ficha (`saving`, clase base y `App\Models`) para lo que la
  guarda sin pasar por esos formularios; también solo cuando cambia.
- App MegaFamilia (`ApiController::login`) y Marketplace (`resolveUserId`): una cuenta ajena no da
  sesión ni cuenta como la del cliente (aviso `megafamilia_login_cuenta_ajena` en el log).
- La copia de la contraseña al guardar la ficha solo llega a la cuenta de ese cliente.

En dev ninguna ficha tiene hoy el Usuario WEB de una cuenta ajena ni hay Usuarios WEB repetidos.
Las fichas que ya estuvieran así se pueden seguir guardando; solo no se les puede poner uno ajeno.

### Pruebas (transacción que se deshace, base de dev, como administrador)

- 18 comprobaciones: editar ficha con y sin cuenta poniendo el usuario de una cuenta de personal o
  de otro cliente (422), editar sin tocarlo o con uno libre (se guarda), Usuario WEB numérico con
  ceros a la izquierda (se guarda), alta con usuario de personal (422, sin cliente creado), candado
  del modelo, app MegaFamilia y Marketplace con una ficha normal (entra) y con fichas que ya tenían
  el usuario de personal o de otro cliente (no entra, no se toca ninguna contraseña ajena). En
  main fallan 8.
- En el navegador: el aviso sale debajo de Usuario WEB al editar y al dar de alta; nada se guardó.
- Las pruebas del pendiente 16 (14, 11 y 3) siguen pasando.
- Pruebas existentes (`Portal/AislamientoTest`, `Portal/AuthTest`, `HistorialCambiosClienteTest`,
  `CanceladoBajaDefinitivaTest`, `MegaFamilia/ApiIdorTest`): mismo resultado en main y en la rama;
  sus errores son de preparación de datos en la base de pruebas, ajenos a este cambio.
