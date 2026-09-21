## 2026-09-21 — Talento Fase A: ranking admin-wide de ventas y prospectos

**Contexto:** comparación Vendedores vs Talento (plan aprobado
`ethereal-fluttering-simon.md`). La única brecha sin bloqueo de diseño: Talento
solo tenía "Mis ventas"/"Mis prospectos" (self-scoped); no existía una vista
admin-wide (todos los colaboradores-vendedor) como sí tiene Vendedores.

**Backend** (`TalentoVentasController.php`):
- `rankingAdmin()` — mismo criterio que `SaleController::rankingSales()` de
  Vendedores (`client_main_information.seller_id = users.id`, sin filtro), vía
  `DB::table()` (no el modelo Eloquent — `ClientMainInformation` trae 6 accessors
  pesados en `$appends` que no aplican a un agregado). Acepta `?range[]=` opcional.
- `prospectosAdmin()` — misma query que `PortalTecnicoController::prospectos()`
  (self-scoped) sin el filtro por `owner_id`, **paginada** (25/página — 2206 filas
  reales en dev, mandarlas todas de un golpe no era viable, se corrigió tras
  verlo en el primer screenshot). Filtro opcional `?user_id=`.
- Rutas: `GET /talento/api/ventas/ranking`, `GET /talento/api/prospectos` (+
  `GET /talento/ventas/ranking` para la página). Reusa el permiso existente
  `talento.ventas.view` (no se creó uno nuevo). Registradas también en
  `module.json` (menú + catálogo de endpoints), siguiendo la convención
  declarativa del sidebar dinámico.

**Frontend**: `TalentoVentasRanking.vue`, tema Torre desde el día uno (`.tc-wrap`
+ `darkMode` de `hook/appConfig.js`, `.tc-card`/`.tc-status`/`.tc-btn`/`.tc-select`
— sin necesitar el pase de estilos de la Fase B, porque nace ya con el tema).

**Verificado:**
- Paridad exacta contra Vendedores: mismo rango de fechas, `SaleController::
  rankingSales()` vs `TalentoVentasController::rankingAdmin()` — mapas
  nombre→ventas idénticos byte a byte (`GUADALUPE: 162, DIANA: 113, ARIANA: 31...`).
- Playwright real (usuario `david_marsal`): pantalla carga, ranking 23 filas,
  prospectos pagina correctamente (25/página, 89→3 páginas al filtrar por
  vendedor), captura visual en claro y oscuro — el tema Torre se ve consistente
  en ambos.
- `npm run dev` compiló limpio en cada paso.

Rama `feat/talento-fase-a-ranking-admin`, mergeada a `main`.
