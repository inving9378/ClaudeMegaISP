import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import 'tour_service.dart';

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
          .timeout(const Duration(seconds: 4));
      if (res.statusCode != 200) return null;

      final info = AppVersionInfo.fromJson(jsonDecode(res.body) as Map<String, dynamic>);
      if (info.version.isEmpty || info.apkUrl.isEmpty) return null;

      return compareVersions(info.version, installed) > 0 ? info : null;
    } catch (_) {
      // Falla silenciosa — el chequeo de actualización no debe romper la app.
      return null;
    }
  }

  /// Comprueba si hay actualización y, si la hay, muestra el diálogo estándar.
  /// Llámalo desde el initState de cualquier dashboard tras autenticarse.
  /// No hace nada si no hay update o si el context ya no está montado.
  Future<void> showUpdateDialogIfNeeded(BuildContext context) async {
    final info = await checkForUpdate();
    if (info == null || !context.mounted) return;

    final accepted = await showDialog<bool>(
      context: context,
      barrierDismissible: !info.mandatory,
      builder: (_) => AlertDialog(
        title: const Text('Actualización disponible'),
        content: Text(
          'Hay una versión nueva (${info.version}) lista para instalar.'
          '${info.releaseNotes != null && info.releaseNotes!.isNotEmpty ? "\n\n${info.releaseNotes}" : ""}'
          '\n\nAl tocar "Descargar", el sistema bajará el APK y te pedirá actualizar la app — no necesitas desinstalar.',
        ),
        actions: [
          if (!info.mandatory)
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Más tarde'),
            ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Descargar'),
          ),
        ],
      ),
    );
    if (accepted == true) await openApkUrl(info.apkUrl);
  }

  /// Abre la URL del APK en el navegador del sistema. Android descarga el
  /// archivo y al tocarlo lanza el instalador; como mantenemos la misma
  /// firma (debug keystore), aparece como "Actualizar", no "Instalar",
  /// y conserva datos.
  ///
  /// Antes de lanzar el descargador, resetea los tours guiados — la próxima
  /// vez que la app abra (ya con la nueva versión), todos los tours se
  /// mostrarán de nuevo con las novedades.
  Future<bool> openApkUrl(String url) async {
    await TourService().resetTours();

    final uri = Uri.parse(url);
    if (!await canLaunchUrl(uri)) return false;
    return launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
