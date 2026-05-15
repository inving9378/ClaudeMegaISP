import 'package:flutter/material.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import 'package:provider/provider.dart';

import '../../providers/control_parental_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class ChildDetailScreen extends StatefulWidget {
  final int childId;
  const ChildDetailScreen({required this.childId, super.key});

  @override
  State<ChildDetailScreen> createState() => _ChildDetailScreenState();
}

class _ChildDetailScreenState extends State<ChildDetailScreen> with SingleTickerProviderStateMixin {
  late TabController _tab;
  Map<String, dynamic>? _detail;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 5, vsync: this);
    Future.microtask(() async {
      final d = await context.read<ControlParentalProvider>().loadDetail(widget.childId);
      setState(() => _detail = d);
    });
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cp = context.watch<ControlParentalProvider>();
    final perfil = cp.profiles.firstWhere(
      (p) => p.id == widget.childId,
      orElse: () => throw StateError('perfil ${widget.childId} no encontrado'),
    );

    final used = (_detail?['time_minutes_today'] as int?) ?? 0;
    final limit = (_detail?['time_limit_minutes'] as int?) ?? 180;
    final pct = (used / (limit == 0 ? 1 : limit)).clamp(0.0, 1.0);
    final remaining = (limit - used).clamp(0, limit);

    return Scaffold(
      appBar: AppBar(
        title: Text(perfil.name),
        bottom: TabBar(
          controller: _tab,
          indicatorColor: Colors.white,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Resumen'),
            Tab(text: 'Apps'),
            Tab(text: 'Horarios'),
            Tab(text: 'Tareas'),
            Tab(text: 'Ubicación'),
          ],
        ),
      ),
      body: TabBarView(controller: _tab, children: [
        _resumen(perfil, pct, used, limit, remaining),
        _apps(),
        const CenteredState(message: 'Define horarios de bloqueo aquí.\nEditor pendiente de implementar.', icon: Icons.schedule),
        const CenteredState(message: 'Tareas asignadas a este perfil.\nIntegrar con módulo Tareas.', icon: Icons.task_alt),
        const CenteredState(message: 'Última ubicación conocida.\nRequiere google_maps_flutter (no en v0.1).', icon: Icons.location_on_outlined),
      ]),
    );
  }

  Widget _resumen(perfil, double pct, int used, int limit, int remaining) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Center(
          child: CircularPercentIndicator(
            radius: 90,
            lineWidth: 14,
            percent: pct,
            animation: true,
            backgroundColor: Colors.grey.shade200,
            progressColor: pct > 0.9 ? BrandColors.danger : (pct > 0.7 ? BrandColors.warning : BrandColors.success),
            circularStrokeCap: CircularStrokeCap.round,
            center: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('$remaining', style: const TextStyle(fontSize: 36, fontWeight: FontWeight.bold)),
                const Text('min restantes', style: TextStyle(color: BrandColors.textMuted)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Center(child: Text('Tiempo hoy: $used / $limit min', style: const TextStyle(color: BrandColors.textMuted))),
        const SizedBox(height: 24),
        Row(
          children: [
            Expanded(child: _quickActionBtn(Icons.pause_circle_outline, 'Pausar internet', BrandColors.danger)),
            const SizedBox(width: 8),
            Expanded(child: _quickActionBtn(Icons.add_circle_outline, 'Dar tiempo extra', BrandColors.success)),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _quickActionBtn(Icons.location_searching, 'Ver ubicación', BrandColors.secondary)),
            const SizedBox(width: 8),
            Expanded(child: _quickActionBtn(Icons.notifications_active_outlined, 'Enviar mensaje', BrandColors.warning)),
          ],
        ),
      ],
    );
  }

  Widget _quickActionBtn(IconData icon, String label, Color color) {
    return OutlinedButton.icon(
      onPressed: () => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$label — pendiente'))),
      icon: Icon(icon, color: color),
      label: Text(label, style: const TextStyle(fontSize: 12)),
      style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 12)),
    );
  }

  Widget _apps() {
    final apps = (_detail?['apps'] as List?) ?? [];
    if (apps.isEmpty) return const CenteredState(message: 'Sin datos de uso de apps.');
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: apps.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (_, i) {
        final a = apps[i] as Map<String, dynamic>;
        return ListTile(
          leading: const CircleAvatar(backgroundColor: Color(0xFFFFF1E2), child: Icon(Icons.apps, color: BrandColors.primary)),
          title: Text(a['name']?.toString() ?? '?'),
          trailing: Text('${a['minutes']} min', style: const TextStyle(fontWeight: FontWeight.bold)),
        );
      },
    );
  }
}
