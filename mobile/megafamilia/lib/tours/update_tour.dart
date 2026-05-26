import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:tutorial_coach_mark/tutorial_coach_mark.dart';

import '../config.dart';
import '../services/tour_service.dart';

/// Muestra un overlay informativo post-actualización con el changelog de la
/// versión actual. A diferencia de los otros tours, no apunta a widgets —
/// usa un único TargetFocus de pantalla completa con tarjetas por bullet.
class UpdateTour {
  final BuildContext context;
  UpdateTour(this.context);

  /// Slug del tour incluye el versionCode para que CADA versión muestre su
  /// changelog una sola vez.
  Future<String> _slug() async {
    final pkg = await PackageInfo.fromPlatform();
    return 'update_v${pkg.version}_${pkg.buildNumber}';
  }

  Future<bool> shouldShow() async {
    final slug = await _slug();
    return TourService().shouldShowTour(slug);
  }

  /// Descarga la info de la versión actual desde el servidor y muestra el
  /// changelog como overlay. Solo se ejecuta una vez por versionCode.
  Future<void> show() async {
    final slug = await _slug();
    if (!await TourService().shouldShowTour(slug)) return;

    final notes = await _fetchChangelog();
    if (notes == null || notes.trim().isEmpty || !context.mounted) {
      await TourService().markTourDone(slug);
      return;
    }

    final tutorial = TutorialCoachMark(
      targets: [
        TargetFocus(
          identify: 'update_changelog',
          targetPosition: TargetPosition(const Size(1, 1), Offset.zero),
          shape: ShapeLightFocus.Circle,
          contents: [
            TargetContent(
              align: ContentAlign.custom,
              customPosition: CustomTargetContentPosition(
                top: 80, left: 24, right: 24,
              ),
              builder: (ctx, ctrl) => _ChangelogCard(
                version: AppConfig.appVersion,
                notes: notes,
                onClose: () => ctrl.skip(),
              ),
            ),
          ],
        ),
      ],
      colorShadow: Colors.black,
      opacityShadow: 0.9,
      hideSkip: true,
      onFinish: () => TourService().markTourDone(slug),
      onSkip: () {
        TourService().markTourDone(slug);
        return true;
      },
    );
    tutorial.show(context: context);
  }

  Future<String?> _fetchChangelog() async {
    try {
      final pkg = await PackageInfo.fromPlatform();
      final url = Uri.parse(
        '${AppConfig.apiBaseUrl.replaceFirst('/api/megafamilia', '')}'
        '/api/megafamilia/mobile/check-update'
        '?platform=android&current_version_code=${pkg.buildNumber}',
      );
      final res = await http.get(url).timeout(const Duration(seconds: 6));
      if (res.statusCode != 200) return null;
      final j = jsonDecode(res.body) as Map<String, dynamic>;
      return j['changelog']?.toString() ?? j['release_notes']?.toString();
    } catch (_) {
      return null;
    }
  }
}

class _ChangelogCard extends StatelessWidget {
  final String version;
  final String notes;
  final VoidCallback onClose;
  const _ChangelogCard({required this.version, required this.notes, required this.onClose});

  @override
  Widget build(BuildContext context) {
    final bullets = notes
        .split('\n')
        .map((l) => l.trim())
        .where((l) => l.isNotEmpty)
        .toList();

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              const Text('🎉', style: TextStyle(fontSize: 28)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Novedades de la v$version',
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                ),
              ),
              IconButton(onPressed: onClose, icon: const Icon(Icons.close)),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Esto es lo que cambió desde tu última versión:',
            style: TextStyle(fontSize: 12, color: Colors.black54),
          ),
          const SizedBox(height: 12),
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 320),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: bullets.map((b) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('•  ', style: TextStyle(fontSize: 14)),
                      Expanded(child: Text(b, style: const TextStyle(fontSize: 13, height: 1.4))),
                    ],
                  ),
                )).toList(),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: FilledButton(onPressed: onClose, child: const Text('¡Entendido!')),
          ),
        ],
      ),
    );
  }
}
