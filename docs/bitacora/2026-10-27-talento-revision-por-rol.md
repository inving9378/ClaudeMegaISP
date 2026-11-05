## 2026-10-27 — Talento: revisión completa por rol antes de que la vea Irving

Recorrido automático en el sitio de dev (puerto 80) con cinco cuentas de prueba: administrador
(`prueba_admin`), Mostrador con los permisos de Diana (`prueba_mostrador`), técnico
(`demo_tecnico_bajo`), supervisor (`demo_supervisor`, con un técnico a cargo) y vendedor
(`demo_vendedor`). Cada enlace de Talento del menú, cada pestaña de la ficha propia y de una ajena, y
las 23 pantallas generales por dirección: ~300 vistas. Se anotaron errores de la página, respuestas
4xx/5xx y avisos de error visibles.

### Lo que ya estaba bien

- Entrada: técnico, vendedor y Mostrador caen en su ficha; el supervisor en el tablero de su equipo
  (solo su técnico); el administrador en Colaboradores. La ficha ajena redirige a la propia.
- Flujo completo con una orden real (borrada al final): el Mostrador la crea para hoy sin hora en
  Talento › Órdenes → el técnico la ve en «Mi día» y la inicia → el administrador la ve «En curso» con
  «27/10/26». «Asignar trabajo» desde cliente y prospecto (rol → persona activa, día obligatorio) y
  «Nuevo flujo» bien, con el envío interceptado.
- Liquidaciones del Mostrador: lista con «A pagar» calculado ($2,000 / $2,111.11 / $2,611.11 / $1,500).
- Academia por rol: el Mostrador ve los 2 cursos «general»; el técnico los 5.
- Las pantallas generales que un técnico abre por dirección (custodia, dispositivos, academia, roles
  múltiples, asistencia, flujo de campo) le muestran solo lo suyo.

### Lo que se corrigió (rama `fix/talento-revision-por-rol`)

1. **El supervisor no podía penalizar ni registrar un préstamo** desde la ficha de su técnico: la
   ventana pedía elegir a la persona de la lista de todos los colaboradores (403 para él) y quedaba
   vacía; además el catálogo de tipos de penalización también le daba 403. Ahora, dentro de la ficha,
   la persona ya viene elegida y la lista completa no se pide; el catálogo de tipos se puede leer con
   `talento.view` (crear/editar tipos sigue con `talento.penalties.manage` en el controller). Mismo
   arreglo para técnicos y vendedores, que veían un selector vacío y un error en esas pestañas.
2. **En su propia ficha, el supervisor veía la gestión de credenciales/fondos, préstamos y finiquito**
   (porque supervisa a alguien) y el servidor se la negaba. Las banderas de la ficha ahora usan
   «supervisor de esta persona», igual que los controllers.
3. **Cajas ODB**: el técnico puede registrar una baseline pero no veía la lista (403). Ahora la ve
   quien puede registrar.
4. **Proyectos del supervisor**: podía crear un proyecto pero no agregarle actividades (403 en el
   catálogo y en el alta). Ahora sí, con el mismo criterio que crear. «Otorgar bono» queda solo para
   `talento.projects.manage` y ya no le aparece al supervisor. Guardar y analizar el corredor no
   revisaban quién lo pedía (bastaba entrar a Talento): ahora exigen gestionar proyectos.
5. **Tablero del administrador**: la gráfica «Producción diaria» nunca se dibujó (la pantalla esperaba
   `series` y el servidor manda `categories` + una lista por tipo) y el servidor no contaba las órdenes
   que viven en `tasks`. Ahora se dibuja y las cuenta, igual que el pago; «Órdenes hoy» también.
6. El WhatsApp al técnico decía «Consulta el detalle en tu Portal de Colaborador» (retirado el 23-oct):
   ahora «en «Mi día» de Talento».
7. El tablero del supervisor ya no pide las listas de alta/edición de fichas (4 respuestas 403 en cada
   visita, sin efecto en pantalla).

Probado en la vista previa de la rama (navegador, sin guardar nada) y en el servidor dentro de una
transacción deshecha: supervisor crea proyecto/actividad/corredor (201/201/200) y no otorga bono
(403); técnico no toca proyectos (403) y sí ve cajas; vendedor no ve cajas (403); una orden validada
en `tasks` suma en la producción de su ficha y no en la de otra.

