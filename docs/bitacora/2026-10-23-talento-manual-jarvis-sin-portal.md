## 2026-10-23 — Manual de usuario y guía de JARVIS de Talento sin el Portal

Pedido: actualizar el manual de usuario y la guía de JARVIS tras retirar el Portal de Colaborador
(ver `2026-10-23-talento-retira-portal.md`).

**Manual Operativo (capítulo Talento, `TalentoUserManualSeeder` + menú de `ManualIndex.vue`)**
- «Qué es Talento» y Custodia ya no hablan del Portal: hablan de la ficha propia.
- Las 6 páginas «Portal de Colaborador — …» se cambian por «Tu ficha de Talento» y «Tu ficha — Mi
  día / Mi dinero / Mis prospectos / Mi equipo». El seeder borra las 6 páginas viejas al correr.
- Custodia ahora la ve cualquier colaborador (antes solo técnicos): ahí está su material.
- Capturas nuevas `ficha-*.png`, tomadas con datos de ejemplo inyectados en el navegador y los nombres
  cambiados por «Ejemplo»; se borran las 6 `portal-*.png` (la de «Mi día» mostraba el nombre y la
  dirección de un cliente real; sigue en el historial de git).
- En dev el capítulo nunca se había cargado (quedó pendiente el 2026-09-22): se corrió el seeder en
  dev (27 páginas, `manual_sections`, solo filas `talento-*`). En producción hay que correrlo igual:
  `php artisan db:seed --class="App\Modules\Addons\Empresa\Seeders\TalentoUserManualSeeder" --force`.

**JARVIS**
- `entrenamiento.json`: `usuario_menu` pasa de `circuito_test_126` (ya no existe; el comando fallaba
  de entrada) a `admin`, y se agrega una nota de Talento (ficha propia, sus pestañas, que el Portal
  ya no existe y que niveles, puestos y roadmap se retiraron).
- `docs/modulos/talento.md`: dos menciones más al Portal corregidas.
- **Falta regenerar** `resources/asistente/guia/modulos/talento.md` (todavía manda a Puestos, Niveles
  y Roadmap): `php artisan asistente:aprender talento --forzar` falla porque en dev no se pueden
  descifrar las llaves de IA guardadas en Integraciones (OpenAI y Anthropic, `DecryptException`): se
  guardaron con otra clave maestra. Hay que volver a capturar la llave en Integraciones y correr el
  comando.
- Nota: el generador lee el manual solo por `module_slug` `talento`/`addon-talento`/`core-talento`,
  así que el capítulo curado `talento-*` no le llega; por eso la nota en `entrenamiento.json`.
