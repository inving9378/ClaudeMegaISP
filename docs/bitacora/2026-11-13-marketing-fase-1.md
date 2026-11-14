## 2026-11-13 11:50 — Marketing IA 360 · Fase 1 (rediseño ejecutivo + solo Meganet + acceso cerrado) · Resultado: APROBADA por el supervisor (espera el SÍ de Henry)

Equipo: supervisor (sesión principal) + agente-backend + agente-frontend + agente-qa en paralelo, con vigilante de 20 min
(ninguno se atoró). Worktree `/var/www/megaisp-henry-marketing`, rama `feat/marketing-360-equipo`. **Sin commit todavía.**
Contrato: `docs/marketing/fase-1-paquetes.md` (incluye el CAMBIO DE ALCANCE de Henry del 13-nov). Detalle de cada agente:
`docs/marketing/fase-1-avance-{backend,frontend,qa}.md` y `docs/marketing/fase-1-qa-reporte.md`.

### 1. Comandos que se corrieron (en orden, con su código de salida)
| Comando | Base | Salida |
|---|---|---|
| phpunit por archivo (los 18 de Fase 1 + los de Fase 0 tocados), bajo `flock` | `megaisp_test` | 0 en todos |
| `deploy:dry-run`-equivalente de QA con datos de permisos (`--with-data=permissions,roles,role_has_permissions,…`) | `megaisp_dryrun` | 0 |
| suite completa `vendor/bin/phpunit` (11:19–11:36) | `megaisp_test` | 2 (fallas = las de la línea base) |
| `migrate:fresh` → `migrate:rollback --step=2` → `migrate` | `megaisp_fresh_test` | 0 / 0 / 0 |
| compilación `mix --production` (3 veces, la última con todos los arreglos) | — | 0 |
| capturas Playwright (frontend 103, QA navegador) | `megaisp_test` vía `artisan serve --no-reload` | 0 |

La base `megaisp` solo se leyó (verificado al final: 5,020 usuarios, sin migraciones de esta fase).

### 2. Resultado de las pruebas
- **Suite completa: 2,713 pruebas · 139 fallan · 6 omitidas.** Las 139 fallas están en la línea base
  (`docs/marketing/fase-1-linea-base-fallas.txt`). **0 fallas nuevas.** (El aviso «No tests found in ClientTest» ya salía en la base.)
- **Pruebas nuevas de la Fase 1: 148**, todas pasan:
  - Backend (55): permisos faltantes 6, dashboard 13, campañas activas 5, navegación 5, retiradas 7, acceso sin `check_route_permission` 11, comando del equipo 5, migración de voz 3.
  - QA (86): acceso negado por defecto 18, permisos faltantes 12, rutas retiradas 12, KPIs 13, campañas activas 7, navegación 6, acceso 8, comando del equipo 10.
  - Frontend (7): las 20 vistas abren con su componente; ventas/sin empresa/sin sesión bloqueados.
- Pruebas de la Fase 0 ajustadas por el cambio de alcance (cada cambio marcado `[Fase 1 · B]` en el código con su razón): MatrizRoles, CapaEmpresaMiddleware, ContextoEmpresaApi/Backend, Aislamiento, EmpresasApi, EquipoApi, ExistsAcotado, CaminosSinSesion, RutasMarketingProtegidas (131 → 134 rutas). Ninguna exigencia se bajó: lo que antes era 200 para `ventas`/`marketing` ahora se exige 403.

### 3. Cobertura (tabla pieza → prueba; sin pcov)
| Pieza | Prueba |
|---|---|
| Regla de acceso (solo admin de Meganet) en `ResolverEmpresaActiva` | `AccesoNegadoPorDefectoTest` (las 134 rutas, 7 perfiles), `AccesoSinRutaPermiso*Test`, `MatrizRolesTest` |
| Sin `check_route_permission` + `auth` conservado | `RutasMarketingProtegidasTest`, `AccesoNegadoPorDefectoTest` (sin sesión nunca 200) |
| 13 permisos (migración) | `PermisosFaltantes*Test` |
| Funciones retiradas (410) | `Retiradas*Test` (forma exacta, sin IA ni HTTP, barrido sin 500) |
| Dashboard / campañas activas (dinero con bcmath) | `DashboardResumenBackendTest`, `DashboardKpisTest`, `CampanasActivas*Test` |
| Barra de navegación | `Navegacion*Test` |
| `marketing:equipo-fijar` | `EquipoFijarBackendTest`, `EquipoFijarCommandTest` |
| Columna `preferred_voice_model` | `MigracionVozNichoBackendTest` |
| ids no numéricos → 404 | prueba de barrido en `AccesoSinRutaPermisoBackendTest` |
| Pantallas rediseñadas | `PantallasMarketingTest` + 103 capturas |

