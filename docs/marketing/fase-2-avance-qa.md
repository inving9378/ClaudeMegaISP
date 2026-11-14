# Fase 2 · Avance de QA (agente-qa)

12:52 — arranco: leí rol, contrato y skill; backend/frontend sin avance aún · sigue: fixtures de IA + EscenarioFase2
12:57 — línea base del motor capturada con el código de 08d58e7e3 (fixtures/escenas-base.json: genérica 9:16/16:9 y nicho gamer, escenas+TTS) · sigue: fixtures de IA + EscenarioFase2
12:59 — FIXTURES LISTOS para frontend: tests/Feature/Marketing/Fase2/Qa/fixtures/ia/ (estrategia-valida.json, piezas-<tipo>-validas.json ×6, piezas-invalidas.json) + Concerns/EscenarioFase2.php · sigue: AccesoCampanas360Test y AsistenteCampanaTest
13:02 — AccesoCampanas360Test (barrido de las 18 rutas desde el router) y AsistenteCampanaTest (§2.4.2 normal/borde/ataque) escritos; aún sin correr (backend en catálogos) · sigue: GeneracionEstrategiaTest, GeneracionPiezasTest
13:04 — GeneracionEstrategiaTest, GeneracionPiezasTest, EditarRegenerarPiezaTest escritos (sin correr: backend aún sin controladores) · sigue: ProducirVideoTest, MotorEscenasTest, PuntaAPunta
13:15 — escritos ProducirVideoTest, MotorEscenasTest, ProduccionPuntaAPuntaTest, WhatsAppSoloTextoTest, EstadoCampanaTest, MigracionesFase2Test. NO modifico Fase0/Fase1 (mi alcance es solo Fase2/Qa): quedan para el supervisor los conteos 134→152 en Fase0/Qa/RutasMarketingProtegidasTest.php:484 (+18 en nuevas(), assertCount(13→31) :436), Fase1/Qa/AccesoNegadoPorDefectoTest.php:89 y Fase1/Backend/AccesoSinRutaPermisoBackendTest.php:55 · sigue: correr mis pruebas por nombre contra lo que ya existe
13:16 — confirmado: NO toqué Fase0/Fase1 (git diff vacío ahí); los conteos 134→152 quedan anotados para la sesión principal · sigue: corriendo MigracionesFase2Test y AccesoCampanas360Test
13:21 — MigracionesFase2Test 8 OK, AccesoCampanas360Test 11 OK, AsistenteCampanaTest 59 OK; GeneracionEstrategiaTest 3 fallas en revisión · sigue: resto por nombre
13:26 — GeneracionEstrategiaTest/GeneracionPiezasTest esperando el candado (backend corre MultivariantCampaignControllerTest > 4 min); mientras, revisión de código: candados de producir/generar/regenerar OK, ver/miniatura con realpath OK · sigue: correr el resto
13:30 — GeneracionEstrategiaTest 19 OK (ajuste de prueba: la IA caída se simula con 400; el adaptador central reintenta 5xx con espera, comportamiento existente) · sigue: GeneracionPiezasTest en curso
13:32 — GeneracionPiezasTest 22 OK · sigue: EditarRegenerar, Producir, Estado, WhatsApp (en curso)
13:33 — WhatsAppSoloTextoTest 3 OK; ajustes de prueba (WAF global 403 aceptado, MySQL JSON reordena claves, contexto de regeneración = las OTRAS variantes) · sigue: re-corrida + MotorEscenasTest
13:35 — EditarRegenerar 31 OK, Producir 14 OK, Estado 6 OK; MotorEscenas: 2 ajustes de prueba (enum license, ida y vuelta JSON) · sigue: Motor + PuntaAPunta
13:38 — MotorEscenasTest 13 OK, ProduccionPuntaAPuntaTest OK (Reel 1080×1920 + Video 1920×1080 reales, render 117.7 s los dos) · sigue: limpieza de archivos y navegador
13:41 — limpieza agregada al escenario (temp de CTA y voz que deja el motor); todas mis pruebas en verde · sigue: navegador en 8097
13:52 — navegador 8097: acceso (fila marketing → 403 genérico, igual que /marketing de Fase 1; sin fuga de datos), 999999 → «No encontrado»; asistente en curso
13:57 — asistente: doble clic en Guardar = 1 fila, recargar en /editar no duplica, MXN bien; guardar en el paso 1 NO es posible (422 hasta llenar todo) · sigue: ficha, generar sin IA, producir
14:00 — ficha 1440/375 sin desborde, WhatsApp solo «Copiar» + aviso visible; Generar sin IA → «Falta asignar una IA…» y 0 trabajos · sigue: producir con doble clic + procesador
14:01 — Producir con doble clic: 1 video y 1 trabajo por guion; «Sigue en cola» visible al forzar 11 min; procesador video-render corriendo; dry-run de migraciones (paso 2a) en curso
14:07 — punta a punta en pantalla: Reel (1080×1920, 11.4 s) y Video (1920×1080, 18.1 s) → Listo y se reproducen (sin voz: en megaisp_test no hay IA de voz, la ficha lo avisa); render 81 s + 138 s. Skill 2a dry-run OK (7 pendientes), 2b rollback --step=4 + re-migrate OK · sigue: estados de error en pantalla y limpieza
14:09 — navegador terminado y limpio (QA2NAV, usuarios qa2nav_*, 4 archivos de video, servidor 8097 apagado); corriendo por nombre las pruebas existentes de rutas/menú para anotar lo que afecta la Fase 2 · sigue: reporte
14:11 — CIERRE QA: 11 archivos propios en verde; punta a punta OK (prueba + navegador); skill 2–7 OK; pruebas existentes que caen: AccesoNegadoPorDefectoTest:89 y AccesoSinRutaPermisoBackendTest:55 (134→152), NavegacionTest/NavegacionBackendTest por el menú §2.11. Reporte en docs/marketing/fase-2-qa-reporte.md
14:27 — re-corrida tras F-1/H-1/H-3/H-5: pruebas ajustadas sin aflojar (borrador incompleto no genera ni activa; generar sin alcance 422; Blade fila marketing «No tienes acceso»); 10/11 OK, falta PuntaAPunta
14:30 — CIERRE re-corrida: 11/11 OK (PuntaAPunta 127.8 s); reporte §11 actualizado
18:53 — re-corrida por la decisión de Henry (Reels por variante, motivo humano, nichos fuera del público): GeneracionPiezasTest ajustado sin aflojar + prueba nueva del prompt; 11/11 OK (191 pruebas)
