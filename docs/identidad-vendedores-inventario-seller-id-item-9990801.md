# Item #9990801 — Diagnóstico de identidad: sellers / talento_colaboradores / users, y a qué apunta `seller_id` (SOLO LECTURA)

Fase 1 de #9990778 (Identidad unificada). **Este reporte es 100% solo-lectura**: ninguna consulta
de las de abajo escribe en BD, no hay migraciones, no hay cambio de código fuera de `docs/`.

## 1. Inventario de las 3 tablas base

| Tabla | Filas (dev) | Columnas clave | Identidad |
|---|---|---|---|
| `sellers` | 28 | `id`, `user_id`, `status_id`, `type_id`, `balance`, `range` | `id` es la identidad del **motor de comisiones/ventas legado** (`Commission`, `Sale`, `TransactionSeller`, etc.) |
| `talento_colaboradores` | 28 | `id`, `user_id`, `type` (único valor real: `interno`), `department`, `supervisor_id`, `level_id`, `puesto_id` | `id` es la identidad del **motor Talento** (nómina/compensación/portal de colaborador) |
| `users` | 4792 | `id`, `login_user`, `is_seller`, `is_system` | `id` es la identidad de **autenticación** — el único denominador común entre `sellers` y `talento_colaboradores` |

`sellers` y `talento_colaboradores` **no se referencian entre sí directamente** — no hay columna
`seller_id` en `talento_colaboradores` ni `colaborador_id` en `sellers`. El único puente es
`sellers.user_id == talento_colaboradores.user_id` (ambos apuntan a la misma fila de `users`). Este
puente ya está implementado y en uso — `Actor::seller()`
(`app/Modules/Addons/Talento/Support/Actor.php:58-66`, `Seller::where('user_id', $this->user->id)`)
y, con el mismo patrón, `TalentoEmbajadoresController::sellerData()` (ver item #123, ya documentado
en `docs/talento-vendedores-bridge-item-123-verificacion.md`).

## 2. A qué apunta REALMENTE `seller_id` en cada tabla que lo usa

Búsqueda exhaustiva en `information_schema.COLUMNS` (no grep de código — esto es autoritativo).
Existen **12 tablas** con una columna literal `seller_id`:

| Tabla | FK declarada | ¿A qué apunta en la práctica? | Filas (dev) |
|---|---|---|---|
| `commissions` | → `sellers.id` | `sellers.id` (consistente) | 0 |
| `commissions_rules_sellers` | → `sellers.id` | `sellers.id` (consistente) | 12 |
| `discounts` | → `sellers.id` | `sellers.id` (consistente) | 0 |
| `history_sellers_rules` | → `sellers.id` | `sellers.id` (consistente) | 38 |
| `payment_by_rule` | → `sellers.id` | `sellers.id` (consistente) | 72 |
| `payments_sellers` | → `sellers.id` | `sellers.id` (consistente) | 0 |
| `sales` | → `sellers.id` | `sellers.id` (consistente) | 0 |
| `transactions_sellers` | → `sellers.id` | `sellers.id` (consistente) | 2 |
| `talento_comisiones_espejo` | *(sin FK)* | `sellers.id` — modelo `belongsTo(Seller::class,'seller_id')`, junto con `colaborador_id`→`talento_colaboradores.id` y `user_id`→`users.id` en la MISMA fila (tabla puente triple, ver §4) | 0 |
| `talento_reconciliacion_log` | *(sin FK)* | `sellers.id` — mismo patrón, junto con `colaborador_id` | 161 |
| **`whatsapp_conversations`** | *(sin FK)* | ⚠️ **`users.id`, NO `sellers.id`** — ver §3.2 | 0 |
| **`client_main_information`** | *(sin FK)* | ⚠️ **AMBIGUO — el propio modelo declara dos significados distintos sobre la misma columna** — ver §3.1 | 4195 de 5611 |