### 4. Fallas encontradas y cómo se corrigieron
1. Cambiar de empresa aún guardaba otra marca en sesión → ahora siempre queda Meganet (`ContextoEmpresaController`).
2. El comando del equipo no daba los permisos de Video, Campañas IA, Voces y Nichos → ahora da todos los de Marketing (los `marketing.*` + los de `module.json`). Se excluye a propósito `manage-integrations` (es el Hub de llaves de todo LM).
3. Comparador de voces daba 500 en instalación limpia (columna sin migración; ya existía) → migración aditiva con guarda.
4. Letras en el id de la URL daban 500 en 58 rutas (ya existía) → 404.
5. Mensaje de «sin acceso» mandaba a «Empresas y equipo» (oculta) → «No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.»; la pantalla salía sin estilo → corregido (estilos en su sección del layout).
6. Supervisor: la cola de videos mostraba un costo en USD (ya existía) → se quitó de pantalla (todo el dinero en MXN).
7. Frontend: las vigencias de campañas salían un día antes (zona horaria) → corregido.

### 5. Revisión de seguridad
Greps sobre todo el diff: sin llaves ni secretos, sin `v-html`, sin `$guarded = []`, `selectRaw` solo con texto fijo, validación por FormRequest en las 3 rutas nuevas, `(float)` solo en porcentajes. El filtro anti-SQL global frena ataques en parámetros (403). Ningún login ni dato real en código, pruebas ni docs (usuarios de prueba ficticios `mkt_prev_*`). **Sin hallazgos abiertos.**

### 6. Acceso (reemplaza «aislamiento por empresa»)
Probado en **cada una de las 134 rutas**: sin sesión → nunca 200; con sesión sin equipo → 403; super-administrador o DESARROLLADOR sin fila → 403; fila `marketing` o `ventas` → 403; admin de otra empresa → 403; inactivo → 401 y cierra sesión; admin de Meganet → entra. Públicas por diseño (lista cerrada de 7: formulario público, webhooks con firma y seguimiento de campañas piloto) igual que antes.

### 7. Reglas del sistema
Sin IA nueva ni llaves nuevas (Brand Kit ya no captura llaves: manda a Integraciones); sin WhatsApp ni Sofía; sin mapas; nada se escribe en Meta; dinero en MXN con bcmath; campañas en otra moneda se cuentan aparte y no se convierten. Archivos: **todos** dentro de lo autorizado (módulo, `resources/js/components/module/marketing/**`, pruebas de Marketing, docs de Marketing). `app.js` no se tocó.

### 8. Archivos
73 archivos (sin contar `public/js`, que es el bundle compilado y no se versiona):
- Backend: 2 migraciones, `DashboardController`, `NavegacionController`, `DashboardMarketingService`, 2 Requests, `RespondeRetirada`, `EquipoFijarCommand`; modificados `routes.php`, `module.json` (sin subir versión), `ResolverEmpresaActiva`, `ModuleServiceProvider`, `ContextoEmpresaController`, 4 controladores viejos (retiradas), vista `sin-empresa`.
- Frontend: kit (`MktNavegacionModulo`, `MktDialogo`, formato/tokens), dashboard (3 paneles) y 19 pantallas rediseñadas.
- Pruebas: `tests/Feature/Marketing/Fase1/**` + ajustes de Fase 0. Docs: `docs/marketing/fase-1-*`, `plan-fases.md`, `guia-visual.md`.

**Funciones de Marketing retiradas (F1-3)** — siguen registradas y responden 410 con mensaje; ya no aparecen en pantalla:
- Estadísticas y plantillas viejas: `GET /marketing/stats`, `GET /marketing/templates`, `POST /marketing/templates/store`, `PUT|DELETE /marketing/templates/{id}`.
- Campañas viejas: `POST /marketing/campaigns/table`, `POST /marketing/campaigns/store`, `PUT|DELETE /marketing/campaigns/{id}`, `POST /marketing/campaigns/{id}/approve|pause|activate`; `GET /marketing/campaigns/{id}` lleva al Panel.
- Generador viejo de texto e imagen: `POST /marketing/content/generate-copy`, `generate-image`, `approve/{id}`, `reject/{id}`, `GET /marketing/content/campaign/{campaignId}`.
- Leads viejos: `POST /marketing/leads-legacy/table`, `GET /marketing/leads-legacy/{id}`, `POST …/{id}/qualify`, `PUT …/{id}/status`, `POST …/{id}/assign`.
- En pantalla: pestañas legado del Panel, diálogos viejos de campaña/plantilla, captura de llaves en Brand Kit, costos estimados en USD, pestañas vacías de la ficha de lead. «Nueva campaña» lleva al generador IA.

