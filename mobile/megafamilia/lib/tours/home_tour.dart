import 'package:flutter/material.dart';
import 'package:tutorial_coach_mark/tutorial_coach_mark.dart';

import '../services/tour_service.dart';

/// GlobalKeys que el `ClienteDashboard` debe asignar a los widgets resaltados.
class HomeTourKeys {
  final GlobalKey saldo = GlobalKey();
  final GlobalKey pago = GlobalKey();
  final GlobalKey controlFamiliar = GlobalKey();
  final GlobalKey tickets = GlobalKey();
  final GlobalKey navegacion = GlobalKey();
}

class HomeTour {
  final BuildContext context;
  final HomeTourKeys keys;
  HomeTour(this.context, this.keys);

  Future<void> show() async {
    final allTargets = _buildTargets();
    // Filtra los targets cuyo GlobalKey no tiene widget montado en el árbol.
    // Esto evita crashes si una sección no está visible en la pantalla actual.
    final targets = allTargets
        .where((t) => t.keyTarget?.currentContext != null)
        .toList();
    if (targets.isEmpty) return;

    final tutorial = TutorialCoachMark(
      targets: targets,
      colorShadow: Colors.black,
      paddingFocus: 8,
      opacityShadow: 0.85,
      textSkip: 'Saltar',
      hideSkip: false,
      onFinish: () => TourService().markTourDone('home'),
      onSkip: () {
        TourService().markTourDone('home');
        return true;
      },
    );
    tutorial.show(context: context);
  }

  List<TargetFocus> _buildTargets() => [
        _target(
          identify: 'saldo',
          key: keys.saldo,
          title: '💰 Saldo y estado',
          body: 'Aquí ves el saldo de tu cuenta y el estado del servicio de internet en tiempo real.',
          shape: ShapeLightFocus.RRect,
        ),
        _target(
          identify: 'pago',
          key: keys.pago,
          title: '💳 Pago rápido',
          body: 'Toca aquí para pagar tu factura sin salir de la app. Aceptamos múltiples métodos.',
        ),
        _target(
          identify: 'control_familiar',
          key: keys.controlFamiliar,
          title: '👨‍👩‍👧 Control familiar',
          body: 'Administra los dispositivos de tus hijos: tiempo de pantalla, bloqueos, ubicación y tareas.',
        ),
        _target(
          identify: 'tickets',
          key: keys.tickets,
          title: '🎫 Soporte',
          body: 'Abre tickets de soporte si tienes alguna falla. Recibirás respuesta del equipo en minutos.',
        ),
        _target(
          identify: 'navegacion',
          key: keys.navegacion,
          title: '🧭 Menú de navegación',
          body: 'Usa el menú inferior para moverte entre las secciones principales de la app.',
          shape: ShapeLightFocus.RRect,
        ),
      ];

  TargetFocus _target({
    required String identify,
    required GlobalKey key,
    required String title,
    required String body,
    ShapeLightFocus shape = ShapeLightFocus.RRect,
  }) {
    return TargetFocus(
      identify: identify,
      keyTarget: key,
      shape: shape,
      radius: 12,
      contents: [
        TargetContent(
          align: ContentAlign.bottom,
          builder: (ctx, ctrl) => _Card(
            title: title,
            body: body,
            onNext: () => ctrl.next(),
            onSkip: () => ctrl.skip(),
          ),
        ),
      ],
    );
  }
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
