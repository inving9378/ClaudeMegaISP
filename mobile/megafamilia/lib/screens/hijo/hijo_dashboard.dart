import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../providers/hijo_provider.dart';
import '../../theme.dart';

class HijoDashboard extends StatefulWidget {
  const HijoDashboard({super.key});
  @override
  State<HijoDashboard> createState() => _HijoDashboardState();
}

class _HijoDashboardState extends State<HijoDashboard> {
  int _bottomIndex = 0;

  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<HijoProvider>().loadTareas());
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final h = context.watch<HijoProvider>();
    final pct = (h.minutesUsedToday / (h.minutesLimitToday == 0 ? 1 : h.minutesLimitToday)).clamp(0.0, 1.0);
    final remaining = (h.minutesLimitToday - h.minutesUsedToday).clamp(0, h.minutesLimitToday);

    return Scaffold(
      appBar: AppBar(
        title: Text('¡Hola, ${auth.session?.name ?? "hijo"}! 🎮'),
        actions: [IconButton(onPressed: () => auth.logout(), icon: const Icon(Icons.logout))],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const SizedBox(height: 12),
          Center(
            child: CircularPercentIndicator(
              radius: 100,
              lineWidth: 16,
              percent: pct,
              animation: true,
              backgroundColor: Colors.grey.shade200,
              progressColor: pct > 0.9 ? BrandColors.danger : (pct > 0.7 ? BrandColors.warning : BrandColors.success),
              circularStrokeCap: CircularStrokeCap.round,
              center: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text('$remaining', style: const TextStyle(fontSize: 44, fontWeight: FontWeight.bold)),
                  const Text('min restantes', style: TextStyle(color: BrandColors.textMuted)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Center(child: Text('Tiempo de hoy', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600))),
          const SizedBox(height: 24),
          const Text('Apps permitidas', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 8),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 4,
            childAspectRatio: 0.95,
            crossAxisSpacing: 8,
            mainAxisSpacing: 8,
            children: const [
              _AppTile(icon: Icons.video_library, label: 'YouTube', color: Colors.red),
              _AppTile(icon: Icons.sports_esports, label: 'Juegos', color: Colors.deepPurple),
              _AppTile(icon: Icons.chat_bubble, label: 'WhatsApp', color: Colors.green),
              _AppTile(icon: Icons.music_note, label: 'Música', color: Colors.pink),
              _AppTile(icon: Icons.school, label: 'Tareas', color: Colors.blue),
              _AppTile(icon: Icons.book, label: 'Lectura', color: Colors.brown),
              _AppTile(icon: Icons.public, label: 'Web', color: Colors.teal),
              _AppTile(icon: Icons.draw, label: 'Dibujar', color: Colors.orange),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Tareas pendientes', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              TextButton(onPressed: () => context.go('/hijo/tareas'), child: const Text('Ver todas')),
            ],
          ),
          ...h.tareas.where((t) => t.status == 'pending').take(3).map((t) => Card(
                child: ListTile(
                  leading: const Icon(Icons.task_alt, color: BrandColors.success),
                  title: Text(t.title),
                  subtitle: Text(t.rewardDetail ?? ''),
                  trailing: Text('${t.points} pts', style: const TextStyle(fontWeight: FontWeight.bold, color: BrandColors.warning)),
                  onTap: () => context.go('/hijo/tareas'),
                ),
              )),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _bottomIndex,
        onDestinationSelected: (i) {
          setState(() => _bottomIndex = i);
          switch (i) {
            case 1: context.go('/hijo/tareas'); break;
            case 2: context.go('/hijo/logros'); break;
            case 3: context.go('/hijo/solicitar'); break;
          }
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Inicio'),
          NavigationDestination(icon: Icon(Icons.task_alt_outlined), selectedIcon: Icon(Icons.task_alt), label: 'Tareas'),
          NavigationDestination(icon: Icon(Icons.emoji_events_outlined), selectedIcon: Icon(Icons.emoji_events), label: 'Logros'),
          NavigationDestination(icon: Icon(Icons.help_outline), selectedIcon: Icon(Icons.help), label: 'Solicitar'),
        ],
      ),
    );
  }
}

class _AppTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _AppTile({required this.icon, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(6),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircleAvatar(backgroundColor: color.withOpacity(0.15), child: Icon(icon, color: color)),
            const SizedBox(height: 4),
            Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w500), textAlign: TextAlign.center, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}
