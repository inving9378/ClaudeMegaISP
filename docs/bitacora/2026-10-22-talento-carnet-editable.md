## 2026-10-22 — Talento: la credencial se cambia desde el dashboard de cada uno

Pedido: "desde talento, deben poder cambiar la credencial cuando estan en la dashboard de cada uno".
Decisiones: se cambian foto, nombre, teléfono, correo y RFC; cada quien la suya y el administrador
la de cualquiera; el diseño (frente, reverso y logo) sigue siendo uno para todos y solo lo cambia un
administrador.

### Qué se encontró antes

- En Vendedores la credencial no se edita: la ficha del vendedor solo la muestra y la descarga en
  PDF ("Editar vendedor" cambia estado y tipo). El diseño se cambia en Configuración › Ventas ›
  "Configuración de credencial" (permiso `config_view_sales`, solo roles de administración) y los
  datos de la persona en Administración › Usuarios. Probado en dev subiendo un frente y regresándolo.
- Las fotos se guardan de dos maneras: "Mi perfil" en el disco public (`uploads/perfil/{id}/…`,
  así están las 9 fotos que hay) y la edición de usuario en `public/perfiles/{nombre}` (carpeta que
  en dev no existe). El carnet de Talento, el de Vendedores y el PDF solo buscaban en
  `public/perfiles`, así que no mostraban ninguna de esas fotos.

### Qué cambia (rama `feat/talento-carnet-editable`)

- Ficha › Credenciales: botones "Editar foto y datos" (la persona o un administrador:
  super-administrator, Super Administrador, DESARROLLADOR o `talento.credentials.manage`) y
  "Cambiar diseño" (`config_view_sales`, usa la misma carga de Configuración).
- API `GET/POST /talento/api/colaboradores/{id}/carnet`: lee y guarda foto, nombre, apellidos,
  teléfono, correo y RFC en el usuario. La foto se guarda como en "Mi perfil"; si no se puede
  guardar, no se toca la anterior.
- `User::url_photography` y el PDF de la credencial encuentran la foto en los dos lugares.

Probado contra el servidor de prueba con la base de dev y cuentas demo (16 comprobaciones): la
persona edita lo suyo y el carnet y el PDF lo reflejan; no lee ni cambia el de otro; el supervisor
tampoco; el administrador cambia el de otro y el diseño; validaciones de nombre y correo. Las
cuentas demo se regresaron a sus datos y se borraron la foto y el diseño de prueba.

Pendiente aparte: la vista previa de la credencial en la ficha de Vendedores sigue buscando la foto
solo en `public/perfiles`.
