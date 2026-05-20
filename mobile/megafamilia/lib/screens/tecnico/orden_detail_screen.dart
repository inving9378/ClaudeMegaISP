import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/tecnico_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/widgets.dart';

class OrdenDetailScreen extends StatelessWidget {
  final int ordenId;
  const OrdenDetailScreen({required this.ordenId, super.key});

  @override
  Widget build(BuildContext context) {
    final tec = context.watch<TecnicoProvider>();
    final o = tec.ordenes.firstWhere(
      (x) => x.id == ordenId,
      orElse: () => throw StateError('orden $ordenId no encontrada'),
    );
    return Scaffold(
      appBar: AppBar(title: Text('Orden ${o.number}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Chip(label: Text(o.type), backgroundColor: Colors.orange.shade100),
              const SizedBox(width: 8),
              StatusBadge(_statusLabel(o.status)),
            ],
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Cliente', style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Row(children: [const Icon(Icons.person_outline, size: 18, color: BrandColors.textMuted), const SizedBox(width: 6), Text(o.clientName)]),
                  if (o.clientPhone != null) ...[
                    const SizedBox(height: 4),
                    Row(children: [const Icon(Icons.phone, size: 18, color: BrandColors.textMuted), const SizedBox(width: 6), Text(o.clientPhone!)]),
                  ],
                  const SizedBox(height: 4),
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [const Icon(Icons.place_outlined, size: 18, color: BrandColors.textMuted), const SizedBox(width: 6), Expanded(child: Text(o.address))]),
                ],
              ),
            ),
          ),
          if (o.planName != null) ...[
            const SizedBox(height: 16),
            Card(
              child: ListTile(
                leading: const Icon(Icons.router_outlined, color: BrandColors.primary),
                title: const Text('Plan contratado'),
                subtitle: Text(o.planName!),
              ),
            ),
          ],
          if (o.scheduledAt != null) ...[
            const SizedBox(height: 16),
            Card(
              child: ListTile(
                leading: const Icon(Icons.event, color: BrandColors.secondary),
                title: const Text('Programado para'),
                subtitle: Text(fechaConDia(o.scheduledAt!)),
              ),
            ),
          ],
          if (o.notes != null && o.notes!.isNotEmpty) ...[
            const SizedBox(height: 16),
            Card(
              color: const Color(0xFFFFF8E1),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Notas de admin', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 4),
                    Text(o.notes!),
                  ],
                ),
              ),
            ),
          ],
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: () => context.go('/tecnico/ordenes/${o.id}/workflow'),
            icon: const Icon(Icons.play_arrow),
            label: Text(o.status == 'pendiente' ? 'Iniciar trabajo' : 'Continuar trabajo'),
          ),
        ],
      ),
    );
  }
}

String _statusLabel(String s) =>
    ({'pendiente': 'Pendiente', 'en_proceso': 'En proceso', 'completada': 'Completada'})[s] ?? s;
