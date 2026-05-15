import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/tecnico_provider.dart';
import '../../theme.dart';

class CompletarOrdenScreen extends StatefulWidget {
  final int ordenId;
  const CompletarOrdenScreen({required this.ordenId, super.key});

  @override
  State<CompletarOrdenScreen> createState() => _CompletarOrdenScreenState();
}

class _CompletarOrdenScreenState extends State<CompletarOrdenScreen> {
  int _rating = 5;
  final _comment = TextEditingController();
  bool _signed = false;
  bool _submitting = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final tec = context.read<TecnicoProvider>();
    await tec.updateOrden(widget.ordenId, {
      'status': 'completada',
      'client_rating': _rating,
      'client_comment': _comment.text,
      'signed': _signed,
    });
    setState(() => _submitting = false);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Orden completada')));
      context.go('/tecnico');
    }
  }

  @override
  Widget build(BuildContext context) {
    final tec = context.watch<TecnicoProvider>();
    final o = tec.ordenes.firstWhere((x) => x.id == widget.ordenId);
    final state = tec.stateFor(o.id, o.type);
    final steps = state['steps'] as List;

    return Scaffold(
      appBar: AppBar(title: const Text('Completar orden')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Resumen: ${o.number}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  const SizedBox(height: 8),
                  ...steps.map((s) {
                    final m = s as Map<String, dynamic>;
                    return Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(m['done'] == true ? Icons.check_circle : Icons.cancel, color: m['done'] == true ? BrandColors.success : BrandColors.danger, size: 18),
                        const SizedBox(width: 6),
                        Expanded(child: Text(m['title']?.toString() ?? '')),
                      ],
                    );
                  }),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Firma del cliente', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Container(
            height: 120,
            decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade300, style: BorderStyle.solid), borderRadius: BorderRadius.circular(8)),
            child: Center(
              child: _signed
                  ? const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [Icon(Icons.check_circle, color: BrandColors.success, size: 40), SizedBox(height: 4), Text('Firma capturada')],
                    )
                  : TextButton.icon(
                      onPressed: () => setState(() => _signed = true),
                      icon: const Icon(Icons.edit),
                      label: const Text('Toca para firmar'),
                    ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Calificación del cliente', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (i) {
              final filled = i < _rating;
              return IconButton(
                iconSize: 40,
                onPressed: () => setState(() => _rating = i + 1),
                icon: Icon(filled ? Icons.star : Icons.star_border, color: filled ? Colors.amber : BrandColors.textMuted),
              );
            }),
          ),
          const SizedBox(height: 8),
          const Text('Comentarios', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          TextField(controller: _comment, maxLines: 4, decoration: const InputDecoration(hintText: 'Observaciones del trabajo realizado…')),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: _submitting || !_signed ? null : _submit,
            icon: const Icon(Icons.check),
            label: _submitting
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Finalizar'),
          ),
        ],
      ),
    );
  }
}