Los 8 primeros son homogéneos: `seller_id` = `sellers.id`, con FK real en BD. Las dos tablas de
Talento (`talento_comisiones_espejo`/`talento_reconciliacion_log`) siguen esa misma convención pero
sin FK declarada (aceptable: son tablas de auditoría/puente, no de negocio transaccional). **Las
últimas dos son los hallazgos que importan** — su nombre de columna es igual, pero su significado
NO es el mismo.

### 3.1 `client_main_information.seller_id` — la columna con doble personalidad

El propio modelo `ClientMainInformation` declara **dos relaciones Eloquent sobre la misma columna**:

```php
// app/Modules/Core/Clientes/Models/ClientMainInformation.php
public function user_seller() { return $this->belongsTo(User::class, 'seller_id'); }   // línea 93-96
public function seller()      { return $this->belongsTo(Seller::class, 'seller_id'); } // línea 118-121
```

Evidencia de cuál es el significado REAL (el que usa el código vivo), por triangulación de 3
consumidores independientes:

1. `getSellerNameAttribute()` (línea 200-203) usa `user_seller()->first()->name`, no `seller()`.
2. `ClientDatatableHelper.php` (líneas 74-76, 524-526) hace eager-load de `client_main_information.user_seller`, nunca de `.seller`.
3. `StaticsController.php:136` hace `$query->join('users', 'users.id', '=', 'client_main_information.seller_id')` — join directo contra `users`, prueba definitiva de que en producción ese valor se trata como `users.id`.

Confirmado también por los **datos reales**: de los 24 valores distintos que existen hoy en
`client_main_information.seller_id`, **22 son `users.id` válidos** (valores como 4128, 4212, 4122,
4429 — rango de `users.id`) y solo **4 coinciden por casualidad con un `sellers.id` válido** (los
`sellers.id` van de 1 a 50, así que valores bajos como 3, 4, 8, 9 caen en ambos rangos por
coincidencia numérica, no por diseño). El valor más frecuente, `seller_id=8` (2127 clientes), es
`users.id=8` = **Irving** (dueño, cartera "sin vendedor asignado" cae en su cuenta) — **no**
`sellers.id=8` (que es RENE).

**Conclusión de este hallazgo:** `client_main_information.seller_id` es, en la práctica, un
`users.id`. La relación `seller()` (→ `sellers.id`) que el modelo también declara es código que,
para el 92% de los valores reales de la columna, no resuelve nada (busca un `sellers.id` que no
existe en ese rango) — es un landmine para cualquier desarrollador nuevo que la use esperando un
`Seller` real. Un solo consumidor real de esa relación ambigua-hermana existe, pero es en OTRA
tabla con OTRO nombre de columna (`crm_lead_information.owner_id`, ver más abajo) — no en
`client_main_information.seller_id`.

### 3.2 `whatsapp_conversations.seller_id` — es `users.id`, con conversión explícita cuando hace falta `sellers.id`

```php
// app/Modules/Addons/WhatsAppAgent/Models/WhatsAppConversation.php:53
public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
```

Y el código que la consume lo confirma sin ambigüedad:
- `WhatsAppPanelController.php:302`: `$conversation->seller_id === $user->id` (comparación directa contra el id de usuario autenticado).
- `WhatsAppContactMatcherService.php:48`: la popula copiando `$main->seller_id` (el `seller_id`, ya ambiguo, de `client_main_information` — hereda la misma semántica `users.id`).
- `WhatsAppCrmService.php:118`: cuando SÍ necesita un `sellers.id` real (para crear una comisión), hace la conversión explícita: `Seller::where('user_id', $conversation->seller_id)->value('id')`.

Esta última línea es la prueba más clara de todo el inventario: el propio código necesita traducir
de un `seller_id` (que es `users.id`) a un `sellers.id` real antes de poder usarlo para comisiones.

### 3.3 Columna hermana con otro nombre: `crm_lead_information.owner_id`

