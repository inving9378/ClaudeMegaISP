## 2026-11-12 18:30 — Marketing IA 360 · Fase 0 (multiempresa, roles y guía visual) · Resultado: APROBADA (pendiente del SÍ de Henry)

Rama: `feat/marketing-360-equipo` · Worktree: `/var/www/megaisp-henry-marketing` · Base: `cc002c0f6`.
Equipo: supervisor (plan en `docs/marketing/fase-0-paquetes.md`), backend, frontend y QA en paralelo.
QA se quedó atorado a las 16:37: esperaba un trabajo en segundo plano que ya no existía, y no tenía ningún proceso corriendo. Se detuvo a las 17:35 por instrucción de Henry. La sesión principal corrió la skill `pruebas-antes-de-avanzar` completa. Las pruebas que QA ya había escrito (11 archivos) sí se corrieron.
**Sin commit.** Ninguna migración tocó la base `megaisp` (dev compartida).

### 1. Comandos que se corrieron (en orden, con su código de salida)
1. Base: `flock /tmp/megaisp-suite.lock php vendor/bin/phpunit --log-junit …/base.xml` en `/var/www/megaisp-base-marketing`, de 17:36 a 17:44. Salida 2, la normal de una suite con fallas que ya existían.
2. Fase: lo mismo en el worktree, de 17:44 a 18:02. Salida 2.
   - Salió 1 falla nueva: `AislamientoEndpointsExistentesTest::test_filtros_raros_de_leads_no_truenan`.
   - Se corrigió (ver §4) y se volvió a correr ese archivo: `phpunit tests/Feature/Marketing/Fase0/Qa/AislamientoEndpointsExistentesTest.php` dio OK, 129 pruebas.
3. `flock … php artisan deploy:dry-run-migrations --keep`. Salida 0: las 4 migraciones corrieron en limpio.
4. Rollback y re-migración sobre `megaisp_dryrun`, los dos con salida 0. Después del rollback las 2 tablas ya no existían.
   - `flock … env APP_ENV=testing DB_DATABASE=megaisp_dryrun php artisan migrate:rollback --step=4 --force`
   - `… migrate --force`
5. `flock … php artisan deploy:dry-run-migrations --keep --with-data=users,roles,model_has_roles,permissions,role_has_permissions`. Salida 0. Con los datos de dev, que son copia de prod del 10-nov, asignaría **12 admin + 1 marketing**. El permiso nuevo queda solo en `super-administrator` y `DESARROLLADOR`.
6. Suite completa de la fase con el arreglo: `flock … php vendor/bin/phpunit --testdox-text …`, de 18:07 a unos 18:25. Salida 2. **0 fallas nuevas.**
7. Limpieza de §2.10.5 en `megaisp_test`: se borraron los usuarios `mkt_prev_*` (4), sus asignaciones y roles, y la empresa 2.
8. `deploy:dry-run-migrations` sin `--keep`, para borrar la base desechable. Salida 0.
9. Greps de seguridad y de reglas (§5 y §7), `git diff --stat` y `git diff --name-only` contra la base.
10. Antes, durante la construcción:
    - backend corrió `migrate --path` en `megaisp_test` y sus 7 archivos de prueba;
    - frontend corrió `npm run dev` en el worktree tres veces (salida 0, sin advertencias nuevas), su prueba `PantallaEmpresasTest` y Playwright con `artisan serve --no-reload` contra `megaisp_test`.

### 2. Resultado de las pruebas
- **Suite completa:** 2,564 pruebas; la base tenía 2,038.
  - Errores 72 y fallas 67. La base tenía 72 y 70, y todas ya existían.
  - **Fallas nuevas: 0.**
  - 3 pruebas que fallaban en la base ya no fallan: TicketCaja, AparienciaPortal y ListaVendedoresEstado. Son inestables y no tienen relación con esta fase.
- **Migraciones:** en limpio pasa · rollback (4) pasa · re-migración pasa · con datos reales pasa.
- **Pruebas nuevas de la Fase 0: 526, todas pasan.**

