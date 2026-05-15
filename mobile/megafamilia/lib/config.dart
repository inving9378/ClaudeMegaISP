/// Configuración estática de la app. Para v0.1, valores hardcoded.
class AppConfig {
  static const String apiBaseUrl = 'http://192.168.105.11/api/megafamilia';
  static const String apkDownloadUrl = 'http://192.168.105.11/apk/megafamilia.apk';
  static const String appName = 'MegaFamilia';
  static const String appVersion = '0.3.0';

  /// Si true, el ApiService inyecta datos mock cuando un endpoint no existe
  /// todavía en el backend. Útil para que las pantallas no se vean vacías
  /// mientras la API se completa.
  static const bool useMockFallback = true;
}
