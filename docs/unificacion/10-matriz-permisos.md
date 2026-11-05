# 10 · Matriz de permisos efectivos por perfil (UNI-T B2, #869)

Documento agregado: solo conteos y nombres técnicos, sin personas. Los datos con ids viven en
`storage/app/uni/permisos/` (fuera del repo) y se regeneran con:

```bash
php artisan uni:matriz-permisos --doc    # solo lectura; no modifica permisos ni roles
```

Archivos exportados: `matriz_cuenta_permiso.csv` (cuenta × permiso con origen `rol:X` / `directo`),
`permiso_rutas.csv` (rutas que abre cada permiso según `config/route_permission.php`),
`lectura_abre_escritura.csv`, `directos_clasificacion.csv`, `rol_conteos.csv`.

Las observaciones son [DEV] (BD de desarrollo, 2026-10-26) o [código]. Producción no se consultó.

## 1. Estado actual por perfil [DEV]

| Perfil | Roles | Cuentas | Permisos | de lectura | de escritura |
|---|---|---|---|---|---|
| dirección | super-administrator, DESARROLLADOR, Super Administrador | 5 / 4 / 2 | 823 / 823 / 413 | 269 / 269 / 166 | 372 / 372 / 164 |
| administrador | Administrador, ADMINISTRADOR_COMPLETO | 2 / 0 | 128 / 531 | 49 / 180 | 47 / 234 |
| supervisión | SUPERVISOR_MOSTRADOR | 0 | 232 | 84 | 102 |
| mostrador | Mostrador | 5 | 224 | 80 | 97 |
| vendedor | Vendedor | 28 | 70 | 38 | 15 |
| técnico | TECNICO | 7 | 82 | 51 | 13 |
| almacén | Almacen | 1 | 40 | 24 | 7 |
| cliente | client | 1337 | 7 | 2 | 0 |

«Lectura» y «escritura» se clasifican por el nombre del permiso (tokens `view/ver/list…` vs
`add/edit/delete/manage…`); los que no encajan en ninguno no suman a ninguna columna.

Verificación de conteos (reproducible):

```sql
SELECT r.name, COUNT(*) FROM role_has_permissions rp JOIN roles r ON r.id = rp.role_id GROUP BY r.name;
SELECT COUNT(*) FROM model_has_permissions WHERE model_type LIKE '%User';   -- 5243 [DEV]
SELECT COUNT(*) FROM role_has_permissions;                                   -- 3691 [DEV]
```

## 2. Permisos puntuales del alcance [DEV]

| Permiso | Roles que lo dan | Directos (Mostrador / Vendedor) |
|---|---|---|
| `task_edit_task` | administración, Mostrador, Vendedor, TECNICO, Almacen, SUPERVISOR_MOSTRADOR | 4 de 5 / 26 de 28 |
| `task_view_full_task` | solo administración y dirección | 3 de 5 / 13 de 28 |
| `talento.work_orders.manage` | super-administrator, Mostrador, DESARROLLADOR | 0 de 5 / 1 de 28 |
| `talento.media.view` | dirección, Mostrador, TECNICO, ADMINISTRADOR_COMPLETO | 0 / 0 |
| `talento.comisiones.view` | dirección, Mostrador, Vendedor | 0 / 0 |

- 89 permisos `talento.*` en BD frente a los declarados en `module.json` de Talento (deriva a depurar en Fase 4).
- `SUPERVISOR_MOSTRADOR`: 0 cuentas, 0 permisos `talento.*`.
- [código] `CheckRoutePermission` compara solo la ruta, no el método HTTP: un permiso de
  lectura que comparte ruta con una acción de escritura abre ambas.

## 3. Permisos de lectura que hoy abren rutas de escritura [código]

Detectado por patrón (`store/update/destroy/add/edit/assign/…`) sobre las rutas de cada permiso de
nombre «lectura». Son candidatos a revisar, no necesariamente fallas (algunas rutas son lecturas
con nombre de acción). 28 permisos, los de más rutas:

`config_view_sales` (29), `config_view_system` (23), `task_view_full_task` (9),
`config_view_scheduling` (9), `config_view_finance` (8), `config_view_tools` (8),
`admin_view_meganet` (8), `config_view_network_management` (6), `smart_import_view` (5),
`config_view_google_maps` (4), `smart_export_view` (4), `olt_view` (3), `inventory_view_inventory` (3),
`talento.view` (3), y 14 más con 1–2 rutas (lista completa en `lectura_abre_escritura.csv`).

El caso que importa a Talento: `task_view_full_task` abre 9 rutas de escritura de tareas.

## 4. Clasificación de permisos directos [DEV]

Total de filas directas sobre cuentas: 5243 (incluye clientes con el rol base). Sobre las 41 cuentas
con roles de staff o directos:

| Perfil | Residuo de la copia rol→directo (Fase 3b) | A decidir |
|---|---|---|
| dirección | 2485 | 0 |
| administrador | 258 | 530 |
| mostrador | 594 | 525 |
| vendedor | 568 | 262 |
| técnico | 0 | 12 |
| sin rol de staff | 0 | 3 |

- **Residuo**: el mismo permiso ya lo da un rol de la cuenta → redundante; se retira en Fase 3b.
- **A decidir**: ningún rol de la cuenta lo da. No se puede saber por datos si es excepción
  legítima o concesión suelta; ver preguntas abajo. Ningún permiso se modificó.

## 5. Matriz PROPUESTA por perfil

| Perfil | Ve | Puede escribir |
|---|---|---|
| dirección | todo | todo (única que ve tableros económicos completos) |
| administrador | todo lo operativo y de configuración de su área | tareas, órdenes, clientes, finanzas operativas |
| supervisión | tareas y órdenes de su equipo, asistencia, validación | validar/reasignar en su equipo; sin configuración |
| **mostrador** | **solo sus tareas** (`task_view_full_task` NO por defecto) + agenda y clientes | captura de pagos, crear tareas, editar las propias |
| vendedor | sus prospectos, ventas y comisiones propias | sus prospectos y tareas asignadas |
| técnico | sus órdenes del día y su material | avance, evidencias y cierre de sus órdenes |
| almacén | inventario y custodia | movimientos de inventario |

Reglas de la propuesta:
1. **Mostrador ve solo sus tareas**: `task_view_full_task` queda solo en dirección/administración/supervisión;
   los 3 Mostrador y 13 Vendedor que lo tienen directo pasan a «a decidir».
2. Un permiso de lectura no debe abrir escritura: separar las rutas de escritura listadas en §3
   bajo permisos propios (cambio de código, fuera de este item).
3. El acceso se resuelve por rol; directos solo para excepciones documentadas (Fase 3b).

## 6. Preguntas para Irving

1. ¿Mostrador debe perder `task_view_full_task` directo y ver solo sus tareas? (propuesta: sí).
2. ¿Los 262 directos «a decidir» de Vendedor y los 525 de Mostrador se tratan como residuo
   (se retiran) o se revisan cuenta por cuenta?
3. ¿Se crea el perfil «supervisión» real (hoy `SUPERVISOR_MOSTRADOR` tiene 0 cuentas)?

### Respuestas de Irving (2026-10-30)

1. **1a** — Mostrador y Vendedor pierden el permiso directo `task_view_full_task` y ven solo sus tareas.
2. **2c** — Los directos «a decidir» de Vendedor (262) y Mostrador (525) se retiran en DEV con simulación previa y
   respaldo; se recorre cada perfil con los usuarios `prueba_*` y se devuelve al momento lo que alguien necesite.
3. **3a** — Se configura el rol `SUPERVISOR_MOSTRADOR` en DEV: ver y validar a su equipo, sin configuración
   (a quién se asigna lo decide Irving después).

Implementación (#973): `php artisan uni:aplicar-matriz --perfil=Mostrador|Vendedor [--rol-supervision]` (dry-run por
defecto; `--aplicar` respalda y guarda el json de reversión; `--revertir=<json>` restaura directos y quita los permisos
dados al rol). El retiro por perfil solo toca cuentas cuyo único rol de staff es ese perfil. Aplicarlo en DEV es #972.