| Archivo | Tipo | Pasa / falla |
|---|---|---|
| Backend/EmpresaActivaYScopeTest | normal / borde / aislamiento | 14 / 0 |
| Backend/CapaEmpresaMiddlewareTest | rol / error / vacío | 15 / 0 |
| Backend/ContextoEmpresaBackendTest | normal / error | 7 / 0 |
| Backend/EmpresasApiBackendTest | normal / error / seguridad | 14 / 0 |
| Backend/EquipoApiBackendTest | normal / error / borde | 15 / 0 |
| Backend/MigracionAsignacionBackendTest | normal / borde | 4 / 0 |
| Backend/ExistsAcotadoBackendTest | aislamiento | 4 / 0 |
| Frontend/PantallaEmpresasTest | rol | 7 / 0 |
| Qa/AislamientoEndpointsExistentesTest | aislamiento / ataque | 129 / 0 |
| Qa/AlcanceModelosTest | aislamiento | 103 / 0 |
| Qa/CaminosSinSesionTest | regresión (bot, webhooks, formulario público) | 17 / 0 |
| Qa/ContextoEmpresaApiTest | normal / error | 25 / 0 |
| Qa/EmpresasApiTest | normal / error / ataque | 51 / 0 |
| Qa/EquipoApiTest | normal / error / borde | 27 / 0 |
| Qa/EsquemaEmpresasTest | esquema | 12 / 0 |
| Qa/MatrizRolesTest | rol | 67 / 0 |
| Qa/MigracionAsignacionTest | normal / borde | 5 / 0 |
| Qa/RutasMarketingProtegidasTest | seguridad (131 rutas) | 10 / 0 |

- **Pruebas viejas de Marketing que fallan:** todas fallaban igual en la base.
  - Fallan: ConversationController 5, MultivariantCampaignController 5, AiAgentService 6, EvolutionWebhook 1, Broll 1 y CreativeDirector 1.
  - Causa: la base de pruebas no tiene el usuario 3986 ni el dump `sql_test_unit.sql`.

### 3. Cobertura (funcional; sin pcov, por decisión de Henry)
| Pieza nueva | Prueba que la cubre |
|---|---|
| Tablas `marketing_empresas` / `marketing_empresa_usuario` | EsquemaEmpresasTest |
| Migración de asignación inicial (D1): selector, idempotencia, `down()` | MigracionAsignacion(Backend)Test + ensayo con datos |
| Permiso `marketing.empresas.gestionar` | EmpresasApi(Backend)Test, PantallaEmpresasTest |
| `EmpresaActiva`, `EmpresaScope`, trait `PerteneceAEmpresa` (13 modelos) | EmpresaActivaYScopeTest, AlcanceModelosTest |
| Middleware `marketing.empresa` / `marketing.rol` / `marketing.principal` | CapaEmpresaMiddlewareTest, MatrizRolesTest, RutasMarketingProtegidasTest |
| Rutas `/api/marketing/contexto*` | ContextoEmpresa(Api/Backend)Test |
| Rutas `/marketing/empresas` y `/api/marketing/empresas*` | EmpresasApi(Backend)Test, EquipoApi(Backend)Test, PantallaEmpresasTest |
| Reglas `exists:` acotadas a la empresa | ExistsAcotadoBackendTest, AislamientoEndpointsExistentesTest |
| Rutas viejas sin sesión (bot, webhooks, formulario) | CaminosSinSesionTest |
| Kit visual `mkt-*`, pantalla «Empresas y equipo», selector, botón en el Dashboard | Navegador (Playwright, 1440 y 375 px, claro y oscuro), 33 capturas en el scratchpad de la sesión |
| `per_page` acotado en Leads | AislamientoEndpointsExistentesTest::test_filtros_raros_de_leads_no_truenan |

Huecos: ninguno. Las piezas visuales se probaron en el navegador, no con PHPUnit.

