import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../providers/cliente_provider.dart';
import '../../theme.dart';
import '../../widgets/widgets.dart';

class FacturasScreen extends StatefulWidget {
  const FacturasScreen({super.key});
  @override
  State<FacturasScreen> createState() => _FacturasScreenState();
}

class _FacturasScreenState extends State<FacturasScreen> with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
    Future.microtask(() => context.read<ClienteProvider>().loadFacturas());
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Facturas'),
        bottom: TabBar(
          controller: _tab,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Todas'),
            Tab(text: 'Pagadas'),
            Tab(text: 'Pendientes'),
          ],
        ),
      ),
      body: c.loadingFacturas && c.facturas.isEmpty
          ? const LoadingView()
          : c.facturas.isEmpty
              ? const CenteredState(message: 'No tienes facturas todavía.', icon: Icons.receipt_long_outlined)
              : TabBarView(controller: _tab, children: [
                  _list(c.facturas),
                  _list(c.facturas.where((f) => f.status.toLowerCase() == 'pagada').toList()),
                  _list(c.facturas.where((f) => f.status.toLowerCase() != 'pagada').toList()),
                ]),
    );
  }

  Widget _list(List<Factura> items) {
    if (items.isEmpty) return const CenteredState(message: 'Sin facturas en esta categoría.');
    final df = DateFormat('d MMM yyyy', 'es');
    final mf = NumberFormat.currency(locale: 'es_MX', symbol: '\$');
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: items.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        final f = items[i];
        return Card(
          child: ListTile(
            leading: const CircleAvatar(backgroundColor: Color(0xFFFFF1E2), child: Icon(Icons.receipt_long, color: BrandColors.primary)),
            title: Text(f.number, style: const TextStyle(fontWeight: FontWeight.bold)),
            subtitle: Text(f.date != null ? df.format(f.date!) : '—'),
            trailing: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(mf.format(f.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                StatusBadge(f.status),
              ],
            ),
            onTap: () => context.go('/cliente/facturas/${f.id}'),
          ),
        );
      },
    );
  }
}
