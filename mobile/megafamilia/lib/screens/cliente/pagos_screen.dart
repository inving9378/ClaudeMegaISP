import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/cliente_provider.dart';
import '../../theme.dart';
import '../../utils/fechas.dart';
import '../../widgets/widgets.dart';

class PagosScreen extends StatefulWidget {
  const PagosScreen({super.key});
  @override
  State<PagosScreen> createState() => _PagosScreenState();
}

class _PagosScreenState extends State<PagosScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      final c = context.read<ClienteProvider>();
      c.loadFacturas();
      c.loadPagos();
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = context.watch<ClienteProvider>();
    final pending = c.facturas.where((f) => f.status.toLowerCase() != 'pagada').toList();
    final totalDue = pending.fold<double>(0, (a, b) => a + b.amount);
    final nextDue = pending.isNotEmpty ? pending.first : null;
    String money(double v) => '\$${v.toStringAsFixed(2)}';

    final overdue = nextDue?.date != null && nextDue!.date!.isBefore(DateTime.now());

    return Scaffold(
      appBar: AppBar(title: const Text('Pagos')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            color: overdue ? BrandColors.danger.withOpacity(0.08) : null,
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Total a pagar', style: TextStyle(color: BrandColors.textMuted)),
                  const SizedBox(height: 4),
                  Text(money(totalDue), style: TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: overdue ? BrandColors.danger : BrandColors.primary)),
                  if (nextDue != null) ...[
                    const SizedBox(height: 8),
                    Text('Ref: ${nextDue.number}', style: const TextStyle(color: BrandColors.textMuted)),
                    const SizedBox(height: 2),
                    Text(
                      'Límite: ${nextDue.date != null ? fechaLarga(nextDue.date!) : "—"}',
                      style: TextStyle(color: overdue ? BrandColors.danger : BrandColors.textMuted, fontWeight: overdue ? FontWeight.bold : FontWeight.normal),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Métodos de pago', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 8),
          _PaymentMethod(icon: Icons.credit_card, label: 'Tarjeta de crédito / débito', color: BrandColors.primary),
          _PaymentMethod(icon: Icons.account_balance, label: 'Transferencia bancaria (SPEI)', color: BrandColors.secondary),
          _PaymentMethod(icon: Icons.store, label: 'OXXO', color: BrandColors.warning),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: pending.isEmpty
                ? null
                : () => ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Procesador de pagos — pendiente'))),
            icon: const Icon(Icons.payment),
            label: const Text('Pagar ahora'),
          ),
          const SizedBox(height: 24),
          const Text('Historial', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 8),
          if (c.pagos.isEmpty)
            const CenteredState(message: 'Sin pagos previos.', icon: Icons.history)
          else
            ...c.pagos.map((p) => Card(
                  child: ListTile(
                    leading: const Icon(Icons.check_circle, color: BrandColors.success),
                    title: Text(money(p.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text(p.method),
                    trailing: Text(p.date != null ? fechaLarga(p.date!) : '—', style: const TextStyle(color: BrandColors.textMuted, fontSize: 12)),
                  ),
                )),
        ],
      ),
    );
  }
}

class _PaymentMethod extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _PaymentMethod({required this.icon, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(backgroundColor: color.withOpacity(0.1), child: Icon(icon, color: color)),
        title: Text(label),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}