### 4. Fallas encontradas y cómo se corrigieron
1. **Lista de Leads con `per_page=-1` → 500 (SQL con LIMIT negativo).** El defecto ya existía y la prueba nueva de QA lo destapó.
   - Reproducir: `GET /api/marketing/leads?per_page=-1`.
   - Arreglo: `MarketingLeadController.php:73`, que ahora acota entre 1 y 100.
   - Prueba que lo cubre: `test_filtros_raros_de_leads_no_truenan`.
2. **Instrucción del servidor de vista previa.** `php artisan serve` sin `--no-reload` lee el `.env` y se conecta a la base `megaisp` (dev), no a la de pruebas.
   - Frontend lo detectó antes de que el servidor recibiera ninguna petición.
   - Se corrigió el contrato §2.10.3 y los archivos de rol de frontend y QA.
3. **QA atorado** esperando un trabajo en segundo plano que ya no existía. Se detuvo y la sesión principal corrió la skill.
4. **Limitación conocida, no corregida en esta fase:** las pantallas viejas fijan `company_id = 1` a mano en 6 controladores y 1 servicio (Leads, Formularios, Multivariante, Contenido, Publicación, Video).
   - No hay fuga: lo que crea una marca nueva queda en esa marca.
   - Pero esa marca no ve sus propios registros en esas pantallas.
   - Se corrige en la Fase 1, que reescribe esas pantallas.
   - **Hasta entonces no conviene dar de alta una segunda marca en producción.**

### 5. Revisión de seguridad
- **Validación:** 3 FormRequests nuevos con tipos, largos y listas cerradas; slug con expresión regular; colores hex; rol con lista cerrada.
- **Consultas:** las nuevas solo usan bindings, como `whereRaw('LOWER(nombre) = ?', [...])`. La búsqueda de personas escapa los comodines y devuelve máximo 20 resultados.
- **Asignación masiva:** `$fillable` explícito en Empresa y EmpresaUsuario. `company_id` nunca sale del request: el trait lo pisa al crear y lo bloquea al editar.
- **Llaves:** el grep de llaves sobre el diff salió vacío. No se devuelven secretos al frontend.
- **XSS:** sin `v-html` en código nuevo. Con `<script>` en el nombre de la empresa se guarda como texto (EmpresasApiTest).
- **Archivos subidos:** no aplica, esta fase no sube archivos.
- **Ataques:** el WAF global responde 403 a cuerpos con inyección y las pruebas aceptan 403 o 422.
- **Rutas:** las 131 rutas de Marketing llevan `auth` + `check_route_permission` + `marketing.empresa`; las 7 públicas siguen sin sesión.
- **Aviso:** durante la sesión aparecieron en pantalla dos tokens. Ninguno se copió a archivos ni al repo; si se quiere, se pueden rotar.
  - El `evolution_webhook_token`, al leer la configuración de Marketing.
  - El `verify_token` de la integración «WhatsApp oficial (Meta)», al revisar el Hub.

### 6. Aislamiento por empresa y por rol
- **Empresa A contra B:**
  - listar, ver, editar y borrar por id directo dan **404**;
  - conteos y búsquedas no mezclan;
  - `company_id` mandado en el request se ignora.
  - Lo cubren AislamientoEndpointsExistentesTest (129) y AlcanceModelosTest (103).
- **Roles:** lo cubren MatrizRolesTest (67) y RutasMarketingProtegidasTest, con la respuesta 403 y su código de error.
  - admin: todo + «Empresas y equipo»;
  - marketing: todo menos «Empresas y equipo»;
  - ventas: solo Leads (ver, crear, editar, historial) y Conversaciones;
  - sin empresa: 403 `sin_empresa` con la página «Aún no tienes empresa».
- **Configuración compartida** (Brand Kit, nichos, plantillas, medios, canales, bot): solo se edita con Meganet activa; con otra marca da 403 `config_compartida`.
- **Último admin:** quitarse o desactivar al último administrador da 422 `ultimo_admin`.

