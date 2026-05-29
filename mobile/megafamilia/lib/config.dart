/// Configuración estática de la app.
/// La URL base se puede sobreescribir en tiempo de compilación:
///   flutter build apk --dart-define=API_BASE_URL=https://midominio.com/api/megafamilia
class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.105.11/api/megafamilia',
  );
  static const String apkDownloadUrl = String.fromEnvironment(
    'APK_DOWNLOAD_URL',
    defaultValue: 'http://192.168.105.11/downloads/megafamilia-v0_3_2.apk',
  );
  static const String appName = 'MegaFamilia';
  static const String appVersion = '0.3.3';

  /// Si true, el ApiService inyecta datos mock cuando un endpoint falla.
  static const bool useMockFallback = bool.fromEnvironment('MOCK_FALLBACK', defaultValue: true);
}
