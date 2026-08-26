# Item roadmap #75 — google_maps_flutter omitido en MegaFamilia (RESUELTO — premisa no aplica)

**Título del item:** "MegaFamilia: google_maps_flutter también omitido del Flutter — afecta vista
de mapas en APK". Hallazgo colateral del detective FCM 2026-06-02, urgencia BAJA (0 clientes
activos), a ejecutar "cuando se haga greenfield Firebase (item #72)... misma sesión dedicada que #72".

## 1. El item depende explícitamente de #72, que sigue sin ejecutarse

El propio texto del item dice que los 3 pasos (agregar `google_maps_flutter` al `pubspec.yaml`,
configurar Maps API key para Android, construir vistas) se hacen **en la misma sesión** que el
item #72 (greenfield Firebase). Verificado: `RoadmapItem::find(72)->estado_aprobacion` sigue
`requiere_irving` — #72 no se ha ejecutado. Ejecutar #75 aislado contradice el propio plan del
item.

## 2. La premisa "afecta vista de mapas en APK" no es cierta hoy

Grep de `flutter_map`/`FlutterMap`/`LatLng` en `/var/www/megafamilia/lib` (repo Flutter real,
independiente de `megaisp`, ver `docs/megafamilia-apk-auditoria-2026-07-14.md` §0) da **un solo
archivo**: `screens/conductor/tabs/mapa_tab.dart` — el mapa del **conductor de Flotas**, no
existe ninguna pantalla de mapa para geolocalización/geocercas/recorridos del **menor**
(MegaFamilia) en el Flutter actual. La auditoría de pantallas (`megafamilia-apk-auditoria-2026-07-14.md`
§5, re-verificada vigente el mismo día que este item, item #23) no lista ninguna pantalla de mapa
bajo Cliente→Parental ni bajo Hijo. No hay nada "afectado" — es una funcionalidad que nunca se
construyó, no una que se rompió por la omisión del paquete.

El propio `pubspec.yaml` (líneas 51-57) muestra por qué: `flutter_map` (OpenStreetMap, **sin
API key ni costo**) se agregó después para el mapa de Flotas Fase 5. El comentario sobre omitir
`google_maps_flutter`/Firebase es de v0.1 y nunca se revisó porque OSM ya cubrió la necesidad
real de mapas de la app.

## 3. El Flutter que pide el item está siendo reemplazado

Confirmado el mismo día (2026-08-26) en el item #23 (mismo worker, `docs/megafamilia-apk-auditoria-2026-07-14.md`
§7): existe una migración Flutter→React Native (`/var/www/megafamilia-rn`) ~95% completa en
pantallas, y es el target recomendado para cualquier inversión nueva de UI — no el Flutter aquí
auditado. Verificado en esta sesión: `megafamilia-rn/package.json` ya trae `react-native-maps
^1.27.2`, y `MIGRATION_RN.md` documenta el mapeo `flutter_map (OSM) → react-native-maps (OSM tile
override)` — es decir, la app sucesora **ya hereda el mismo patrón sin API key de Google** que
evita justo el problema que este item quiere resolver.

## 4. "Configurar Google Maps API key para Android" sí es una frontera dura

El revisor escaló el item citando credenciales/seguridad — correcto: agregar `google_maps_flutter`
implicaría una key de **Maps SDK for Android** (restringida por package name + SHA-1, distinta de
la key de **Maps JavaScript API** que ya usa el panel admin vía el módulo compartido `Mapas`/
`/configuracion/credenciales-google-maps` para las vistas de Ubicaciones/Geocercas de MegaFamilia
en el navegador). Generar/gestionar esa credencial nueva, en un codebase sin ninguna pantalla que
la necesite y que además se está reemplazando, no se justifica.

## Conclusión

No hay cambio de código que aplique hoy: el gap real (mapa de ubicación del menor) no existe
todavía en ninguna app móvil, el paquete/patrón correcto cuando se construya es el que ya usa
Flotas y el que ya trae `megafamilia-rn` (mapa OSM, sin API key), y el propio item exige
ejecutarse junto con #72 (sin ejecutar). Se cierra documentando la ruta correcta para cuando se
retome: construir la pantalla en `megafamilia-rn` con `react-native-maps` + tiles OSM (no
`google_maps_flutter`, no key de Android nueva); si alguna vez se requiere Google Maps real,
pasar por el módulo compartido `Mapas` (convención "servicios compartidos únicos" de
`CLAUDE.md`), no una key aparte para MegaFamilia.

---
*Generado por el Circuito CC (worker wt-6) el 2026-08-26, item roadmap #75. Sin cambios de
código; solo lectura sobre `/var/www/megafamilia`, `/var/www/megafamilia-rn` (repos
independientes) y el propio repo `megaisp`.*