### 9. Cómo se ve distinto y cómo lo prueba Henry (en dev, después de integrar)
- Marketing abre en un **Panel ejecutivo**: 4 indicadores del mes (leads, conversión, gasto en MXN, costo por lead), embudo, campañas activas y accesos rápidos; barra de navegación propia del módulo arriba en todas las pantallas.
- Leads, Formularios, Conversaciones, Video (4), Campañas IA, Piloto, Publicación (4) y Brand Kit con el mismo diseño, en claro/oscuro y celular.
- Ya no hay selector de empresa ni «Empresas y equipo».
- Prueba rápida: entrar con su usuario → Panel; abrir Leads (antes daba «sin permiso»); con otro usuario de LM escribir `/marketing` → «No tienes acceso a Marketing».

**Pruebas de ataque en navegador pendientes de la Fase 0 (condición de Henry):** doble clic en guardar (lead, formulario, piloto) → una sola fila ✅; recargar a mitad → sin duplicados y la pantalla vuelve bien ✅; otro usuario de LM escribe la URL de 10 pantallas → 403, ningún dato ✅ (sustituye a «URL de otra marca», que ya no aplica); 19 pantallas a 375 px sin scroll horizontal ✅. No se pudo: doble clic al renderizar video (la base de pruebas no tiene los permisos de video). Capturas: `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase1/`.

### 10. Para avanzar
**Pendientes que quedan anotados (no bloquean):** arreglo de `company_id = 1` fijo en 6 controladores (no afecta con una sola empresa); `PublishCampaignView` lista vacía porque nada marca campañas multivariante como `completed` (ya existía, se ve en Fase 2); las APIs de alta de marcas siguen existiendo ocultas (solo Henry/Irving por URL).

**Con el SÍ de Henry:** commit de la Fase 1 en su rama. Integrar a main de dev, publicar versión y aplicar en prod lo decide Irving.
**Ojo para producción (V1.87 + Fase 1 juntas):** la migración de la Fase 0 pone como admin de Marketing a ~12 personas por rol; con la Fase 1 esas personas siguen entrando **hasta que Irving corra** `php artisan marketing:equipo-fijar <login de Henry> <login de Irving>` (primero sin `--aplicar` para ver la lista, luego con `--aplicar`), con bitácora previa. Hacerlo justo después de aplicar la versión. En dev lo corre esta sesión al integrar.

## 2026-11-13 12:50 — Fase 1 · restricción automática del equipo (condición de Henry) · Resultado: APROBADA por el supervisor

- **Qué:** al aplicar la versión, la migración `2026_11_13_100200_restringe_equipo_marketing_a_lista_env` deja como admin
  de Marketing SOLO a los usuarios de la lista `MARKETING_EQUIPO_LOGINS` del `.env` (no está en el repo) y desactiva a
  todos los demás (no borra). Lista vacía o ausente → nadie entra. Además, mientras la restricción no se haya aplicado
  (marca `equipo_fijado_at` en `marketing_settings`), nadie entra: cubre el instante entre la migración de la Fase 0
  (que asigna por rol) y esta. No depende de ningún comando posterior.
- **Línea a agregar en el `.env` de producción ANTES de aplicar la versión** (logins = campo `login_user`, separados por coma):
  `MARKETING_EQUIPO_LOGINS=<login_user de Henry>,<login_user de Irving>`
- **Pruebas:** suite completa 2,744 · 139 fallan (todas de la línea base) · **0 nuevas**. Nuevas: `EquipoRestringidoBackendTest`
  11/11, `RestriccionEquipoEnvTest` 20/20 (incluye el orden real del deploy: Fase 0 y luego esta, nadie entra entre las dos).
- **Migraciones:** dry-run con datos reales de dev (sin lista: 13 filas desactivadas, 0 activas), rollback (quita la marca:
  nadie entra) y re-migrate, exit 0. `config:auditar-env` sigue en 0 (la lista se lee solo en la migración, con respaldo
  directo del archivo `.env` si la config estuviera cacheada).
- **Observaciones no bloqueantes:** la migración imprime en el log del deploy los logins de la lista que no existan;
  una cuenta inactiva en la lista recibe fila pero no entra (el middleware la frena).
- En dev, al integrar: agregar la línea al `.env` de dev con los usuarios de dev, correr la migración y verificar (`chgrp www-data .env` tras editar).