No se llama `seller_id`, pero cumple el mismo rol y apunta al mismo lugar que las 8 tablas
homogéneas del §2:

```php
// app/Modules/Core/CRM/Models/CrmLeadInformation.php:26-29
public function seller() { return $this->belongsTo(Seller::class, 'owner_id'); }
```

Aquí sí es consistente: `owner_id` → `sellers.id`. Queda fuera del alcance literal del item (que
pedía específicamente `seller_id`), pero es relevante para #9990778 porque es una tercera
convención de nombre (`owner_id`) para el mismo concepto de "vendedor dueño del registro".

## 4. El puente que ya existe (y funciona) entre los tres mundos

Las tablas `talento_comisiones_espejo` y `talento_reconciliacion_log` ya llevan, en la misma fila,
las tres identidades simultáneamente: `colaborador_id` (Talento) + `user_id` (auth) + `seller_id`
(motor legado de comisiones). Es la base de datos que hoy resuelve la reconciliación entre el motor
viejo (`sellers`/`CommissionRule`/`TransactionSeller`) y el nuevo (Talento) — ver
`app/Modules/Addons/Talento/Console/ConciliarComisionesVendedorCommand.php` y
`ReconciliarComisionesEspejoCommand.php`. `talento_reconciliacion_log` tiene 161 filas reales;
`talento_comisiones_espejo` está en 0 (construida, sin poblar aún en dev).

## 5. Duplicados / inconsistencias que requieren decisión de Irving

### 5.1 — 6 `sellers` cuyo usuario vinculado tiene `users.is_seller = 0`

`sellers` tiene 28 filas, todas con `user_id` válido y sin duplicados de `user_id`. Pero solo 22 de
esos 28 usuarios tienen `is_seller = 1`:

| `seller_id` | `user_id` | `login_user` | Nombre | `is_seller` |
|---|---|---|---|---|
| 12 | 9 | GUADALUPE | GUADALUPE | 0 |
| 16 | 3695 | Suriromero | Surisadai Marcos | 0 |
| **25** | **3986** | **Meganet8f3d7255** | JUAN | 0 |
| **23** | **3988** | **Meganetdf642fa2** | MELANEE GALILEA | 0 |
| 24 | 4032 | sergio | SERGIO | 0 |
| 47 | 4668 | fernanda | MARIA FERNANDA | 0 |

Dos de estos seis (`Meganet8f3d7255`/`Meganetdf642fa2`) son **cuentas espejo** — coinciden
exactamente con el patrón `^Meganet[0-9a-f]{8}$` documentado en este mismo `CLAUDE.md` (Fase 2.5,
"395 cuentas espejo... detección por selector... patrón `^Meganet[0-9a-f]{8}$`"). Verificado:
`estado=activo`, `is_seller=0`, **0 roles Spatie** hoy (la sanitización de Fase 2.5 ya les quitó los
roles de staff contaminados) — pero esa limpieza **nunca tocó `sellers`**, así que ambas siguen
teniendo una fila de vendedor viva (`sellers.id=25` y `23`) con historial de comisiones potencial.
Es exactamente el tipo de residuo que #9990778 necesita decidir: ¿se dan de baja esas 2 filas de
`sellers` como parte de la limpieza de espejos, o se dejan (huérfanas pero inertes)?

Los otros 4 (`GUADALUPE` user 9, `Suriromero`, `sergio`, `fernanda`) **no** son cuentas espejo — son
usuarios reales con `login_user` normal. `GUADALUPE` (seller_id=12) ya fue investigada aparte en
`docs/vendedores-guadalupe-duplicado-item-9990472-verificacion.md` (persona real, distinta de la
otra "Guadalupe" seller_id=48) — el único dato nuevo aquí es que su `is_seller` está en 0 pese a
tener una fila `sellers` activa; probablemente un simple desfase del flag (no se marcó al
convertirla a vendedora), sin relación con el problema de espejos.

