import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../providers/embajador_provider.dart';

class FollowupFormScreen extends StatefulWidget {
  final int prospectId;
  const FollowupFormScreen({super.key, required this.prospectId});

  @override
  State<FollowupFormScreen> createState() => _FollowupFormScreenState();
}

class _FollowupFormScreenState extends State<FollowupFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _notesCtrl = TextEditingController();

  String _action = 'call';
  DateTime? _nextActionDate;
  bool _saving = false;

  static const _acciones = [
    ('call', 'Llamada telefónica', Icons.call),
    ('whatsapp', 'WhatsApp', Icons.chat),
    ('visit', 'Visita presencial', Icons.directions_walk),
    ('sms', 'SMS', Icons.sms),
    ('email', 'Correo electrónico', Icons.email),
    ('note', 'Nota interna', Icons.note),
  ];

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _nextActionDate ?? DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _nextActionDate = picked);
  }

  Future<void> _guardar() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);

    final data = <String, dynamic>{
      'action': _action,
      'notes': _notesCtrl.text.trim(),
      if (_nextActionDate != null)
        'next_action_date': DateFormat('yyyy-MM-dd').format(_nextActionDate!),
    };

    final prov = context.read<EmbajadorProvider>();
    final followup = await prov.addFollowup(widget.prospectId, data);

    if (!mounted) return;
    setState(() => _saving = false);

    if (followup != null) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Seguimiento registrado.')));
      context.pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(prov.error ?? 'Error al guardar.'),
        backgroundColor: Colors.red,
      ));
    }
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Registrar seguimiento')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // ── Tipo de acción
            Text('Tipo de acción *', style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _acciones.map((a) {
                final selected = _action == a.$1;
                return FilterChip(
                  avatar: Icon(a.$3,
                      size: 16,
                      color: selected
                          ? Theme.of(context).colorScheme.onPrimary
                          : null),
                  label: Text(a.$2),
                  selected: selected,
                  onSelected: (_) => setState(() => _action = a.$1),
                  selectedColor: Theme.of(context).colorScheme.primary,
                  labelStyle: TextStyle(
                    color: selected
                        ? Theme.of(context).colorScheme.onPrimary
                        : null,
                  ),
                );
              }).toList(),
            ),
            const SizedBox(height: 16),

            // ── Notas
            TextFormField(
              controller: _notesCtrl,
              decoration: const InputDecoration(
                labelText: 'Notas del seguimiento *',
                hintText: '¿Qué hablaron? ¿Cuál fue el resultado?',
                alignLabelWithHint: true,
              ),
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Las notas son obligatorias.' : null,
            ),
            const SizedBox(height: 16),

            // ── Fecha próxima acción
            Text('Próxima acción (opcional)',
                style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 8),
            InkWell(
              onTap: _pickDate,
              borderRadius: BorderRadius.circular(8),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                decoration: BoxDecoration(
                  border: Border.all(
                      color: Theme.of(context).colorScheme.outline.withOpacity(0.5)),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(children: [
                  Icon(Icons.event, color: Theme.of(context).colorScheme.primary),
                  const SizedBox(width: 12),
                  Text(
                    _nextActionDate != null
                        ? DateFormat('dd/MM/yyyy').format(_nextActionDate!)
                        : 'Seleccionar fecha',
                    style: TextStyle(
                        color: _nextActionDate != null
                            ? null
                            : Theme.of(context).hintColor),
                  ),
                  const Spacer(),
                  if (_nextActionDate != null)
                    IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () => setState(() => _nextActionDate = null),
                    ),
                ]),
              ),
            ),
            const SizedBox(height: 24),

            // ── Guardar
            FilledButton(
              onPressed: _saving ? null : _guardar,
              child: _saving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Registrar seguimiento'),
            ),
          ],
        ),
      ),
    );
  }
}
