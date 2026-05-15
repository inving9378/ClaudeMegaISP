# MegaFamilia — app móvil

App Flutter v0.1 que consume `/api/megafamilia/*` del backend MegaISP.
Un solo APK con 4 rutas dependiendo del rol del usuario al iniciar sesión:

| Rol Spatie en el backend           | Pantalla home                  |
|------------------------------------|--------------------------------|
| `cliente` / `padre` (o sin rol)    | `/cliente` — autoservicio ISP  |
| `tecnico` / `técnico` / `TECNICO`  | `/tecnico` — órdenes de campo  |
| `hijo` / perfil_type del niño      | `/hijo` — tiempo + tareas      |
| `Administrador` / `DESARROLLADOR`  | redirect al panel web          |

## Arquitectura

- **State**: `provider` (5 ChangeNotifiers: Auth, Cliente, ControlParental, Tecnico, Hijo).
- **Routing**: `go_router` con redirect que lee `AuthProvider.state` y dirige
  según el rol persistido en SharedPreferences.
- **HTTP**: `package:http` + token Sanctum en header `Authorization: Bearer …`.
  `ApiService._tryEndpoint(...)` cae a mock data cuando un endpoint no existe
  todavía en el backend — útil mientras se completa la API.
- **Theming**: Material 3 + paleta MegaISP (naranja `#FF6B00` primario).
- **Tema oscuro**: básico, opt-in vía sistema.

## Endpoints reales vs mock

| Endpoint                                 | Real        | Mock fallback                         |
|------------------------------------------|-------------|---------------------------------------|
| `POST /auth/login`                       | ✅          |                                       |
| `GET /account`                           | ✅          |                                       |
| `GET /profiles` / `POST /profiles`       | ✅          |                                       |
| `GET /profiles/{id}/devices`             | ✅          |                                       |
| `GET /devices/{id}/rules`                | ✅          |                                       |
| `POST /tasks/{id}/complete`              | ✅          |                                       |
| `POST /requests`                         | ✅          |                                       |
| `POST /locations`                        | ✅          |                                       |
| `GET /servicio`                          | ❌          | plan/velocidad/consumo                |
| `GET /facturas` / `GET /pagos`           | ❌          | 4 facturas + 3 pagos                  |
| `GET /tickets` / `POST /tickets`         | ❌          | 3 tickets demo                        |
| `GET /tecnico/ordenes` / `PUT .../{id}`  | ❌          | 3 órdenes demo                        |
| `GET /hijo/tareas`                       | ❌          | 4 tareas demo                         |

## Dependencias omitidas en v0.1

- **firebase_core / firebase_messaging** — requieren `google-services.json`
  bajo `android/app/`. Añadir cuando Firebase esté configurado para el
  proyecto, descomentar en `pubspec.yaml` y wire FCM en `main.dart`.
- **google_maps_flutter** — requiere API key en `AndroidManifest.xml`.
  Añadir cuando se cuente con un Maps API key.
- **qr_code_scanner** — sustituido por `mobile_scanner` (paquete sucesor
  mantenido; `qr_code_scanner ^1.0.1` está archivado y rompe con AGP
  moderno por "namespace missing").

## Comandos

```bash
# Setup local (una sola vez)
export PATH="$PATH:/opt/flutter/bin"
export ANDROID_SDK_ROOT=/opt/android-sdk

cd /var/www/megaisp/mobile/megafamilia
flutter pub get

# Compilar APK release
flutter build apk --release --no-shrink
# → build/app/outputs/flutter-apk/app-release.apk

# Deploy (requiere sudo)
sudo cp build/app/outputs/flutter-apk/app-release.apk /var/www/html/apk/megafamilia.apk
sudo chmod 644 /var/www/html/apk/megafamilia.apk
```

## Estructura de carpetas

```
lib/
├── main.dart                # MultiProvider + MaterialApp.router
├── theme.dart               # Paleta MegaISP + ThemeData
├── router.dart              # go_router con redirect por rol
├── config.dart              # base URL + flags
├── models/models.dart       # DTOs (Factura, Ticket, Orden, Tarea, …)
├── services/
│   ├── api_service.dart     # HTTP + mock fallback
│   └── storage_service.dart # SharedPreferences (sesión)
├── providers/               # 5 ChangeNotifier por rol
├── widgets/widgets.dart     # StatusBadge, StatCard, QuickAction, etc.
└── screens/
    ├── auth/                # Splash, Login
    ├── cliente/             # 8 pantallas
    ├── tecnico/             # 5 pantallas
    └── hijo/                # 5 pantallas
```
