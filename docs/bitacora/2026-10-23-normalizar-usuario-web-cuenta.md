## 2026-10-23 — `client:normalizar-usuario-web` vuelve a actualizar la cuenta del cliente

Hallado al revisar el pendiente 16 (ver `2026-10-23-ficha-cliente-cuenta-ligada.md`).

### Qué pasaba

El comando (item #155) quita los ceros a la izquierda del Usuario WEB y contaba con que, al guardar
la ficha, la cuenta del cliente recibiera el mismo cambio. Pero guardaba con la clase base de la
ficha (`App\Modules\Core\Clientes\Models\ClientMainInformation`), y la copia a la cuenta
(`ClientMainInformationObserver`) solo está registrada en `App\Models\ClientMainInformation`. Con
`--apply`, la ficha quedaba normalizada y la cuenta con el usuario anterior: el cliente perdía el
enlace que usan MegaFamilia y el Marketplace del portal. En dev, aplicado dentro de una
transacción que se deshizo, quedaban 754 cuentas así.

En dev el comando no se ha aplicado (siguen 870 fichas por normalizar). En producción no se sabe.

### Qué cambia (rama `fix/normalizar-usuario-web-cuenta`)

- El comando usa `App\Models\ClientMainInformation`, así que cada ficha pasa por la misma copia
  que al guardarla desde la pantalla.
- Al final reporta cuántas cuentas difieren de su ficha solo por ceros a la izquierda, en dry-run
  y con `--apply`. En producción, correr el dry-run dice si una corrida anterior dejó cuentas así.

### Pruebas (transacción que se deshace, base de dev)

- `--apply` en la rama: 870 normalizados, 0 omitidos, 0 cuentas desfasadas; quedan solo las 2
  fichas que ya tenían un usuario distinto al de su cuenta antes de correr nada. En main, 756.
- Efectos de pasar por la copia normal: las cuentas no cambian nada más que el usuario; 34 fichas
  con la dirección vacía la reciben armada de calle, colonia, municipio y estado, como al guardar
  desde la pantalla; ninguna dirección existente cambia; ningún estado cambia.
