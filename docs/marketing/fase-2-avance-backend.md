12:52 — arranco: leí rol y contrato; inventario del módulo · sigue: migraciones
12:56 — 4 migraciones creadas y aplicadas en megaisp_test (rollback --step=4 y re-migrate OK); frontend ya puede sembrar · sigue: catálogos, validador, reparto, presenter
13:01 — catálogos, ValidadorPiezas, RepartoPresupuesto (bcmath), Presenter, modelos y 7 FormRequests listos · sigue: controladores CRUD/estado, rutas, Blade
13:06 — controladores (CRUD/estado, generación, piezas, videos), jobs IA, generadores, GuionAMvm y ProductorVideoCampana escritos · sigue: rutas, Blade, module.json, claves IA, cambios al motor
13:08 — rutas (152), Blade, module.json (api_endpoints; menú NO, espera §2.11), 2 claves IA, select de generated-content, motor: renderer con bandera + RenderVideoJob idempotente/WithoutOverlapping · sigue: pruebas propias
13:12 — pruebas: Campana360CrudBackendTest 37 OK, RepartoPresupuesto 8 OK, GuionAMvm 6 OK, ValidadorPiezas 37 OK · sigue: GeneracionBackendTest, ProducirBackendTest, RendererEscenasBackendTest, MigracionesFase2BackendTest
13:15 — GeneracionBackendTest 15 OK (arreglado: nombre de la función IA con clave con punto; contenido vacío en PUT pieza) · sigue: ProducirBackendTest, RendererEscenasBackendTest, MigracionesFase2BackendTest
13:16 — NO toqué RutasMarketingProtegidasTest ni pruebas de Fase 0/1 (regla de Henry). Lista final de rutas nuevas abajo para la sesión principal. Alias de middleware en routes.php: $M = marketing.empresa + marketing.rol:admin,marketing; generar/regenerar/producir + throttle:6,1 · sigue: resultado ProducirBackendTest, Renderer, Migraciones

### Rutas nuevas de Fase 2 (18) — para RutasMarketingProtegidasTest (lo cambia la sesión principal, no backend)

| Método | URI | Nombre | Middleware |
|---|---|---|---|
| GET | /api/marketing/campanas-360 | api.marketing.campanas-360.index | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| POST | /api/marketing/campanas-360 | api.marketing.campanas-360.store | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /api/marketing/campanas-360/{id} | api.marketing.campanas-360.show | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| PUT | /api/marketing/campanas-360/{id} | api.marketing.campanas-360.update | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| DELETE | /api/marketing/campanas-360/{id} | api.marketing.campanas-360.destroy | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| POST | /api/marketing/campanas-360/{id}/estado | api.marketing.campanas-360.estado.cambiar | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| PUT | /api/marketing/campanas-360/{id}/estrategia | api.marketing.campanas-360.estrategia.update | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| POST | /api/marketing/campanas-360/{id}/generar | api.marketing.campanas-360.generar | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |
| PUT | /api/marketing/campanas-360/{id}/piezas/{piezaId} | api.marketing.campanas-360.piezas.update | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| POST | /api/marketing/campanas-360/{id}/piezas/{piezaId}/producir | api.marketing.campanas-360.piezas.producir | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |
| POST | /api/marketing/campanas-360/{id}/piezas/{piezaId}/regenerar | api.marketing.campanas-360.piezas.regenerar | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |
| GET | /api/marketing/campanas-360/{id}/progreso | api.marketing.campanas-360.progreso | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /api/marketing/campanas-360/{id}/videos/{videoId}/miniatura | api.marketing.campanas-360.videos.miniatura | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /api/marketing/campanas-360/{id}/videos/{videoId}/ver | api.marketing.campanas-360.videos.ver | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /marketing/campanas-360 | marketing.campanas-360.lista | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /marketing/campanas-360/nueva | marketing.campanas-360.nueva | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /marketing/campanas-360/{id} | marketing.campanas-360.ficha | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |
| GET | /marketing/campanas-360/{id}/editar | marketing.campanas-360.editar | web, App\Modules\Core\Auth\Middleware\Authenticate, App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva, App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa:admin,marketing |

13:19 — ProducirBackendTest 8 OK, RendererEscenasBackendTest 13 OK · sigue: MigracionesFase2BackendTest, pruebas viejas del motor, greps
13:23 — MigracionesFase2BackendTest 6 OK; grep de prohibidos vacío; rutas Marketing = 152; corriendo pruebas viejas del motor por nombre (RenderVideoJob/VideoTemplateRenderer OK como base) · sigue: Multivariant/Kinetic/FFmpeg/Tts y cierre

