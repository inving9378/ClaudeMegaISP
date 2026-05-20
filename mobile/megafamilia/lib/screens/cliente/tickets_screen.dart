import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/cliente_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/widgets.dart';

class TicketsScreen extends StatefulWidget {
  const TicketsScreen({super.key});
  @override
  State<TicketsScreen> createState() => _TicketsScreenState();
}

class _TicketsScreenState extends State<TicketsScreen> with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
    Future.microtask(() => context.read<ClienteProvider>().loadTickets());
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  bool _abierto(Ticket t) {
    final s = t.status.toLowerCase();
    return s.contains('nuevo') || s.contains('esperando');
  }

  bool _enProceso(Ticket t) {
    final s = t.status.toLowerCase();
    return s.contains('proceso') || s.contains('curso');
  }

  bool _cerrado(Ticket t) {
    final s = t.status.toLowerCase();
    return s.contains('resuelto') || s.contains('cerrado');
  }

  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tickets'),
        bottom: TabBar(
          controller: _tab,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Abiertos'),
            Tab(text: 'En proceso'),
            Tab(text: 'Cerrados'),
          ],
        ),
      ),
      body: c.loadingTickets && c.tickets.isEmpty
          ? const LoadingView()
          : TabBarView(controller: _tab, children: [
              _list(c.tickets.where(_abierto).toList()),
              _list(c.tickets.where(_enProceso).toList()),
              _list(c.tickets.where(_cerrado).toList()),
            ]),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.go('/cliente/tickets/nuevo'),
        icon: const Icon(Icons.add),
        label: const Text('Nuevo'),
        backgroundColor: BrandColors.primary,
        foregroundColor: Colors.white,
      ),
    );
  }

  Widget _list(List<Ticket> items) {
    if (items.isEmpty) return const CenteredState(message: 'Sin tickets en esta categoría.', icon: Icons.confirmation_number_outlined);
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: items.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        final t = items[i];
        return Card(
          child: ListTile(
            leading: const CircleAvatar(backgroundColor: Color(0xFFE3F2FD), child: Icon(Icons.confirmation_number, color: BrandColors.secondary)),
            title: Text(t.subject, maxLines: 2, overflow: TextOverflow.ellipsis),
            subtitle: Text('${t.number} · ${t.date != null ? fechaCorta(t.date!) : "—"}'),
            trailing: StatusBadge(t.status),
          ),
        );
      },
    );
  }
}
