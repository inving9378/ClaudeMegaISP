import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/control_parental_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class ControlParentalScreen extends StatefulWidget {
  const ControlParentalScreen({super.key});
  @override
  State<ControlParentalScreen> createState() => _ControlParentalScreenState();
}

class _ControlParentalScreenState extends State<ControlParentalScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<ControlParentalProvider>().loadProfiles());
  }

  @override
  Widget build(BuildContext context) {
    final p = context.watch<ControlParentalProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Control parental')),
      body: p.loading && p.profiles.isEmpty
          ? const LoadingView()
          : p.profiles.isEmpty
              ? const CenteredState(message: 'Aún no has registrado perfiles de hijos.', icon: Icons.family_restroom)
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    SizedBox(
                      height: 170,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: p.profiles.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 12),
                        itemBuilder: (_, i) {
                          final c = p.profiles[i];
                          return SizedBox(
                            width: 160,
                            child: InkWell(
                              onTap: () => context.go('/cliente/parental/${c.id}'),
                              child: Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.center,
                                    children: [
                                      CircleAvatar(
                                        radius: 32,
                                        backgroundColor: _colors[c.id % _colors.length],
                                        child: Text(c.initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(c.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold)),
                                      Text('${c.age ?? "—"} años', style: const TextStyle(fontSize: 12, color: BrandColors.textMuted)),
                                      const SizedBox(height: 6),
                                      Chip(
                                        avatar: const Icon(Icons.smartphone, size: 14, color: BrandColors.secondary),
                                        label: Text('${c.devicesCount} disp.'),
                                        labelStyle: const TextStyle(fontSize: 11),
                                        padding: EdgeInsets.zero,
                                        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                        visualDensity: VisualDensity.compact,
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Añadir perfil — próximamente'))),
                      icon: const Icon(Icons.add),
                      label: const Text('Añadir perfil'),
                    ),
                    const SizedBox(height: 24),
                    const Text('Resumen del día', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            const Icon(Icons.shield_moon_outlined, size: 48, color: BrandColors.warning),
                            const SizedBox(height: 8),
                            Text('${p.profiles.length} perfiles activos · ${p.profiles.fold<int>(0, (a, b) => a + b.devicesCount)} dispositivos en total', textAlign: TextAlign.center),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}

const _colors = [
  Color(0xFF5B8DEF),
  Color(0xFF38C172),
  Color(0xFFFF8C00),
  Color(0xFFE91E63),
  Color(0xFF9B59B6),
  Color(0xFF16A085),
];