### 7. Reglas del sistema
Los greps del paso 6 de la skill no tuvieron ninguna coincidencia:
- IA: cero llamadas a la IA en esta fase.
- WhatsApp: no envía nada, no usa líneas y no toca Sofía.
- Mapas: no se tocan.
- Meta: cero llamadas.
- Dinero: no aplica en esta fase; sin `float` ni `USD`.

### 8. Archivos modificados
78 archivos (+9,245 / −145). **Todos dentro de lo autorizado:**
- `app/Modules/Addons/Marketing/**`
- `app/Models/Marketing/**` (11 modelos, solo el trait)
- `resources/js/components/module/marketing/**`
- 1 línea en `resources/js/app.js`
- `tests/Feature/Marketing/**`
- `docs/marketing/**` y esta bitácora

Fuera de lo autorizado: ninguno. `public/js/` (lo que genera la compilación) **no entra** al commit.

### 9. Cómo probarlo en pantalla
- Antes del commit no está en dev puerto 80: no se mezcla sin el SÍ. Las capturas de la vista previa están en el scratchpad de la sesión: `…/scratchpad/shots/` (01 a 12).
- En producción, después del APROBADO, se prueba según §11.

### 10. Para avanzar
¿SÍ (commit de la Fase 0 y continuar a la Fase 1) o NO (qué corregir)?

### 11. Pasos de producción para Henry (después del «APROBADO por Claude de parte de Henry»)
1. **Antes:** publico la versión, solo con lo de Marketing, y te digo su número.
2. **Dónde está el botón:**
   1. Entra a `https://v1megaisp.meganett.com.mx` con tu usuario, en la página de Inicio.
   2. Arriba aparece la franja de actualizaciones con el botón **«Buscar actualizaciones»**. Si no lo ves, tu usuario no tiene `updates.apply`: lo aprieta Irving.
   3. Al apretarlo sale «Actualización disponible — V…» y luego **«Actualizar ahora»**.
3. **Si sale bien:** ves «Aplicando actualización — V…» con su porcentaje. Al final aparece **«Actualización aplicada — V…»** en verde y el botón **«Recargar»**. Aprieta Recargar.
4. **Prueba rápida, unos 3 minutos:**
   1. Entra a Marketing (`/marketing`). Arriba debe estar el encabezado nuevo con el selector de empresa «Meganet» y el botón **«Empresas y equipo»**.
   2. En «Empresas y equipo» debe aparecer Meganet como Principal y su equipo (unas 12 personas admin y 1 marketing).
   3. Abre Leads y Conversaciones: deben abrir igual que antes.
   4. Con el usuario de alguien que no esté en el equipo, Marketing debe decir «Aún no tienes empresa».
5. **Si falla:** aparece «La actualización falló» en rojo. No vuelvas a apretar el botón.
   - Si falló **antes de las migraciones**, LM regresa solo a la versión anterior. Mándame una captura del aviso.
   - Si falló **en las migraciones** (el aviso dice el paso «migrate»): no toques nada más, manda captura y avisa a Irving. La base tiene el respaldo del inicio de la actualización, y revertir requiere la consola del servidor, que solo tiene Irving.

### 12. Meta (pregunta de Henry)
Detalle en `docs/marketing/notas-meta-ads-read.md`.
- **App de Meta:** en dev no hay ninguna app registrada para Marketing.
  - La captura de leads lee `marketing_settings` (`meta_app_secret`, `meta_verify_token`, `meta_page_access_token`), que en dev están vacíos.
  - La tarjeta del Hub «Meta Business» está inactiva y sin App ID.
  - El nombre y el ID hay que verlos en producción, en Hub → «Meta Business» → App ID, y compararlos con la app de Sofía.
- **Dónde se captura:**
  - el token va en `/integraciones` → pestaña Integraciones → tarjeta «Meta Business (Facebook + Instagram)» → Editar (campos: token, App ID, App Secret);
  - el ID `act_…` **no tiene campo hoy**; en la Fase 5 se agrega por marca en Marketing → «Empresas y equipo» → editar marca.
