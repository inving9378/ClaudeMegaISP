## 2026-10-24 — Academia: los cursos se muestran por rol

**Pedido:** "Seguridad en campo (NOM-001), Fusión óptica y Calidad de caja ODB son para los técnicos,
no deberían mostrársele al mostrador; los cursos deben mostrarse por rol: si son generales, para todos,
y los específicos de cada rol, solamente a ese rol".

**Antes:** el curso solo tenía un texto libre de departamento («técnicos», «general») que se mostraba
como etiqueta; nadie filtraba por él: todos veían todos los cursos.

**Ahora**
- Columna nueva `talento_courses.roles` (JSON, migración aditiva
  `2026_10_24_130000_add_roles_to_talento_courses`): vacía = curso general (todos); con roles = solo
  para quien tenga alguno. Al migrar, los cursos con departamento «técnicos» quedan para los roles de
  técnico que existan (TECNICO, TECNICO_INSTALADOR, TECNICO_PLANTA); los demás, generales.
  `department` se conserva tal cual.
- **Catálogo** (pestaña Academia de la ficha y `/talento/academia`): cada quien ve los generales y los
  de sus roles. La tarjeta dice «Para: Todos» o el puesto («Para: Técnico»). Se quitó el filtro por
  departamento.
- **Abrir un curso o su examen** que no le toca, aunque se escriba la dirección: «no encontrado».
  Quien administra la Academia (`talento.academy.manage`) puede abrir cualquiera.
- **Avance** (resumen de la ficha y su espejo en `CertificationService`): cuenta solo los cursos de
  esa persona (el Mostrador: 0 / 2).
- **App** (`/portal/academia`): la misma regla con los roles de la persona.
- **Admin:** la pestaña Admin lista todos los cursos (antes reusaba la lista del catálogo). En el
  formulario del curso, «Departamento» se cambió por **«Para quién»**: «Todos (curso general)» o
  «Solo estos roles» con los puestos (`GET /talento/api/courses-roles`, permiso
  `talento.academy.manage`; no se ofrecen los roles de sistema ni `client`).
- Manual («Academia»): ahora para todos los colaboradores y explica «Para:» y «Para quién».

**Pruebas** (copia desechable de la base, `megaisp_dryrun`, ya borrada; dev no se tocó): la migración
aplica, se deshace y vuelve a aplicar. Navegador: 16/16 — Mostrador ve solo los 2 generales, su avance
dice 0 / 2 y el curso de técnicos por dirección da 404; el técnico ve los 5; en Admin salen los 5 y al
marcar Mostrador en «Calidad de caja ODB» el Mostrador ya lo ve. App: la misma lista.

**Al mezclar a main:** `php artisan migrate` en dev; en producción corre sola con el despliegue.

## 2026-10-24 — Mezclado a main + captura del manual

- Mezclado a main (856c61ac); `php artisan migrate` en dev: los 3 cursos de técnicos quedan «Para: Técnico» (en dev solo existe el rol TECNICO de los tres), los 2 generales «Para: Todos».
- Build de producción + `view:clear`/`route:clear` + seeder del manual de Talento corrido en dev.
- Revisado en el navegador contra dev (sin guardar cambios): Mostrador ve solo los 2 generales y su avance dice 0 / 2; un curso de técnicos por dirección da 404; el técnico ve los 5; Admin lista los 5 y el formulario trae «Para quién».
- Captura `public/images/manual/talento/academia.png` retomada con la vista de un técnico (etiquetas «Para: Técnico» / «Para: Todos», sin el filtro de departamento); nombre de usuario cambiado por «Ejemplo».

## 2026-10-24 — La etiqueta vuelve a decir «general»

- Pedido: «en vez de general pusiste para todos, déjalo como estaba antes».
- Los cursos generales vuelven a mostrar la etiqueta **«general»** (verde, como antes) en el catálogo y en la pestaña Admin; los de un puesto siguen con «Para: Técnico». `TalentoCourse::para` devuelve `general` cuando el curso no tiene roles.
- En el formulario, la opción pasa a **«General (para todos)»** (antes «Todos (curso general)»).
- Manual (texto + captura `academia.png`) al día. Revisado en el navegador: Mostrador, técnico y Admin, sin errores.
