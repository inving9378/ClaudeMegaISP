import 'package:flutter/material.dart';
import 'package:tutorial_coach_mark/tutorial_coach_mark.dart';

import '../services/tour_service.dart';

class ControlFamiliarTourKeys {
  final GlobalKey listaHijos = GlobalKey();
  final GlobalKey estadoConexion = GlobalKey();
  final GlobalKey solicitudes = GlobalKey();
  final GlobalKey agregarHijo = GlobalKey();
}

class ControlFamiliarTour {
  final BuildContext context;
  final ControlFamiliarTourKeys keys;
  ControlFamiliarTour(this.context, this.keys);

  Future<void> show() async {
    final targets = _buildTargets()
        .where((t) => t.keyTarget?.currentContext != null)
        .toList();
    if (targets.isEmpty) return;

    final tutorial = TutorialCoachMark(
      targets: targets,
      colorShadow: Colors.black,
      opacityShadow: 0.85,
      paddingFocus: 8,
      textSkip: 'Saltar',
      onFinish: () => TourService().markTourDone('control_familiar'),
      onSkip: () {
        TourService().markTourDone('control_familiar');
        return true;
      },
    );
    tutorial.show(context: context);
  }

  List<TargetFocus> _buildTargets() => [
        _t('lista_hijos', keys.listaHijos, '👦 Lista de hijos',
            'Aquí ves todos los perfiles que has registrado. Toca uno para entrar a su panel detallado.'),
        _t('estado_conexion', keys.estadoConexion, '📱 Estado de dispositivos',
            'Verde = online, gris = offline. La hora indica la última conexión registrada.'),
        _t('solicitudes', keys.solicitudes, '📬 Solicitudes pendientes',
            'Los hijos pueden pedirte más tiempo o desbloquear apps. Aprueba o rechaza desde aquí.'),
        _t('agregar', keys.agregarHijo, '➕ Agregar hijo',
            'Registra un nuevo perfil. El menor descarga la app, escanea el QR y queda vinculado.'),
      ];

  TargetFocus _t(String id, GlobalKey key, String title, String body) => TargetFocus(
        identify: id,
        keyTarget: key,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        contents: [
          TargetContent(
            align: ContentAlign.bottom,
            builder: (ctx, ctrl) => _Card(title: title, body: body,
                onNext: () => ctrl.next(), onSkip: () => ctrl.skip()),
          ),
        ],
      );
}

class _Card extends StatelessWidget {
  final String title;
  final String body;
  final VoidCallback onNext;
  final VoidCallback onSkip;
  const _Card({required this.title, required this.body, required this.onNext, required this.onSkip});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text(body, style: const TextStyle(fontSize: 13, height: 1.4)),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              TextButton(onPressed: onSkip, child: const Text('Saltar')),
              FilledButton(onPressed: onNext, child: const Text('Siguiente')),
            ],
          ),
        ],
      ),
    );
  }
}
