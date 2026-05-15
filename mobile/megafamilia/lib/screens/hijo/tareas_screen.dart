import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/hijo_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class TareasScreen extends StatefulWidget {
  const TareasScreen({super.key});
  @override
  State<TareasScreen> createState() => _TareasScreenState();
}

class _TareasScreenState extends State<TareasScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<HijoProvider>().loadTareas());
  }

  IconData _rewardIcon(String type) {
    switch (type) {
      case 'time_extra': return Icons.timer;
      case 'app_unlock': return Icons.lock_open;
      case 'points': return Icons.stars;
      case 'badge': return Icons.military_tech;
      default: return Icons.card_giftcard;
    }
  }

  @override
  Widget build(BuildContext context) {
    final h = context.watch<HijoProvider>();
    final pendientes = h.tareas.where((t) => t.status == 'pending').toList();
    final hechas = h.tareas.where((t) => t.status != 'pending').toList();

    return Scaffold(
      appBar: AppBar(title: const Text('Mis tareas')),
      body: h.loading && h.tareas.isEmpty
          ? const LoadingView()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (pendientes.isNotEmpty) const Text('Pendientes', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ...pendientes.map((t) => _tile(t, h, true)),
                if (hechas.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  const Text('Completadas', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  ...hechas.map((t) => _tile(t, h, false)),
                ],
                if (pendientes.isEmpty && hechas.isEmpty) const CenteredState(message: 'Aún no tienes tareas asignadas.', icon: Icons.task_alt),
              ],
            ),
    );
  }

  Widget _tile(Tarea t, HijoProvider h, bool pending) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(backgroundColor: BrandColors.warning.withOpacity(0.12), child: Icon(_rewardIcon(t.rewardType), color: BrandColors.warning)),
        title: Text(t.title, style: TextStyle(fontWeight: FontWeight.bold, decoration: pending ? null : TextDecoration.lineThrough, color: pending ? null : BrandColors.textMuted)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (t.description != null && t.description!.isNotEmpty) Text(t.description!),
            const SizedBox(height: 2),
            Text(t.rewardDetail ?? '', style: const TextStyle(color: BrandColors.warning, fontWeight: FontWeight.w600)),
          ],
        ),
        trailing: pending
            ? FilledButton(
                onPressed: () => h.completarTarea(t.id),
                style: FilledButton.styleFrom(backgroundColor: BrandColors.success, padding: const EdgeInsets.symmetric(horizontal: 12)),
                child: const Text('Hecha'),
              )
            : StatusBadge(t.status),
        isThreeLine: t.description != null && t.description!.isNotEmpty,
      ),
    );
  }
}
