## 2026-10-22 — Talento: solo se muestran los colaboradores activos

Pedido: "en talento los colaboradores que deben mostrarse únicamente los activos". Decisiones del
mismo día: los inactivos quedan ocultos con un filtro aparte para poder reactivarlos; y quien está
activo en Talento pero con su cuenta del sistema desactivada no cuenta como activo.

### Qué había

La lista de colaboradores mostraba todos por defecto (31 en dev: 16 activos y 15 inactivos), y
varios selectores pedían la lista sin filtrar (asignar órdenes, calcular liquidación, rutas,
compensación, supervisor, ficha anterior/siguiente). Además, tres colaboradores estaban activos en
Talento con su cuenta desactivada en Administración: dos personas y MEGAISP (la cuenta de sistema
que firma las penalizaciones que confirma un administrador sin ficha).

### Qué cambia (rama `fix/talento-solo-activos`)

- `TalentoColaborador::activos()`: activo en Talento y con la cuenta del sistema activa.
  `noActivos()` es el resto. La lógica no cambia (entrada a la app, reglas semanales, liquidación,
  `Actor`): siguen mirando solo el estado de Talento.
- `/talento/api/colaboradores` y `/talento/api/mi-equipo`: sin `status` (o con `active`)
  devuelven solo activos; con `status=inactive`, el resto. Con eso todos los selectores de
  Talento quedan en activos sin tocarlos uno por uno.
- Lista de colaboradores: el filtro dice "Activos" (por defecto) y "Ver inactivos"; quien tiene la
  cuenta desactivada lleva la etiqueta "Cuenta desactivada".
- Usan la misma regla: la tarjeta "Colaboradores activos", el escalafón, el desempeño y la serie
  del War Room, su tarjeta de KPI, los participantes de un proyecto y el equipo del portal y la app.

### Pruebas (base de dev)

Lista: 31 → 13 por defecto; "Ver inactivos" 18 (los 15 inactivos más los 3 con la cuenta
desactivada, con su etiqueta). Tarjeta, escalafón, War Room y equipo de la app: 16 → 13. Revisado
también en el navegador.
