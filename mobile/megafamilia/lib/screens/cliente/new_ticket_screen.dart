import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/cliente_provider.dart';
import '../../theme.dart';

class NewTicketScreen extends StatefulWidget {
  const NewTicketScreen({super.key});
  @override
  State<NewTicketScreen> createState() => _NewTicketScreenState();
}

class _NewTicketScreenState extends State<NewTicketScreen> {
  String _category = 'Internet lento';
  final _subject = TextEditingController();
  final _description = TextEditingController();
  bool _hasPhoto = false;
  bool _submitting = false;

  static const _categories = [
    'Internet lento',
    'Sin servicio',
    'Pagos',
    'Facturación',
    'Control parental',
    'Instalación',
    'Cambio de domicilio',
  ];

  Future<void> _submit() async {
    if (_subject.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Agrega un asunto')));
      return;
    }
    setState(() => _submitting = true);
    final res = await context.read<ClienteProvider>().createTicket({
      'category': _category,
      'subject': _subject.text.trim(),
      'description': _description.text.trim(),
    });
    setState(() => _submitting = false);
    if (res != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ticket creado: ${res.number}')));
      context.go('/cliente/tickets');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nuevo ticket')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Categoría', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _categories.map((c) {
              final selected = _category == c;
              return ChoiceChip(
                label: Text(c),
                selected: selected,
                onSelected: (_) => setState(() => _category = c),
                selectedColor: BrandColors.primary,
                labelStyle: TextStyle(color: selected ? Colors.white : BrandColors.textPrimary),
              );
            }).toList(),
          ),
          const SizedBox(height: 20),
          const Text('Asunto', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          TextField(controller: _subject, decoration: const InputDecoration(hintText: 'Resumen breve del problema')),
          const SizedBox(height: 16),
          const Text('Descripción', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          TextField(
            controller: _description,
            decoration: const InputDecoration(hintText: 'Cuéntanos qué está pasando…'),
            maxLines: 6,
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: () => setState(() => _hasPhoto = !_hasPhoto),
            icon: Icon(_hasPhoto ? Icons.check_circle : Icons.add_a_photo_outlined, color: _hasPhoto ? BrandColors.success : null),
            label: Text(_hasPhoto ? 'Foto adjunta (demo)' : 'Adjuntar foto'),
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Enviar ticket'),
          ),
        ],
      ),
    );
  }
}
