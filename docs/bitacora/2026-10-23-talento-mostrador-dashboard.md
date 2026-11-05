## 2026-10-23 — Talento: el Mostrador entra a su tablero como un técnico, sin lista de colaboradores

Pedido: «si entro como mostrador a talento debe mostrarse como mismo le sale al técnico, directo en
el dashboard, no en la lista de colaboradores… el dashboard y poder cambiar de pestañas».

### Qué se encontró (cuenta `prueba_mostrador`, mismos permisos que Diana)

- Entraba a su ficha, pero sin el tablero «Mi progreso»: solo se arma para roles de técnico
  (pantalla y `TalentoColaboradorController::progreso()`).
- Arriba tenía la ruta «Colaboradores › …» y las flechas anterior/siguiente, que con su permiso de
  crear órdenes recibían la lista completa (14) y la dejaban abrir la ficha de cualquiera
  (`resolverColaboradorAutoservicio()` y `ficha()` daban acceso a todas con
  `talento.work_orders.manage`, decisión del 28-sep).
- La lista que usa para elegir a quién asignar una orden le llegaba con todos los datos de cada
  colaborador (correo, teléfono, supervisor, roles…).
- Un Mostrador sin ficha de Talento (hay una cuenta así en dev) caía en el listado completo.

### Qué cambia (rama `fix/talento-mostrador-dashboard`)

- Tablero «Mi progreso» también para `Mostrador` y `SUPERVISOR_MOSTRADOR` (las pestañas de campo
  siguen siendo solo del técnico).
- Ruta «Colaboradores» y flechas solo para quien ve la lista (`talento.employees.view`); sin ese
  permiso no se carga la lista. Aplica también al técnico, cuyas flechas llevaban a fichas que no
  podía abrir.
- La ficha de otro que no se puede ver lleva a la propia (antes se abría vacía).
- `abreFichasPorOrdenes()`: crear órdenes ya no abre la ficha de cualquiera si se tiene rol
  Mostrador; las órdenes se crean desde «Órdenes de trabajo». Quien tiene ese permiso sin rol
  Mostrador (una cuenta Administrador en dev, dado a mano) sigue igual.
- `/talento/api/colaboradores` para quien no ve la lista: solo id, tipo, estado y nombre.
- Sin ficha y sin lista: aviso «Todavía no tienes ficha de colaborador en Talento» en lugar del
  listado.

### Pruebas (base de dev, solo consultas; la orden de prueba en una transacción que se deshace)

20 comprobaciones (en main fallan 11): Mostrador entra a su ficha sin ruta ni flechas, con
tablero; la ficha y los datos de otros lo regresan / 403; al buscar a quién asignar recibe solo
nombres; sigue creando órdenes; técnico igual que antes y sin flechas; administrador con lista,
ruta y flechas; la cuenta Administrador con el permiso a mano sigue abriendo fichas; Mostrador sin
ficha ve el aviso. En el navegador, entrando por Talento › Dashboard: su ficha con «Mi progreso» y
las pestañas, sin ruta ni flechas; escribir la ficha de otro lo regresa a la suya.
