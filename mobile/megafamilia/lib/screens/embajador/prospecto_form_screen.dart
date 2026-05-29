import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../providers/embajador_provider.dart';

/// Pantalla dual: crear (prospectId == null) o editar (prospectId != null).
class ProspectoFormScreen extends StatefulWidget {
  final int? prospectId;
  const ProspectoFormScreen({super.key, this.prospectId});

  @override
  State<ProspectoFormScreen> createState() => _ProspectoFormScreenState();
}

class _ProspectoFormScreenState extends State<ProspectoFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl    = TextEditingController();
  final _phoneCtrl   = TextEditingController();
  final _emailCtrl   = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _notesCtrl   = TextEditingController();

  String _status = 'new';
  bool _saving = false;
  bool _loading = true;

  bool get _esEdicion => widget.prospectId != null;

  static const _statuses = [
    ('new', 'Nuevo'),
    ('contacted', 'Contactado'),
    ('interested', 'Interesado'),
    ('quoted', 'Cotizado'),
    ('lost', 'Perdido'),
  ];

  @override
  void initState() {
    super.initState();
    if (_esEdicion) {
      _loadProspect();
    } else {
      _loading = false;
    }
  }

  Future<void> _loadProspect() async {
    final p = await context.read<EmbajadorProvider>().getProspecto(widget.prospectId!);
    if (!mounted) return;
    if (p == null) {
      setState(() => _loading = false);
      return;
    }
    setState(() {
      _nameCtrl.text    = p.name;
      _phoneCtrl.text   = p.phone;
      _emailCtrl.text   = p.email ?? '';
      _addressCtrl.text = p.address ?? '';
      _notesCtrl.text   = p.notes ?? '';
      _status = p.status;
      _loading = false;
    });
  }

  Future<void> _guardar() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);

    final data = {
      'name':    _nameCtrl.text.trim(),
      'phone':   _phoneCtrl.text.trim(),
      if (_emailCtrl.text.trim().isNotEmpty) 'email': _emailCtrl.text.trim(),
      if (_addressCtrl.text.trim().isNotEmpty) 'address': _addressCtrl.text.trim(),
      if (_notesCtrl.text.trim().isNotEmpty) 'notes': _notesCtrl.text.trim(),
      if (_esEdicion) 'status': _status,
    };

    final prov = context.read<EmbajadorProvider>();
    bool ok;

    if (_esEdicion) {
      final updated = await prov.updateProspecto(widget.prospectId!, data);
      ok = updated != null;
    } else {
      final created = await prov.createProspecto(data);
      ok = created != null;
    }

    if (!mounted) return;
    setState(() => _saving = false);

    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(_esEdicion ? 'Prospecto actualizado.' : 'Prospecto creado.'),
      ));
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
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _addressCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(
        title: Text(_esEdicion ? 'Editar prospecto' : 'Nuevo prospecto'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // ── Nombre
            TextFormField(
              controller: _nameCtrl,
              decoration: const InputDecoration(
                labelText: 'Nombre completo *',
                prefixIcon: Icon(Icons.person),
              ),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'El nombre es obligatorio.' : null,
              textCapitalization: TextCapitalization.words,
            ),
            const SizedBox(height: 12),

            // ── Teléfono
            TextFormField(
              controller: _phoneCtrl,
              decoration: const InputDecoration(
                labelText: 'Teléfono *',
                prefixIcon: Icon(Icons.phone),
              ),
              keyboardType: TextInputType.phone,
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'El teléfono es obligatorio.' : null,
            ),
            const SizedBox(height: 12),

            // ── Email (opcional)
            TextFormField(
              controller: _emailCtrl,
              decoration: const InputDecoration(
                labelText: 'Correo electrónico',
                prefixIcon: Icon(Icons.email),
                hintText: 'Opcional',
              ),
              keyboardType: TextInputType.emailAddress,
              validator: (v) {
                if (v == null || v.trim().isEmpty) return null;
                final re = RegExp(r'^[^@]+@[^@]+\.[^@]+$');
                return re.hasMatch(v.trim()) ? null : 'Correo no válido.';
              },
            ),
            const SizedBox(height: 12),

            // ── Dirección (opcional)
            TextFormField(
              controller: _addressCtrl,
              decoration: const InputDecoration(
                labelText: 'Dirección',
                prefixIcon: Icon(Icons.location_on),
                hintText: 'Opcional',
              ),
              textCapitalization: TextCapitalization.sentences,
            ),
            const SizedBox(height: 12),

            // ── Status (solo en edición)
            if (_esEdicion) ...[
              DropdownButtonFormField<String>(
                value: _status,
                decoration: const InputDecoration(
                  labelText: 'Estado',
                  prefixIcon: Icon(Icons.flag),
                ),
                items: _statuses
                    .map((s) => DropdownMenuItem(value: s.$1, child: Text(s.$2)))
                    .toList(),
                onChanged: (v) => setState(() => _status = v!),
              ),
              const SizedBox(height: 12),
            ],

            // ── Notas
            TextFormField(
              controller: _notesCtrl,
              decoration: const InputDecoration(
                labelText: 'Notas',
                prefixIcon: Icon(Icons.note),
                hintText: 'Cualquier detalle relevante',
                alignLabelWithHint: true,
              ),
              maxLines: 4,
              textCapitalization: TextCapitalization.sentences,
            ),
            const SizedBox(height: 24),

            // ── Guardar
            FilledButton(
              onPressed: _saving ? null : _guardar,
              child: _saving
                  ? const SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(_esEdicion ? 'Guardar cambios' : 'Crear prospecto'),
            ),
          ],
        ),
      ),
    );
  }
}