### 5.2 — 6 `sellers` activos sin contraparte en `talento_colaboradores`

Vía el puente `sellers.user_id == talento_colaboradores.user_id`: 22 de 28 sellers tienen su
colaborador correspondiente; 6 no:

| `seller_id` | `user_id` | `login_user` | Nombre |
|---|---|---|---|
| 5 | 4 | kathya | KATHYA |
| 6 | 6 | TERE | MARIA TERESA |
| 28 | 8 | Irving | IRVING |
| **25** | **3986** | **Meganet8f3d7255** | JUAN |
| **23** | **3988** | **Meganetdf642fa2** | MELANEE GALILEA |
| 26 | 4070 | kevin | Kevin Casimiro |

`Irving` (dueño) y `TERE` (staff administrativo de mostrador, mencionada extensamente en este
`CLAUDE.md`) son casos esperables: personal que opera el sistema pero no está dado de alta como
"colaborador de campo" en Talento. `kathya`/`kevin` habría que confirmar con Irving si son personal
activo sin alta en Talento o cuentas descontinuadas. Los 2 espejos repiten aquí el mismo hallazgo
del punto 5.1.

### 5.3 — Tablas del motor legado vacías en dev (contexto, no bloqueante)

`sales`, `commissions`, `discounts` y `payments_sellers` están en **0 filas** en dev pese a tener FK
sana a `sellers.id`. Esto es consistente con el hallazgo ya cerrado en
`docs/vendedores-sales-commissions-prospects-item-9990471-verificacion.md` (`sales` descontinuada,
`commissions` con 566K filas de detalle huérfanas por un truncado parcial de 2024-07-12). No es
nuevo, se cita aquí solo para que la Fase de migración de #9990778 no asuma que esas tablas tienen
datos que migrar.

## 6. Tabla de correspondencia — resumen ejecutivo para #9990778

| Concepto | Identidad canónica | Puente a `users.id` |
|---|---|---|
| Motor de comisiones/ventas legado | `sellers.id` | `sellers.user_id` |
| Motor de nómina/compensación Talento | `talento_colaboradores.id` | `talento_colaboradores.user_id` |
| Autenticación / identidad única real | `users.id` | — (es el centro) |
| `client_main_information.seller_id` | **es `users.id`**, no `sellers.id` (pese a que el modelo también expone una relación muerta hacia `sellers.id`) | directo |
| `crm_lead_information.owner_id` | `sellers.id` (nombre distinto, mismo concepto que las 8 tablas homogéneas) | vía `sellers.user_id` |
| `whatsapp_conversations.seller_id` | **es `users.id`**, con conversión manual a `sellers.id` cuando se necesita para comisiones | directo, con traducción explícita en `WhatsAppCrmService` |
| `talento_comisiones_espejo` / `talento_reconciliacion_log` | ya cargan las 3 identidades en la misma fila (bridge real, ya construido) | — |

## 7. Qué queda pendiente de decisión de Irving (para cuando #9990778 retome)

1. Las 2 filas de `sellers` (`id=23,25`) colgando de cuentas espejo ya sanitizadas en roles/permisos
   — ¿se dan de baja o se dejan inertes?
2. Si el flag `users.is_seller` debe corregirse para los 4 casos reales del §5.1 (GUADALUPE/
   Suriromero/sergio/fernanda) como parte de la limpieza, o si es simplemente un flag decorativo sin
   consumidores que dependan de él (no se investigó su uso en código — fuera del alcance literal de
   este item, que era sobre `seller_id`).
3. Si vale la pena, en la migración de #9990778, **renombrar o documentar in-code** la relación
   `ClientMainInformation::seller()` (que hoy es código-trampa: existe, compila, pero no resuelve
   nada real para el 92% de los valores) para evitar que un futuro desarrollador la use esperando un
   `Seller`.

Sin cambio de código — este item es 100% de solo lectura, según su propio alcance.
