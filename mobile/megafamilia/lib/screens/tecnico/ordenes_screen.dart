import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/tecnico_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/widgets.dart';

class OrdenesScreen extends StatefulWidget {
  const OrdenesScreen({super.key});
  @override
  State<OrdenesScreen> createState() => _OrdenesScreenState();
}

class _OrdenesScreenState extends State<OrdenesScreen> with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
    Future.microtask(() => context.read<TecnicoProvider>().loadOrdenes());
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tec = context.watch<TecnicoProvider>();
    final now = DateTime.now();
    bool isToday(DateTime? d) => d != null && d.year == now.year && d.month == now.month && d.day == now.day;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Órdenes'),
        bottom: TabBar(
          controller: _tab,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Hoy'),
            Tab(text: 'Pendientes'),
            Tab(text: 'Completadas'),
          ],
        ),
      ),
      body: tec.loading && tec.ordenes.isEmpty
          ? const LoadingView()
          : TabBarView(controller: _tab, children: [
              _list(tec.ordenes.where((o) => isToday(o.scheduledAt)).toList()),
              _list(tec.ordenes.where((o) => o.status == 'pendiente').toList()),
              _list(tec.ordenes.where((o) => o.status == 'completada').toList()),
            ]),
    );
  }

  Widget _list(List<Orden> items) {
    if (items.isEmpty) return const CenteredState(message: 'Sin órdenes en esta categoría.', icon: Icons.assignment_outlined);
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: items.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        final o = items[i];
        return Card(
          child: ListTile(
            title: Text(o.clientName, style: const TextStyle(fontWeight: FontWeight.bold)),
            subtitle: Text('${o.number} · ${o.type}\n${o.address}', maxLines: 2, overflow: TextOverflow.ellipsis),
            isThreeLine: true,
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (o.scheduledAt != null) Text(fechaCortaConHora(o.scheduledAt!), style: const TextStyle(fontSize: 11, color: BrandColors.textMuted)),
                const SizedBox(height: 4),
                StatusBadge(_statusLabel(o.status)),
              ],
            ),
            onTap: () => context.go('/tecnico/ordenes/${o.id}'),
          ),
        );
      },
    );
  }
}

String _statusLabel(String s) =>
    ({'pendiente': 'Pendiente', 'en_proceso': 'En proceso', 'completada': 'Completada'})[s] ?? s;
