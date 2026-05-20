import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/hijo_provider.dart';
import '../../theme.dart';

class LogrosScreen extends StatelessWidget {
  const LogrosScreen({super.key});

  static const _badges = [
    {'icon': Icons.shield, 'label': 'Protector', 'unlocked': true, 'color': Colors.blue},
    {'icon': Icons.star, 'label': 'Estrella', 'unlocked': true, 'color': Colors.amber},
    {'icon': Icons.bolt, 'label': 'Veloz', 'unlocked': true, 'color': Colors.orange},
    {'icon': Icons.menu_book, 'label': 'Lector', 'unlocked': false, 'color': Colors.brown},
    {'icon': Icons.fitness_center, 'label': 'Atleta', 'unlocked': false, 'color': Colors.green},
    {'icon': Icons.music_note, 'label': 'Pianista', 'unlocked': true, 'color': Colors.pink},
    {'icon': Icons.psychology, 'label': 'Genio', 'unlocked': false, 'color': Colors.purple},
    {'icon': Icons.science, 'label': 'Curioso', 'unlocked': false, 'color': Colors.teal},
  ];

  @override
  Widget build(BuildContext context) {
    final h = context.watch<HijoProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Mis logros')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            color: const Color(0xFFFFF8E1),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 24),
              child: Column(
                children: [
                  const Icon(Icons.stars, color: Colors.amber, size: 56),
                  const SizedBox(height: 8),
                  Text('${h.points}', style: const TextStyle(fontSize: 48, fontWeight: FontWeight.bold, color: BrandColors.warning)),
                  const Text('puntos acumulados', style: TextStyle(color: BrandColors.textMuted)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  const Icon(Icons.local_fire_department, color: BrandColors.danger, size: 32),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Racha de ${h.streakWeeks} semanas', style: const TextStyle(fontWeight: FontWeight.bold)),
                        const Text('¡Sigue así!', style: TextStyle(color: BrandColors.textMuted)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Insignias', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 4, mainAxisSpacing: 8, crossAxisSpacing: 8, childAspectRatio: 0.95),
            itemCount: _badges.length,
            itemBuilder: (_, i) {
              final b = _badges[i];
              final unlocked = b['unlocked'] as bool;
              final color = b['color'] as Color;
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CircleAvatar(
                        backgroundColor: unlocked ? color.withOpacity(0.15) : Colors.grey.shade200,
                        child: Icon(b['icon'] as IconData, color: unlocked ? color : Colors.grey),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        b['label'] as String,
                        style: TextStyle(fontSize: 11, color: unlocked ? BrandColors.textPrimary : BrandColors.textMuted),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