### Sin tocar

- El menú de Talento del administrador solo traía Colaboradores, Órdenes, Liquidaciones, Escalafón,
  Mapa en vivo y Sitios de checada (recorte del 30-sep); resuelto abajo.
- Fuera de Talento: cada página pide `/administracion/documentation/documentation_menu/tree` y da 403
  a quien no es administrador (sin efecto en pantalla).
- La campanita de «Nueva tarea asignada» abría la tarea en Scheduling; resuelto abajo.

## 2026-10-27 — Las pantallas generales de Talento vuelven al menú

Decidido: el menú de Talento enlaza otra vez las 7 pantallas de gestión general que solo se abrían
escribiendo la dirección, cada una con su permiso: Academia (`talento.academy.manage`, no `.view`,
porque técnicos y Mostrador tienen `.view` y ya tienen su Academia en la ficha), Credenciales
(`talento.credentials.view`), Penalizaciones (`talento.penalties.view`), Reglas de desempeño
(`talento.reglas_desempeno.view`), Documentos pendientes (`talento.documentos.ver-todos`), Paquetes de
documentos (`talento.expediente.paquetes.manage`) y Configuración de evidencias
(`talento.config.evidencias`). Hoy todos esos permisos son solo de los roles de administración.
Probado en navegador: el administrador ve los 7 enlaces y las 7 pantallas abren sin errores; el menú
de Mostrador, técnico, supervisor y vendedor no cambia. Rama `feat/talento-menu-pantallas-generales`.

## 2026-10-27 — La campanita abre la orden en «Mi día»

Pedido: que el aviso «Nueva tarea asignada» de una orden de Talento abra la orden en «Mi día».

Al probarlo salieron dos cosas más: (1) en las pantallas de Talento la campanita **no aparecía**: el
topbar solo la dibuja cuando el controlador pasa `$notifications`, y ningún controlador de Talento lo
hace, así que el técnico, que vive en su ficha, nunca la veía; (2) aunque la viera en otra pantalla,
el clic iba a `/scheduling/task/read-notification/{id}`, que exige `task_view_full_task`, y al técnico
el sistema lo regresaba al inicio en silencio.

Qué cambia (rama `feat/talento-campanita-mi-dia`):
- `Task::sendNotifications()`: si la tarea es una orden de Talento (`talento_type_id`), el aviso apunta
  a `/talento/aviso/{id}` (con `talento.view`); las demás tareas siguen igual.
- `TalentoWorkOrderController::abrirAviso()`: solo abre avisos propios (404 si no); si la orden está
  asignada a quien hace clic y tiene ficha activa, lo marca leído y abre
  `/talento/colaborador/{ficha}?tab=mi_dia&ot={id}`; si no, sigue a Scheduling como antes. El criterio
  vive en `Actor::urlOrdenEnMiDia()`, que también usa `TaskController::readNotification()` para los
  avisos viejos que todavía apuntan a Scheduling.
- «Mi día» abre la orden que llega en `?ot=` (de hoy o de otro día); al volver a la lista se quita de
  la dirección para que recargar no la vuelva a abrir.
- El topbar dibuja la campanita siempre; donde no trae los avisos del servidor los pide al cargar.
- Sin cambio: la campanita de Scheduling solo enseña avisos de tareas que **empiezan hoy**, así que
  el aviso de una orden de mañana aparece mañana.

Probado en la vista previa con una orden real (borrada al final): el Mostrador la crea para hoy a las
10:30; el técnico, en la pestaña Asistencia de su ficha, ve la campanita con 1, hace clic y cae en
«Mi día» con «Orden #… Pendiente · Iniciar orden»; «Volver» quita la orden de la dirección y recargar
ya no la reabre. En el servidor: el técnico asignado va a «Mi día», el administrador no asignado sigue
a Scheduling, una tarea que no es de Talento sigue a Scheduling y un aviso ajeno da 404. La campanita
sale sin errores en Talento, Clientes y Scheduling (el Dashboard principal usa otra plantilla y no la
tiene, igual que antes).