### Notas para frontend y QA (backend, 13-nov)
- Formas JSON = §2.4. Extras aditivos: PUT pieza devuelve además `avisos` (p. ej. voz en off larga: aviso, no error); 422 `guion_invalido` trae `errores` {ruta: mensaje}; 422 de PUT estrategia / PUT pieza usan formato Laravel (`message`, `errors.<ruta>`), con rutas tipo `escenas.0.texto_pantalla`.
- `throttle:6,1` es por USUARIO y compartido entre generar, regenerar y producir (Laravel firma por usuario): la 7.ª de cualquiera de las tres en el mismo minuto → 429.
- Índice y ficha solo ven campañas con `objetivo` (las creadas por el asistente); las campañas viejas no aparecen ni se abren aquí (404).
- MySQL JSON reordena claves dentro de `estrategia`/`contenido`: comparar por clave, no por orden.
- `presupuesto_mxn` / `precio_mxn`: cadena o entero; un número con decimales en JSON (float) → 422 (mandar cadena "599.50").
- La cola de pruebas es `sync`: generar/regenerar corren dentro de la petición (el 202 ya trae el estado final). Para producir usar `Queue::fake` o el render real corre en la petición.
- Doble de IA para pruebas: `ClienteIACampana` (puerta única hacia `IA::configurada`/`IA::enviar`) se puede sustituir en el contenedor; ojo: el controlador queda en caché en la ruta dentro de una misma prueba.
- Video `sin_voz` = `generation_metadata.voz_generada === false` (guion sin voz en off o TTS falló). `duracion_s` solo cuando está listo.
- Menú (`module.json menu[]`, «Campañas 360» y renombre «Video por nicho»): NO programado, espera la aprobación de §2.11.
13:32 — pruebas viejas del motor por nombre: RenderVideoJob OK (3, 2 skip), VideoTemplateRenderer OK (7, 3 skip), Kinetic/FFmpeg/Tts OK, Multivariant 5 errores = los mismos 5 de la línea base (sin usuario en megaisp_fresh_test). CIERRE del paquete backend.
14:12 — retomo correcciones de QA F-1, H-1, H-3, H-5 · sigue: leer reporte QA
14:17 — F-1/H-1/H-3/H-5 + tamaño/resolución reales aplicados; Crud 42 OK, Generacion 17 OK, Producir 9 OK (incluye render real 1080x1920) · sigue: resto de mis pruebas + 4 de QA

### Correcciones de QA (F-1, H-1, H-3, H-5 + tamaño/resolución) — 14:20
- **F-1 borrador parcial:** el POST pide solo `nombre` y `objetivo`; todo lo demás es opcional (`null`, `[]` o ausente), pero lo que venga se valida igual de estricto (tipos, MXN como cadena o entero, límites, `{{ }}`).
  - La forma Campana360 gana `faltantes` (lista de campos). `presupuesto_mxn` es `null` mientras no se captura; `"0.00"` sigue siendo «orgánica». La marca va en `config.campanas360_presupuesto_definido`.
  - Generar con la campaña incompleta → 422 `campana_incompleta` con `faltantes` y el mensaje «Completa la campaña antes de seguir. Falta: …». Activar incompleta → el mismo 422. Cancelar un borrador incompleto sí se puede.
  - Orden en generar: 404 → 409 `campana_cerrada` → 422 `campana_incompleta` → 422 `ia_no_configurada` → resto.
- **H-1:** `ClienteIACampana` pasa `timeout` 75 s y `reintentos` 0 por llamada (2 × 75 = 150 < 170 del trabajo).
- **H-3:** en pantallas, `sin_acceso` (fila marketing/ventas) muestra la vista «No tienes acceso a Marketing». La API sigue igual. Afecta a todo `/marketing/**`, no solo a Campañas 360.
- **H-5:** `alcance` es obligatorio en POST /generar; sin cuerpo → 422 `errors.alcance`.
- **Tamaño y resolución:** el renderer devuelve `file_size` y `resolution` reales (ffprobe del MP4 final) y el job los guarda.
  - Probado con un render real en `ProducirBackendTest`: 1080x1920 y el tamaño igual al del archivo.
- **Pruebas de QA que cambian de expectativa por F-1 (no las edité):** `AsistenteCampanaTest::test_alta_invalida_da_422_con_el_campo`, 4 casos que ahora dan 201:
  - «canales vacío»
  - «público sin descripción» (`''`)
  - «oferta sin descripción» (`''`)
  - «presupuesto falta» (`null`)
  - Lo que sigue dando 422: nombre u objetivo faltantes, `publico`/`oferta` con texto de menos de 3 caracteres, y los tipos raros.
- **Por H-5:** ninguna prueba de QA de las corridas cambia (todas mandan `alcance`). Una prueba que haga POST /generar sin cuerpo esperando 202 ahora recibe 422.
- **Resultado:**
  - Mis 8 archivos: OK.
  - `GeneracionEstrategiaTest`: 19 OK.
  - `AccesoCampanas360Test`: 11 OK.
  - `ProduccionPuntaAPuntaTest`: 1 OK (61 aserciones).
  - `AsistenteCampanaTest`: 55 OK y 4 fallas, justo los 4 casos de F-1 de arriba.
