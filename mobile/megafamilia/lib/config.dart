/// Configuración estática de la app.
class AppConfig {
  static const String apiBaseUrl = 'http://192.168.105.11/api/megafamilia';
  static const String apkDownloadUrl = 'http://192.168.105.11/downloads/megafamilia-v0_3_2.apk';
  static const String appName = 'MegaFamilia';
  static const String appVersion = '0.3.2';

  /// Si true, el ApiService inyecta datos mock cuando un endpoint falla.
  /// false = modo producción (muestra errores reales en lugar de demos).
  static const bool useMockFallback = false;
}
