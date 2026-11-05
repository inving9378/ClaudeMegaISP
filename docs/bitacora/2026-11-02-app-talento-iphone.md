## 2026-11-02 — App Talento: versión de iPhone en una rama

Pedido de David: «arma la versión de iPhone en una rama y ponla para que sea compatible con varias
versiones del SO».

**Dónde.** `/home/meganet/TalentoEquipo`, rama `build/iphone` (worktree
`/home/meganet/TalentoEquipo-ios`), que sale de `build/play-store` (React Native 0.81). No mezclada a
`master`. Commits: `a346c7e` carpeta `ios/`, `8d1823e` ajustes de código para iPhone, `7cc5472` bundle
de Android al día.

**Versiones de iOS.** Mínimo **iOS 15.1** (el de React Native 0.81; ninguna librería de la app pide
más), hasta iOS 26: del iPhone 6s en adelante, incluidos los que se quedaron en iOS 15 (6s, SE 1.ª
gen., 7) y en iOS 16 (8, X). Solo iPhone. `UIDesignRequiresCompatibility` mantiene en iOS 26 el
aspecto de iOS 18 para que se vea igual en todas las versiones.

**Qué se armó.**
- Carpeta `ios/` desde la plantilla de React Native 0.81: `com.meganet.talento`, «Meganet», versión
  1.23 (123), misma arquitectura que Android (`RCT_NEW_ARCH_ENABLED=0`), textos de permisos (cámara,
  ubicación, red local), sin cifrado propio declarado, fuente de íconos, ícono generado del mismo
  diseño que Android (`assets/branding/gen-icons-ios.js`, sin transparencia) y arranque teal.
- Firebase (avisos push) y el escáner de serial no se enlazan en iPhone: no existe la configuración
  de Firebase para iPhone ni la cuenta de Apple, y el escáner tampoco compila con la cámara 4. La app
  ya se apagaba sola sin ellos.
- Código: asistencia e inspección pedían el permiso de ubicación solo con el nombre de Android (en
  iPhone no se habría podido checar); el teclado en iPhone (la pantalla no se encoge sola); el aviso
  «Actualizar» (abre un APK) no sale en iPhone; el token de avisos se registra con la plataforma real.
  En Android no cambia nada.
- `ios/LEEME.md`: cómo compilarla en una Mac, qué probar y los pasos para activar los avisos.

**Verificado aquí:** el JavaScript se arma para iPhone (`react-native bundle --platform ios`) y para
Android; `Info.plist` y `PrivacyInfo.xcprivacy` son válidos; los íconos son PNG sin transparencia; la
configuración de enlaces de React Native lista para iPhone las 10 librerías nativas y deja fuera
Firebase y el escáner.

**No verificado (no se puede en Linux):** `pod install` y la compilación en Xcode, ni la app en un
iPhone o simulador. Para eso hace falta una Mac con Xcode 26 (Apple lo exige para subir desde el
28-abr-2026) y la cuenta de Apple Developer.