18:24 — ajuste de Henry (Público solo tipo; prompts sin campos vacíos; fecha fin; diagnóstico Nextlalpan) · sigue: diagnóstico
18:27 — nuevo encargo A (nichos fuera del público en prompts) y B (registrar errores de validación, respuesta cortada, guiones una variante por trabajo) · en curso
18:29 — A (nichos fuera del público; categorías de clips de fondo solo en guiones), B (reel/guion una variante por trabajo; motivo humano en failure_reason; errores_validacion por intento + log; respuesta cortada) aplicados; pruebas nuevas escritas · sigue: correr todo

### Ajuste de Henry 13-nov (Público solo tipo; prompts; fecha fin; Nextlalpan; nichos fuera del público; motivo de rechazo) — 18:45
- **1) Público solo con el tipo:** edad, nicho, zona e interés ya eran opcionales y no están en `faltantes`. Una campaña con solo el tipo de público (`publico.descripcion`) se guarda, genera y activa. La forma del JSON no cambia y lo que llegue se valida igual de estricto.
- **2) y A) Prompts:**
  - El público es solo lo que escribió el equipo; campos vacíos o `null` no se mencionan, en público ni en oferta.
  - Los nichos YA NO se mandan como «NICHOS DEL PÚBLICO» (eran la causa de «home office y gamers»): se quitaron de estrategia y piezas.
  - Solo en guiones va «CATEGORÍAS DE CLIPS DE FONDO DISPONIBLES (no son el público…)», con slug y nombre, sin motivadores, dolores ni vocabulario.
  - Se ajustaron la forma de `categoria_broll` y la regla del guion. GeneradorEstrategia no pedía esos campos (sin cambios).
- **3) Fecha de fin:** ya existía; ahora se revisa aunque haya otros errores en la misma petición. 422 `errors.fecha_fin` = «La fecha de fin debe ser igual o posterior a la de inicio.» en POST y PUT; también si en PUT se mueve el inicio después del fin guardado. Las fechas imposibles (2026-02-31) dan 422.
- **4) Diagnóstico «Nextlalpan»:** NO es de backend. La API guarda `publico.zonas: ["Nextlalpan"]` en POST y PUT (probado), y un texto suelto se rechaza con 422, nunca se descarta en silencio. La causa es de frontend: en `PasoPublico.vue` el campo es un `q-select` con `use-input` + `new-value-mode`, que solo convierte lo escrito en chip con Enter (`@new-value`); si se escribe y se sale del campo sin Enter, el texto se pierde (no hay manejo de blur). Al ocultar el campo deja de importar; si se vuelve a mostrar, agregar el chip al salir del campo.
- **B) Motivo del rechazo:**
  - Reels (y todo guion con 2 o más variantes) van UNA variante por trabajo y por llamada, cada una con su reintento. Video 16:9 (una variante) va directo.
  - Decisión: no subir `MAX_TOKENS`. Dos guiones de hasta 8 escenas no caben en 3500, y más tokens de salida = más tiempo; llamada + reintento deben caber en 170 s.
  - `ClienteIACampana` marca `cortada` cuando el proveedor para por el tope (`fin = max_tokens`), y el reintento pide responder más breve.
  - Cada pieza fallida guarda en `generation_metadata.errores_validacion` los errores de CADA intento (`intento_1`/`intento_2`: campo → mensaje, `respuesta_cortada`; sin el texto de la IA) y deja un warning en el log `marketing`.
  - `failure_reason` = motivo humano del último intento, p. ej. «La IA no entregó un guion válido: el guion debe durar de 10 a 45 segundos (suma 52 s). Regenéralo o edítalo a mano.» o «…: la respuesta llegó cortada (demasiado larga)…».
- **Pruebas de QA que cambian de expectativa por B (no las edité):** `GeneracionPiezasTest`, 13 fallas:
  - `test_piezas_y_variantes_exactas_por_canal_con_una_llamada_por_tipo`: los Reels ahora son 1 trabajo del tipo + 1 por variante; el conteo de jobs y de llamadas sube.
  - `test_guion_invalido_de_la_ia_reintenta_una_vez_y_queda_failed` (todos los casos): un reel por llamada → más llamadas; el motivo nuevo en `failure_reason`.
  - `test_un_tipo_que_falla_no_tumba_a_los_demas`: el texto de `failure_reason` ahora es el motivo humano.
- **Resultados:**
  - Mis 8 archivos: OK (Generacion 21, Crud 44, Validador 37, Reparto 8, GuionAMvm 6, Producir 9, Renderer 13, Migraciones 6).
  - QA: AsistenteCampanaTest 60 OK, GeneracionEstrategiaTest 20 OK, EditarRegenerarPiezaTest 31 OK, ProduccionPuntaAPuntaTest 1 OK.
