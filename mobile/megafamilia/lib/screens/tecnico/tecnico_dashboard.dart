import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../providers/tecnico_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/widgets.dart';

class TecnicoDashboard extends StatefulWidget {
  const TecnicoDashboard({super.key});
  @override
  State<TecnicoDashboard> createState() => _TecnicoDashboardState();
}

class _TecnicoDashboardState extends State<TecnicoDashboard> {
  int _bottomIndex = 0;

  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<TecnicoProvider>().loadOrdenes());
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final tec = context.watch<TecnicoProvider>();

    final today = DateTime.now();
    final todays = tec.ordenes.where((o) {
      final d = o.scheduledAt ?? today;
      return d.year == today.year && d.month == today.month && d.day == today.day;
    }).toList();

    final pendientes = tec.ordenes.where((o) => o.status == 'pendiente').length;
    final enProceso = tec.ordenes.where((o) => o.status == 'en_proceso').length;
    final completadas = tec.ordenes.where((o) => o.status == 'completada').length;

    return Scaffold(
      appBar: AppBar(
        title: Text('Hola, ${auth.session?.name ?? "técnico"} 🔧'),
        actions: [
          IconButton(onPressed: () => auth.logout(), icon: const Icon(Icons.logout)),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => tec.loadOrdenes(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: StatCard(label: 'Pendientes', value: '$pendientes', icon: Icons.pending_actions, iconColor: BrandColors.warning)),
                const SizedBox(width: 8),
                Expanded(child: StatCard(label: 'En proceso', value: '$enProceso', icon: Icons.engineering, iconColor: BrandColors.secondary)),
              ],
            ),
            const SizedBox(height: 8),
            StatCard(label: 'Completadas', value: '$completadas', icon: Icons.check_circle, iconColor: BrandColors.success),
            const SizedBox(height: 16),
            const Text('Órdenes de hoy', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            if (todays.isEmpty)
              const CenteredState(message: 'Sin órdenes programadas hoy.', icon: Icons.event_available)
            else
              ...todays.map((o) => Card(
                    child: ListTile(
                      leading: CircleAvatar(backgroundColor: _typeColor(o.type).withOpacity(0.15), child: Icon(_typeIcon(o.type), color: _typeColor(o.type))),
                      title: Text(o.clientName, style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text('${o.number} · ${o.type}\n${o.address}', maxLines: 3, overflow: TextOverflow.ellipsis),
                      isThreeLine: true,
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (o.scheduledAt != null) Text(hora(o.scheduledAt!), style: const TextStyle(fontWeight: FontWeight.bold)),
                          const SizedBox(height: 4),
                          StatusBadge(_statusLabel(o.status)),
                        ],
                      ),
                      onTap: () => context.go('/tecnico/ordenes/${o.id}'),
                    ),
                  )),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _bottomIndex,
        onDestinationSelected: (i) {
          setState(() => _bottomIndex = i);
          switch (i) {
            case 1: context.go('/tecnico/ordenes'); break;
            case 2: ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mapa — requiere Google Maps (no en v0.1)'))); break;
            case 3: ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Perfil — próximamente'))); break;
          }
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Inicio'),
          NavigationDestination(icon: Icon(Icons.assignment_outlined), selectedIcon: Icon(Icons.assignment), label: 'Órdenes'),
          NavigationDestination(icon: Icon(Icons.map_outlined), selectedIcon: Icon(Icons.map), label: 'Mapa'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Perfil'),
        ],
      ),
    );
  }
}

Color _typeColor(String type) {
  switch (type.toLowerCase()) {
    case 'instalación':
    case 'instalacion':
      return BrandColors.primary;
    case 'reparación':
    case 'reparacion':
      return BrandColors.danger;
    case 'mantenimiento':
      return BrandColors.secondary;
    default:
      return Colors.grey;
  }
}

IconData _typeIcon(String type) {
  switch (type.toLowerCase()) {
    case 'instalación':
    case 'instalacion':
      return Icons.cable;
    case 'reparación':
    case 'reparacion':
      return Icons.build;
    case 'mantenimiento':
      return Icons.miscellaneous_services;
    default:
      return Icons.assignment;
  }
}

String _statusLabel(String s) {
  switch (s) {
    case 'pendiente': return 'Pendiente';
    case 'en_proceso': return 'En proceso';
    case 'completada': return 'Completada';
    default: return s;
  }
}
