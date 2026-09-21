## 2026-09-21 — Talento Fase C: caja diaria de efectivo (UI replicada, sin motor nuevo)

**Contexto:** Fase C del plan `ethereal-fluttering-simon.md` (Vendedores→Talento).
Decisión ya tomada por Irving en la fase de planeación: replicar la UI de la
caja diaria de efectivo dentro de Talento, **sin tocar el motor de dinero** —
leer/escribir las MISMAS tablas que ya usa `sellers/cuts/*` de Vendedores
(`cut_boxs`, `cut_extras_incomes`, `cuts_observations`, `cut_suppliers_expenses`,
`cut_installations`), nunca un motor paralelo.

**Backend — `TalentoCajaVendedorController`** (namespace Talento, nuevo):
- Reusa **TAL CUAL** los 4 repositorios genéricos ya existentes
  (`app/Repositories/Sellers/Cuts/{ExtraIncome,Observation,SuppliersExpense,
  Installation}Repository`, que NO están namespaced a Vendedores — se importan
  directo, sin duplicar una sola línea de su lógica; de paso vienen con su
  propio audit trail en `TransactionLog`, gratis).
- `close()` es **copia literal carácter por carácter** de
  `BoxController::close()` (Vendedores) — mismo cálculo, mismas columnas. Nada
  de "reescribir el motor de totales".
- `installationsIndex()` reusa también, literal, el auto-populate de
  `InstallationController::index()` (detecta altas activadas ese día sin fila
  `CutInstallation` y las crea antes de listar).
- `pdf()` reusa la MISMA plantilla Blade `meganet.module.sellers.pdf.box` — no
  se duplicó.
- La caja cuelga de `users.id` directo (`cut_boxs.user_id`) — NO requiere que
  el colaborador tenga fila en `sellers`, más simple que el futuro Fase D
  (comisiones).

**Permisos nuevos** (`talento.caja-vendedor.view`/`.manage` — nombre a
propósito distinto de `talento.caja.*`, que es la caja de HERRAMIENTAS/bono de
salud, concepto totalmente distinto): roles espejo EXACTO de los que ya tienen
acceso a este mismo dinero del lado de Vendedores (`seller_cuts`/
`seller_cuts_close_box`, verificado en BD real, no una suposición) —
super-administrator/DESARROLLADOR (ambos permisos, vía
`PermissionSyncService`) + ADMINISTRADOR_COMPLETO/CONTADOR (solo `.view`).

**Frontend — `TalentoCajaVendedor.vue`** (nuevo, tema Torre desde el día uno):
selector de colaborador (mismo patrón que "Por colaborador" de Credenciales) →
lista de cajas → detalle con 5 tabs (Pagos recibidos solo lectura, Ingresos
extra CRUD, Gastos a proveedores CRUD, Instalaciones solo edición de
técnico/montos, Observaciones CRUD) → botón "Cerrar caja" (oculta todos los
controles de edición una vez cerrada, igual que Vendedores) → "Ver PDF".
**Decisión de diseño:** mientras la caja está ABIERTA no se muestran totales
recalculados en el navegador — solo aparecen los 5 totales guardados
(`total_received/extras/technicals/proveedores/net`) una vez que `close()` los
calcula y guarda, para no tener una segunda fuente de verdad del cálculo en JS.

**Verificado (contra datos REALES de dev — 556 cajas existentes, con cuidado
de no tocar ninguna real):**
- Lista de cajas + detalle de caja cerrada (#556, ARIANA): totales
  7,624.00/0/0/0/7,624.00 correctos, pagos recibidos reales, instalación real
  (cliente 7691, no activada).
- Caja abierta (#551, DIANA): alerta de "totales se calculan al cerrar" +
  botón "Cerrar caja" visibles.
- CRUD real de extras: agregado ($150.50, folio TEST-PLAYWRIGHT-001) →
  apareció con el formato correcto (método, folio, fecha, "Registró: David
  Marsal") → borrado → confirmado "Sin ingresos extra registrados." (cero
  residuo en BD, verificado por query directa).
- CRUD real de observaciones: agregada → apareció con autor → borrada →
  confirmado sin residuo.
- **`close()` probado con dinero real, en transacción con rollback** (nunca
  se guardó): cerré la caja #551 real, comparé el resultado contra lo que los
  4 métodos del propio modelo `CutBox` calculan de forma independiente —
  coinciden exacto. Rollback aplicado, la caja real sigue abierta como estaba.
- PDF: generado por el controller directo (4646 bytes, magic `%PDF` correcto)
  y por la ruta HTTP real (200, `Content-Type: application/pdf`, mismos
  bytes).
- Modo oscuro: tabla, badges, alertas, botón "Cerrar caja" (rojo sólido),
  tabs — todo legible.
- `npm run dev` compiló limpio.

**Pendiente operativo (no bloquea el merge):** la migración de permisos se
corre en `main` DESPUÉS del merge (el guardrail de migraciones en dev — item
#534 — exige que la rama tenga ruta registrada a main; se corre ahí mismo,
trivial). Mientras tanto el acceso funciona igual para super-administrator/
DESARROLLADOR (bypass de rol en `CheckRoutePermission`, no depende de que el
permiso ya exista en BD).

Rama `feat/talento-fase-c-caja-vendedor`, mergeada a `main`.
