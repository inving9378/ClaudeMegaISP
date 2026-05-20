import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';

class AppVersionInfo {
  final String version;
  final String apkUrl;
  final String? sha256;
  final int? size;
  final String? releaseNotes;
  final bool mandatory;

  AppVersionInfo({
    required this.version,
    required this.apkUrl,
    this.sha256,
    this.size,
    this.releaseNotes,
    this.mandatory = false,
  });

  factory AppVersionInfo.fromJson(Map<String, dynamic> j) => AppVersionInfo(
        version: (j['version'] ?? '').toString(),
        apkUrl: (j['apk_url'] ?? '').toString(),
        sha256: j['sha256']?.toString(),
        size: j['size'] is int ? j['size'] as int : int.tryParse('${j['size']}'),
        releaseNotes: j['release_notes']?.toString(),
        mandatory: j['mandatory'] == true,
      );
}

/// Compara dos versiones tipo "x.y.z" devolviendo -1/0/1. Tolerante a
/// trailings como "+2" (sufijo de build) o componentes faltantes.
int compareVersions(String a, String b) {
  List<int> parts(String s) =>
      s.split('+').first.split('.').map((p) => int.tryParse(p) ?? 0).toList();
  final pa = parts(a);
  final pb = parts(b);
  final n = pa.length > pb.length ? pa.length : pb.length;
  for (var i = 0; i < n; i++) {
    final ai = i < pa.length ? pa[i] : 0;
    final bi = i < pb.length ? pb[i] : 0;
    if (ai != bi) return ai.compareTo(bi);
  }
  return 0;
}

class UpdateService {
  /// Devuelve la info del servidor solo si su versión es ESTRICTAMENTE mayor
  /// que la instalada. Si no hay update, devuelve null.
  Future<AppVersionInfo?> checkForUpdate() async {
    try {
      final pkg = await PackageInfo.fromPlatform();
      final installed = pkg.version;

      final res = await http
          .get(
            Uri.parse('${AppConfig.apiBaseUrl}/app-version'),
            headers: const {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 8));
      if (res.statusCode != 200) return null;

      final info = AppVersionInfo.fromJson(jsonDecode(res.body) as Map<String, dynamic>);
      if (info.version.isEmpty || info.apkUrl.isEmpty) return null;

      return compareVersions(info.version, installed) > 0 ? info : null;
    } catch (_) {
      // Falla silenciosa — el chequeo de actualización no debe romper la app.
      return null;
    }
  }

  /// Abre la URL del APK en el navegador del sistema. Android descarga el
  /// archivo y al tocarlo lanza el instalador; como mantenemos la misma
  /// firma (debug keystore), aparece como "Actualizar", no "Instalar",
  /// y conserva datos.
  Future<bool> openApkUrl(String url) async {
    final uri = Uri.parse(url);
    if (!await canLaunchUrl(uri)) return false;
    return launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
