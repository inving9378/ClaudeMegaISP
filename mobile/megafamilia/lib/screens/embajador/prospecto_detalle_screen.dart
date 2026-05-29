import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/embajador_provider.dart';

class ProspectoDetalleScreen extends StatefulWidget {
  final int prospectId;
  const ProspectoDetalleScreen({super.key, required this.prospectId});

  @override
  State<ProspectoDetalleScreen> createState() => _ProspectoDetalleScreenState();
}

class _ProspectoDetalleScreenState extends State<ProspectoDetalleScreen> {
  Prospect? _prospect;
  List<Followup> _followups = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final prov = context.read<EmbajadorProvider>();
      final p = await prov.getProspecto(widget.prospectId);
      if (p == null) throw Exception('Prospecto no encontrado.');

      // Los followups ya vienen embebidos en el prospecto
      setState(() {
        _prospect = p;
        _followups = p.followups;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _abrirFormSeguimiento() async {
    final added = await context.push<bool>(
        '/cliente/embajador/prospectos/${widget.prospectId}/seguimiento');
    if (added == true) _load();
  }

  Future<void> _editarProspecto() async {
    final updated = await context.push<bool>(
        '/cliente/embajador/prospectos/${widget.prospectId}/editar');
    if (updated == true) _load();
  }

  Future<void> _marcarLost() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('¿Marcar como perdido?'),
        content: const Text(
            'El prospecto quedará como "Perdido". Se conserva el historial.'),
        actions: [
          TextButton(onPressed: () => ctx.pop(false), child: const Text('Cancelar')),
          FilledButton(
              onPressed: () => ctx.pop(true),
              style: FilledButton.styleFrom(backgroundColor: Colors.red),
              child: const Text('Marcar perdido')),
        ],
      ),
    );
    if (confirm != true) return;

    final ok = await context.read<EmbajadorProvider>().marcarLost(widget.prospectId);
    if (ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Prospecto marcado como perdido.')));
      context.pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    if (_error != null) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: _load, child: const Text('Reintentar')),
          ]),
        ),
      );
    }

    final p = _prospect!;
    final statusColor = Color(p.statusColorValue);

    return Scaffold(
      appBar: AppBar(
        title: Text(p.name),
        actions: [
          IconButton(icon: const Icon(Icons.edit), onPressed: _editarProspecto),
          PopupMenuButton<String>(
            onSelected: (v) {
              if (v == 'lost') _marcarLost();
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'lost', child: Text('Marcar como perdido')),
            ],
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _abrirFormSeguimiento,
        icon: const Icon(Icons.add_comment),
        label: const Text('Seguimiento'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ── Header con status
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: statusColor.withOpacity(0.15),
                    child: Text(
                      p.name.isNotEmpty ? p.name[0].toUpperCase() : '?',
                      style: TextStyle(
                          fontSize: 22, color: statusColor, fontWeight: FontWeight.bold),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(p.name,
                          style: const TextStyle(
                              fontSize: 18, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 4),
                      _StatusPill(status: p.status, label: p.statusLabel),
                    ]),
                  ),
                ]),
                const Divider(height: 24),
                _InfoRow(icon: Icons.phone, label: p.phone),
                if (p.email != null && p.email!.isNotEmpty)
                  _InfoRow(icon: Icons.email_outlined, label: p.email!),
                if (p.address != null && p.address!.isNotEmpty)
                  _InfoRow(icon: Icons.location_on_outlined, label: p.address!),
                if (p.lastContactAt != null)
                  _InfoRow(
                    icon: Icons.access_time,
                    label:
                        'Último contacto: ${DateFormat('dd/MM/yyyy').format(p.lastContactAt!)}',
                  ),
                if (p.notes != null && p.notes!.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text('Notas', style: Theme.of(context).textTheme.labelSmall),
                  const SizedBox(height: 4),
                  Text(p.notes!, style: const TextStyle(fontSize: 13)),
                ],
              ]),
            ),
          ),

          const SizedBox(height: 16),

          // ── Timeline de seguimientos
          Text('Historial de seguimientos (${_followups.length})',
              style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 8),

          if (_followups.isEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Center(
                  child: Column(children: [
                    Icon(Icons.comment_outlined, size: 40, color: Colors.grey[400]),
                    const SizedBox(height: 8),
                    Text('Sin seguimientos aún',
                        style: TextStyle(color: Colors.grey[600])),
                  ]),
                ),
              ),
            )
          else
            ...List.generate(_followups.length, (i) {
              final f = _followups[i];
              return _FollowupTile(followup: f, isLast: i == _followups.length - 1);
            }),

          // Espacio para el FAB
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

// ── Widgets auxiliares ──────────────────────────────────────────────────────

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  const _InfoRow({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(children: [
        Icon(icon, size: 16, color: Colors.grey[600]),
        const SizedBox(width: 8),
        Expanded(child: Text(label, style: const TextStyle(fontSize: 14))),
      ]),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String status;
  final String label;
  const _StatusPill({required this.status, required this.label});

  @override
  Widget build(BuildContext context) {
    final color = Color(Prospect(id: 0, name: '', phone: '', source: '', status: status).statusColorValue);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.15),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(label, style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w600)),
    );
  }
}

class _FollowupTile extends StatelessWidget {
  final Followup followup;
  final bool isLast;
  const _FollowupTile({required this.followup, required this.isLast});

  static const _icons = {
    'call': Icons.call,
    'whatsapp': Icons.chat,
    'visit': Icons.directions_walk,
    'sms': Icons.sms,
    'email': Icons.email,
    'note': Icons.note,
  };

  @override
  Widget build(BuildContext context) {
    final icon = _icons[followup.action] ?? Icons.circle;
    final date = followup.createdAt != null
        ? DateFormat('dd/MM/yyyy HH:mm').format(followup.createdAt!)
        : '';

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Línea de tiempo
          Column(children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 18,
                  color: Theme.of(context).colorScheme.onPrimaryContainer),
            ),
            if (!isLast)
              Expanded(
                  child: Container(
                      width: 2,
                      color: Theme.of(context).dividerColor)),
          ]),
          const SizedBox(width: 12),
          // Contenido
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Text(followup.actionLabel,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                    const Spacer(),
                    Text(date, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
                  ]),
                  const SizedBox(height: 4),
                  Text(followup.notes, style: const TextStyle(fontSize: 13)),
                  if (followup.nextActionDate != null) ...[
                    const SizedBox(height: 4),
                    Row(children: [
                      Icon(Icons.event, size: 13, color: Colors.orange[700]),
                      const SizedBox(width: 4),
                      Text(
                        'Próxima acción: ${DateFormat('dd/MM/yyyy').format(followup.nextActionDate!)}',
                        style: TextStyle(fontSize: 11, color: Colors.orange[700]),
                      ),
                    ]),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
