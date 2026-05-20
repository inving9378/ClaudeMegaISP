import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/tecnico_provider.dart';
import '../../theme.dart';

class WorkFlowScreen extends StatefulWidget {
  final int ordenId;
  const WorkFlowScreen({required this.ordenId, super.key});

  @override
  State<WorkFlowScreen> createState() => _WorkFlowScreenState();
}

class _WorkFlowScreenState extends State<WorkFlowScreen> {
  late TecnicoProvider _tec;
  late Map<String, dynamic> _state;

  @override
  void initState() {
    super.initState();
    _tec = context.read<TecnicoProvider>();
    final o = _tec.ordenes.firstWhere((x) => x.id == widget.ordenId);
    _state = _tec.stateFor(o.id, o.type);
    // Marcar la orden en proceso si está pendiente
    if (o.status == 'pendiente') {
      Future.microtask(() => _tec.updateOrden(o.id, {'status': 'en_proceso'}));
    }
  }

  @override
  Widget build(BuildContext context) {
    final steps = _state['steps'] as List;
    final idx = _state['currentIndex'] as int;
    final step = steps[idx] as Map<String, dynamic>;
    final isLast = idx >= steps.length - 1;

    return Scaffold(
      appBar: AppBar(title: const Text('Flujo de trabajo')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Progress bar
            LinearProgressIndicator(
              value: (idx + 1) / steps.length,
              backgroundColor: Colors.grey.shade200,
              valueColor: const AlwaysStoppedAnimation(BrandColors.primary),
              minHeight: 8,
            ),
            const SizedBox(height: 8),
            Text('Paso ${idx + 1} de ${steps.length}', style: const TextStyle(color: BrandColors.textMuted)),
            const SizedBox(height: 24),
            Expanded(
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(step['done'] == true ? Icons.check_circle : Icons.radio_button_unchecked, color: step['done'] == true ? BrandColors.success : BrandColors.textMuted, size: 32),
                      const SizedBox(height: 12),
                      Text(step['title']?.toString() ?? '', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 16),
                      CheckboxListTile(
                        value: step['done'] == true,
                        onChanged: (v) => setState(() => step['done'] = v ?? false),
                        title: const Text('Completado'),
                        contentPadding: EdgeInsets.zero,
                        controlAffinity: ListTileControlAffinity.leading,
                      ),
                      if (step['photo'] == true) ...[
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: () => setState(() => step['photo_path'] = step['photo_path'] == null ? '/tmp/mock-photo.jpg' : null),
                          icon: Icon(step['photo_path'] != null ? Icons.check_circle : Icons.add_a_photo_outlined, color: step['photo_path'] != null ? BrandColors.success : null),
                          label: Text(step['photo_path'] != null ? 'Foto adjunta (demo)' : 'Tomar foto'),
                        ),
                      ],
                      const SizedBox(height: 12),
                      TextField(
                        decoration: const InputDecoration(hintText: 'Notas (opcional)', border: OutlineInputBorder()),
                        maxLines: 3,
                        controller: TextEditingController(text: step['notes']?.toString() ?? ''),
                        onChanged: (v) => step['notes'] = v,
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                if (idx > 0)
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => setState(() => _state['currentIndex'] = idx - 1),
                      icon: const Icon(Icons.chevron_left),
                      label: const Text('Anterior'),
                    ),
                  ),
                if (idx > 0) const SizedBox(width: 8),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: step['done'] == true
                        ? () {
                            if (isLast) {
                              context.go('/tecnico/ordenes/${widget.ordenId}/completar');
                            } else {
                              setState(() => _state['currentIndex'] = idx + 1);
                            }
                          }
                        : null,
                    icon: Icon(isLast ? Icons.check : Icons.chevron_right),
                    label: Text(isLast ? 'Completar' : 'Siguiente'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
